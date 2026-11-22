<?php

namespace App\Services;

use App\Exceptions\AccountChangeRefused;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Support\SecurityPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Single place that turns "a name + email + role" into a login account, applying the
 * same password, activation, invite and audit rules everywhere an account is born —
 * Admin user management, bulk import, HR staff onboarding, and Registrar guardian
 * creation. Keeping this in one service means the security-relevant decisions
 * (random vs admin-set password, Pending-until-invited vs Active, force-reset on
 * first login) can't drift between screens.
 */
class UserProvisioningService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Canonical map of portal role → StaffProfile.role_type. This is the single
     * source of truth; controllers must not keep their own copies.
     */
    public const STAFF_ROLE_TYPES = [
        'admin' => 'Admin',
        'principal' => 'Principal',
        'vp_academic' => 'VP_Academic',
        'registrar' => 'Registrar',
        'teacher' => 'Teacher',
        'treasurer' => 'Treasurer',
        'hr_office' => 'HR_Office',
    ];

    /** Roles that carry a StaffProfile (i.e. employees, not guardians/students). */
    public function staffRoles(): array
    {
        return array_keys(self::STAFF_ROLE_TYPES);
    }

    /**
     * Roles the HR Office is allowed to hand out. HR owns staff onboarding, but must
     * not be able to mint an Admin or a Principal — both are top-authority accounts
     * (Admin manages every user including HR; Principal holds final governance/approval
     * power), so letting HR create one is a privilege-escalation path. Admin itself keeps
     * the full {@see staffRoles()} list.
     */
    public function hrAssignableRoles(): array
    {
        return array_values(array_diff($this->staffRoles(), ['admin', 'principal']));
    }

    /** The Registrar owns family-facing accounts only: never staff, Admin, Principal or VP. */
    public function registrarAssignableRoles(): array
    {
        return ['guardian', 'student'];
    }

    /**
     * Every role $actor may hand out. Admin is the superset; HR and the Registrar each own a
     * slice; anyone else may not create accounts at all.
     *
     * @return list<string>
     */
    public function assignableRoles(User $actor): array
    {
        return match (true) {
            $actor->hasRole('admin') => Role::pluck('name')->all(),
            $actor->hasRole('hr_office') => $this->hrAssignableRoles(),
            $actor->hasRole('registrar') => $this->registrarAssignableRoles(),
            default => [],
        };
    }

    /**
     * Server-side guard behind every create screen: the forms only offer allowed roles, but a
     * hand-crafted request must be refused here too. A null actor is a trusted system caller
     * (console bootstrap), which is not subject to the check.
     *
     * @throws AuthorizationException
     */
    public function assertMayAssign(?User $actor, ?string $role): void
    {
        if ($actor === null || $role === null) {
            return;
        }

        if (! in_array($role, $this->assignableRoles($actor), true)) {
            throw new AuthorizationException("You are not allowed to create {$role} accounts.");
        }
    }

    public function staffRoleType(?string $role): string
    {
        return self::STAFF_ROLE_TYPES[$role] ?? 'Staff';
    }

    /**
     * Create a login account, assign its role, and (unless an initial password is
     * supplied) e-mail a setup link.
     *
     * With $plainPassword the account is Active immediately and, per the security
     * policy, may be flagged to reset on first login. Without one it is created in
     * $pendingStatus (normally "Pending") and, when $invite is true, a password-setup
     * link is sent.
     *
     * Pass $auditAction = null to skip the built-in "User" audit entry — used by
     * callers that log against their own domain entity (Guardian, StaffProfile) or
     * batch a single audit line for a bulk import.
     *
     * @param  array<string, mixed>  $attributes  User column values — must include name and email.
     */
    public function provisionAccount(
        array $attributes,
        ?string $role,
        ?User $actor = null,
        ?string $plainPassword = null,
        bool $invite = true,
        string $pendingStatus = 'Pending',
        ?string $auditAction = 'Created user',
    ): User {
        $this->assertMayAssign($actor, $role);

        $hasPassword = $plainPassword !== null && $plainPassword !== '';

        // Account, role and audit entry commit together or not at all. When a caller wraps this
        // in its own transaction (a guardian or staff record created alongside), it joins that one.
        return DB::transaction(function () use ($attributes, $role, $actor, $hasPassword, $plainPassword, $invite, $pendingStatus, $auditAction) {
            $user = User::create(array_merge($attributes, [
                'password' => Hash::make($hasPassword ? $plainPassword : Str::password(16)),
                'status' => $hasPassword ? 'Active' : $pendingStatus,
                'must_reset_password' => $hasPassword && SecurityPolicy::forceResetNewAccounts(),
            ]));

            if ($role !== null) {
                $user->assignRole($role);
            }

            if ($auditAction !== null && $actor !== null) {
                $this->audit->log($actor, $auditAction, 'User', $user->id, ['role' => $role, 'status' => $user->status]);
            }

            // Only e-mail a setup link once the account really exists: a rolled-back create
            // must not leave someone holding a link for an account that was never saved.
            if (! $hasPassword && $invite) {
                DB::afterCommit(fn () => Password::sendResetLink(['email' => $user->email]));
            }

            return $user;
        });
    }

    /**
     * Provision a staff member: a login account plus its StaffProfile row (which shares
     * the user's id). Pass $role = null for a personnel-only record with no portal
     * login (role_type falls back to "Staff").
     *
     * @param  array<string, mixed>  $attributes  User columns (name, email, ...).
     * @param  array<string, mixed>  $profile     StaffProfile columns (staff_id_number, job_title, department_id, joined_date, phone). Defaults status to "Active".
     */
    public function provisionStaff(
        array $attributes,
        ?string $role,
        array $profile,
        ?User $actor = null,
        ?string $plainPassword = null,
        bool $invite = true,
        string $pendingStatus = 'Pending',
        ?string $auditAction = 'Created user',
    ): User {
        return DB::transaction(function () use ($attributes, $role, $profile, $actor, $plainPassword, $invite, $pendingStatus, $auditAction) {
            $user = $this->provisionAccount($attributes, $role, $actor, $plainPassword, $invite, $pendingStatus, $auditAction);

            StaffProfile::create(array_merge(['status' => 'Active'], $profile, [
                'id' => $user->id,
                'role_type' => $this->staffRoleType($role),
            ]));

            return $user;
        });
    }

    /**
     * Give an existing student record a portal login (Pending until the student sets a
     * password from the e-mailed link). The account, the link to the student record and
     * the audit entry are written in one transaction.
     */
    public function provisionStudentLogin(Student $student, string $email, User $actor): User
    {
        return DB::transaction(function () use ($student, $email, $actor) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($student->user_id !== null) {
                throw new AccountChangeRefused("{$student->name} already has a portal login.");
            }

            $user = $this->provisionAccount(
                ['name' => $student->name, 'email' => $email],
                'student',
                $actor,
                auditAction: 'Created student portal login',
            );

            $student->update(['user_id' => $user->id]);

            return $user;
        });
    }
}
