<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The three lesson-plan permissions, so staging gets them from `migrate --force` without running
 * the seeder. Insert-only and safe to re-run: findOrCreate never duplicates a permission, and a
 * grant is only added when the role does not have it yet.
 *
 * Self-contained on purpose (no app classes). Roles may not exist yet (fresh install before
 * seeding, or the test database): permissions are created regardless and only granted to roles
 * that exist. App\Support\LessonPlanPermissions::sync() (DatabaseSeeder, tests) does the same.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'lesson_plans.submit' => ['teacher'],
        'lesson_plans.review' => ['vp_academic'],
        'lesson_plans.view_all' => ['vp_academic', 'principal', 'admin'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission => $roles) {
            Permission::findOrCreate($permission, 'web');

            foreach (Role::where('guard_name', 'web')->whereIn('name', $roles)->get() as $role) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('guard_name', 'web')->whereIn('name', array_keys(self::PERMISSIONS))->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
