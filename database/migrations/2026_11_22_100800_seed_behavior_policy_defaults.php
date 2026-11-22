<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data rows the behavior feature needs on every environment, so staging gets them from
 * `migrate --force` without running the seeder:
 *  - default incident categories (the VP edits them afterwards),
 *  - the three warning thresholds in system_settings (never overwritten if already present),
 *  - the two behavior-policy permissions.
 *
 * Self-contained on purpose (no app classes), so later refactors cannot break an old migration.
 * Roles may not exist yet (fresh install before seeding, or the test database): permissions are
 * created regardless and only assigned to roles that exist. DatabaseSeeder assigns them as well.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        ['Classroom disruption', 'Repeatedly interrupting teaching or other students.'],
        ['Disrespect toward staff or students', 'Rude, defiant or insulting language or behavior.'],
        ['Lateness or skipping class', 'Arriving late without reason or missing class without permission.'],
        ['Dishonesty or academic misconduct', 'Lying, cheating, plagiarism or forging signatures.'],
        ['Bullying or harassment', 'Repeated intimidation, exclusion or harassment, in person or online.'],
        ['Fighting or physical aggression', 'Hitting, pushing or threatening physical harm.'],
        ['Damage to property', 'Damaging school or other people\'s property.'],
        ['Dress code', 'Not following the school uniform or dress code.'],
        ['Phone or device misuse', 'Using a phone or device when not allowed.'],
        ['Other', 'Anything not covered above — describe it in the report.'],
    ];

    private const SETTINGS = [
        'behavior_threshold_warning' => '80',
        'behavior_threshold_final_warning' => '60',
        'behavior_threshold_expulsion_review' => '40',
    ];

    private const PERMISSIONS = [
        'behavior.policy.view' => ['vp_academic', 'principal'],
        'behavior.policy.manage' => ['vp_academic'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::CATEGORIES as $i => [$name, $description]) {
            DB::table('behavior_categories')->insertOrIgnore([
                'name' => $name, 'description' => $description, 'is_active' => true,
                'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (self::SETTINGS as $key => $value) {
            if (! DB::table('system_settings')->where('key', $key)->exists()) {
                DB::table('system_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission => $roles) {
            Permission::findOrCreate($permission, 'web');

            foreach (Role::where('guard_name', 'web')->whereIn('name', $roles)->get() as $role) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('guard_name', 'web')->whereIn('name', array_keys(self::PERMISSIONS))->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('system_settings')->whereIn('key', array_keys(self::SETTINGS))->delete();
        // behavior_categories is dropped by its own migration's down().
    }
};
