<?php

namespace App\Services;

use App\Exceptions\AccountChangeRefused;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
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
            throw new AccountChangeRefused('You cannot deactivate your own account. Ask another Admin to do it.');
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

    /** Return an Inactive account to Active. */
    public function reactivate(User $actor, User $user): User
    {
        $this->assertCanManage($actor, $user);

        return DB::transaction(function () use ($actor, $user) {
            $user = $this->lockTarget($user);

            // A Pending account has never set a password: making it Active would not let anyone in,
            // it would only hide that the invite was never completed. Re-send the invite instead.
            if ($user->status === 'Pending') {
                throw new AccountChangeRefused("{$user->name} hasn't finished setting up their account yet, so there is nothing to reactivate. Use “Resend invite” to e-mail the setup link again.");
            }

            if ($user->status === 'Active') {
                return $user;
            }

            $from = $user->status;
            $user->forceFill(['status' => 'Active'])->save();

            $this->audit->log($actor, 'Reactivated user', 'User', $user->id, [
                'status' => ['from' => $from, 'to' => 'Active'],
            ]);

            return $user;
        });
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
