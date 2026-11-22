<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The behavior-policy permissions — the app's first Spatie permissions. The thresholds are
 * writable only with `behavior.policy.manage` (VP), never through the Admin technical settings or
 * the Principal governance controls. The Principal may view the policy page.
 *
 * Called by DatabaseSeeder and the test fixtures; the data migration creates the same rows on
 * its own (migrations must not depend on app classes).
 */
class BehaviorPermissions
{
    public const VIEW = 'behavior.policy.view';

    public const MANAGE = 'behavior.policy.manage';

    public const GRANTS = [
        self::VIEW => ['vp_academic', 'principal'],
        self::MANAGE => ['vp_academic'],
    ];

    /** Create the permissions and give them to whichever of their roles exist. Safe to re-run. */
    public static function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::GRANTS as $permission => $roles) {
            Permission::findOrCreate($permission, 'web');

            foreach (Role::where('guard_name', 'web')->whereIn('name', $roles)->get() as $role) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
