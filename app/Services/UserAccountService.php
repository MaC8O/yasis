<?php

namespace App\Services;

use App\Exceptions\AccountChangeRefused;
use App\Models\User;
use App\Support\SecurityPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Changes to existing accounts: profile/role edits and activation status. Account creation
 * lives in {@see UserProvisioningService}; this is its counterpart for everything after.
 *
 * Every change runs in a single transaction that locks the rows it decides on, checks the
 * rules, writes the change and the audit entry — so the audit trail can never disagree with
 * the data, and two admins acting at once cannot slip past the last-admin rule together.
 *
 * Accounts are never deleted here: deactivation sets the status to Inactive, which blocks
 * sign-in ({@see \App\Http\Controllers\Auth\AuthController}) and ends live sessions on their
 * next request ({@see \App\Http\Middleware\EnsureAccountIsActive}).
 */
class UserAccountService
{
    public function __construct(
        private AuditService $audit,
        private UserProvisioningService $provisioning,
    ) {}

    /**
     * Roles whose accounts $actor may edit or (de)activate. Admin manages everyone; the
     * Registrar manages guardian and student accounts only.
     *
     * @return list<string>
     */
    public function manageableRoles(User $actor): array
    {
        return match (true) {
            $actor->hasRole('admin') => Role::pluck('name')->all(),
            $actor->hasRole('registrar') => $this->provisioning->registrarAssignableRoles(),
            default => [],
        };
    }

    /**
     * A user may be managed only when every role they hold is in the actor's scope. Checking
     * all roles (not just the first) matters: a teacher who is also a parent holds both
     * teacher and guardian, and the Registrar must not be able to lock out that teacher.
     * Accounts with no role at all are Admin-only.
     */
    public function canManage(User $actor, User $target): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        $roles = $target->getRoleNames()->all();

        return $roles !== [] && array_diff($roles, $this->manageableRoles($actor)) === [];
    }

    /** @throws AuthorizationException */
    public function assertCanManage(User $actor, User $target): void
    {
        if (! $this->canManage($actor, $target)) {
            throw new AuthorizationException('You are not allowed to change this account.');
        }
    }

    /**
     * Edit profile fields and the role. The audit entry records each changed field from → to.
     *
     * @param  array{name: string, email: string, role: string, date_of_birth?: ?string, gender?: ?string, phone?: ?string, address?: ?string}  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $this->assertCanManage($actor, $user);

        if (! in_array($data['role'], $this->manageableRoles($actor), true)) {
            throw new AuthorizationException("You are not allowed to assign the {$data['role']} role.");
        }

        return DB::transaction(function () use ($actor, $user, $data) {
            $user = $this->lockTarget($user);
            $fromRole = $user->getRoleNames()->first();
            $losesAdmin = $user->hasRole('admin') && $data['role'] !== 'admin';

            if ($losesAdmin && $actor->is($user)) {
                throw new AccountChangeRefused('You cannot remove your own Admin role. Ask another Admin to do it.');
            }
            if ($losesAdmin) {
                $this->refuseIfLastActiveAdmin($user, 'change the role of');
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            $changes = collect($user->getDirty())
                ->mapWithKeys(fn ($to, $field) => [$field => ['from' => $this->plain($user->getOriginal($field)), 'to' => $this->plain($to)]])
                ->all();

            $user->save();

            if ($fromRole !== $data['role']) {
                $user->syncRoles([$data['role']]);
                $changes['role'] = ['from' => $fromRole, 'to' => $data['role']];

                // Keep the HR record's role type in step for staff who move between staff roles.
                if ($user->staffProfile && in_array($data['role'], $this->provisioning->staffRoles(), true)) {
                    $user->staffProfile->update(['role_type' => $this->provisioning->staffRoleType($data['role'])]);
                }
            }

            $this->audit->log($actor, 'Edited user', 'User', $user->id, $changes ?: null);

            return $user;
        });
    }

    /** Set the account to Inactive: it can no longer sign in, and any open session ends. */
    public function deactivate(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        if ($actor->is($user)) {
            // The pages never show Deactivate on your own account.
            throw AccountChangeRefused::unexpected('account.deactivate_self', 'You cannot deactivate your own account. Ask another Admin to do it.');
        }

        return DB::transaction(function () use ($actor, $user) {
            $user = $this->lockTarget($user);

            if ($user->status === 'Inactive') {
                return $user;
            }

            $this->refuseIfLastActiveAdmin($user, 'deactivate');

            $from = $user->status;
            // A new remember token voids any "remember me" cookie the user still holds.
            $user->forceFill(['status' => 'Inactive', 'remember_token' => Str::random(60)])->save();

            $this->audit->log($actor, 'Deactivated user', 'User', $user->id, [
                'status' => ['from' => $from, 'to' => 'Inactive'],
            ]);

            return $user;
        });
    }

    /**
     * Bring an Inactive account back. With a password someone knows (password_set_at) it returns
     * to Active. Without one (it was deactivated before setup was finished) it returns to
     * Pending and the setup link is e-mailed again, because an Active account holding only the
     * random placeholder password would look usable while nobody could sign in.
     *
     * Erased accounts and HR staff records without portal access are refused
     * ({@see reactivationBlocker()}).
     */
    public function reactivate(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        return DB::transaction(function () use ($actor, $user) {
            $user = $this->lockTarget($user);
            $this->refuseWithoutPortalAccess($user, 'reactivate', 'reactivate');

            if ($user->status === 'Pending') {
                throw new AccountChangeRefused("{$user->name} hasn't finished setting up their account yet, so there is nothing to reactivate. Use “Activate” to give them a temporary password, or “Resend invite” to e-mail the setup link again.");
            }

            if ($user->status === 'Active') {
                return $user;
            }

            $to = $user->hasKnownPassword() ? 'Active' : 'Pending';
            $user->forceFill(['status' => $to])->save();

            $details = ['status' => ['from' => 'Inactive', 'to' => $to]];
            if ($to === 'Pending') {
                $details['setup_link_sent'] = true;
                $this->sendSetupLinkAfterCommit($user);
            }

            $this->audit->log($actor, 'Reactivated user', 'User', $user->id, $details);

            return $user;
        });
    }

    /**
     * Activate a Pending account with a temporary password, returned so the caller can show it
     * once. The account must change it at first sign-in. The password is never written anywhere
     * in plain text: only its hash is saved, and the audit entry records that one was issued, not
     * what it is. The unused setup link is withdrawn so it cannot be used afterwards.
     *
     * A second call on the same account is refused, because it is no longer Pending.
     */
    public function activate(User $actor, User $user): string
    {
        $this->assertCanManage($actor, $user);

        return DB::transaction(function () use ($actor, $user) {
            $user = $this->lockTarget($user);
            $this->refuseWithoutPortalAccess($user, 'activate', 'activate');

            if ($user->status !== 'Pending') {
                throw new AccountChangeRefused("{$user->name}'s account is {$user->status}, not waiting for setup, so it can't be activated with a temporary password.");
            }

            $temporary = $this->temporaryPassword();

            $user->forceFill([
                'password' => Hash::make($temporary),
                'password_set_at' => now(),
                'status' => 'Active',
                'must_reset_password' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))->where('email', $user->email)->delete();

            $this->audit->log($actor, 'Activated user', 'User', $user->id, [
                'status' => ['from' => 'Pending', 'to' => 'Active'],
                'temporary_password_issued' => true,
                'must_reset_password' => true,
            ]);

            return $temporary;
        });
    }

    /** E-mail the account-setup link again to a Pending account. */
    public function resendInvite(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        return DB::transaction(function () use ($actor, $user) {
            $user = $this->lockTarget($user);
            $this->refuseWithoutPortalAccess($user, 'send a setup link to', 'resend_invite');

            if ($user->status !== 'Pending') {
                throw new AccountChangeRefused("{$user->name}'s account is {$user->status}, not waiting for setup, so there is no setup link to resend.");
            }

            $this->sendSetupLinkAfterCommit($user);
            $this->audit->log($actor, 'Resent account-setup link', 'User', $user->id, ['status' => 'Pending']);

            return $user;
        });
    }

    /**
     * Why this account can never be reactivated or activated, as a short label for the screens,
     * or null when it can. The server enforces the same rule ({@see refuseWithoutPortalAccess()}).
     */
    public function reactivationBlocker(User $user): ?string
    {
        return match (true) {
            $user->isErased() => 'Erased account',
            ! $user->hasPortalAccess() => 'No portal access',
            default => null,
        };
    }

    /**
     * The pages show a reason ("Erased account" / "No portal access") instead of these actions, so
     * reaching this takes a hand-made request (or, rarely, a page left open while the account was
     * erased or lost its role). Logged as unexpected: user ID and action key only, no names.
     *
     * @param  string  $action  reactivate | activate | resend_invite
     */
    private function refuseWithoutPortalAccess(User $user, string $verb, string $action): void
    {
        if ($user->isErased()) {
            throw AccountChangeRefused::unexpected("account.{$action}_erased",
                "You cannot {$verb} this account: it was erased under a data-erasure request, and bringing it back would undo that erasure.");
        }

        if (! $user->hasPortalAccess()) {
            throw AccountChangeRefused::unexpected("account.{$action}_no_portal_access",
                "You cannot {$verb} {$user->name}: this is an HR staff record without portal access, not a login account. To give them portal access, edit the account to set a role and their real e-mail address first.");
        }
    }

    /** Only once the change has committed, so a rolled-back change never leaves a live link. */
    private function sendSetupLinkAfterCommit(User $user): void
    {
        $email = $user->email;
        DB::afterCommit(fn () => Password::sendResetLink(['email' => $email]));
    }

    /**
     * A temporary password that always meets the password policy: at least 12 characters (or the
     * policy minimum if longer), with lower case, upper case and a digit. Look-alike characters
     * (0/O, 1/l/I) are left out so it can be read out or copied by hand.
     */
    private function temporaryPassword(): string
    {
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $all = $lower.$upper.$digits;
        $pick = fn (string $set) => $set[random_int(0, strlen($set) - 1)];

        $chars = [$pick($lower), $pick($upper), $pick($digits)];
        $length = max(12, SecurityPolicy::passwordMinLength());
        while (count($chars) < $length) {
            $chars[] = $pick($all);
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /**
     * Lock the active-Admin rows first, then the target, always in that order. Two admins
     * deactivating each other at the same moment then queue behind one another, and the
     * second sees the first's change instead of both passing the last-admin count.
     */
    private function lockTarget(User $user): User
    {
        if ($user->hasRole('admin')) {
            $this->activeAdmins()->orderBy('users.id')->lockForUpdate()->pluck('users.id');
        }

        return User::whereKey($user->id)->lockForUpdate()->firstOrFail();
    }

    private function refuseIfLastActiveAdmin(User $user, string $verb): void
    {
        if (! $user->hasRole('admin') || $user->status !== 'Active') {
            return;
        }

        if (! $this->activeAdmins()->whereKeyNot($user->id)->exists()) {
            throw new AccountChangeRefused("You cannot {$verb} {$user->name}: they are the only active Admin. Make another account an active Admin first.");
        }
    }

    private function activeAdmins()
    {
        return User::role('admin')->where('users.status', 'Active');
    }

    /** Dates and other casts as plain strings, so the audit details stay readable JSON. */
    private function plain(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }
}
