<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The lesson-plan permissions:
 *  - submit:   create plans, upload versions, edit and delete your own (Teacher),
 *  - review:   approve or ask for changes (VP),
 *  - view_all: read every plan and its files (VP, Principal, Admin — the last two read-only).
 *
 * Called by DatabaseSeeder and the test fixtures; the data migration creates the same rows on
 * its own (migrations must not depend on app classes).
 */
class LessonPlanPermissions
{
    public const SUBMIT = 'lesson_plans.submit';

    public const REVIEW = 'lesson_plans.review';

    public const VIEW_ALL = 'lesson_plans.view_all';

    public const GRANTS = [
        self::SUBMIT => ['teacher'],
        self::REVIEW => ['vp_academic'],
        self::VIEW_ALL => ['vp_academic', 'principal', 'admin'],
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
