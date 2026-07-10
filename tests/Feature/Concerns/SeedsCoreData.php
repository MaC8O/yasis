<?php

namespace Tests\Feature\Concerns;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\GradeScaleBand;
use App\Models\LeaveType;
use App\Models\StaffProfile;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Minimal, fast fixtures for feature tests — deliberately smaller than DatabaseSeeder,
 * which is tuned for demoing the app rather than for test speed.
 */
trait SeedsCoreData
{
    protected function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'principal', 'vp_academic', 'registrar', 'teacher', 'treasurer', 'hr_office', 'guardian', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    protected function makeStaff(string $role, string $roleType, string $email = null, array $overrides = []): StaffProfile
    {
        $user = User::create([
            'name' => ucfirst($role).' Test User',
            'email' => $email ?? "{$role}@test.local",
            'password' => Hash::make('password'),
            'status' => 'Active',
        ]);
        $user->assignRole($role);

        return StaffProfile::create(array_merge([
            'id' => $user->id,
            'staff_id_number' => 'T-'.$user->id,
            'role_type' => $roleType,
            'job_title' => ucfirst($role),
            'department_id' => null,
            'status' => 'Active',
            'joined_date' => now()->subYear(),
            'phone' => null,
        ], $overrides));
    }

    protected function seedAcademicCalendar(): Term
    {
        $year = AcademicYear::create(['year_label' => now()->year.'-'.(now()->year + 1), 'is_active' => true]);

        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Term 1',
            'sequence' => 1,
            'start_date' => now()->subWeeks(2),
            'end_date' => now()->addWeeks(7),
        ]);

        return $term;
    }

    protected function seedDepartment(string $name = 'High School'): Department
    {
        $department = Department::create(['name' => $name, 'level' => 'Secondary']);

        foreach ([['A', 90, 4.0], ['B', 80, 3.0], ['C', 70, 2.0], ['D', 60, 1.0], ['F', 0, 0.0]] as [$letter, $min, $gpa]) {
            GradeScaleBand::create(['department_id' => $department->id, 'letter' => $letter, 'min_score' => $min, 'gpa_point' => $gpa]);
        }

        return $department;
    }

    protected function seedLeaveTypes(): void
    {
        foreach ([['Annual', true], ['Sick', true], ['Unpaid', false]] as [$name, $isPaid]) {
            LeaveType::firstOrCreate(['name' => $name], ['is_paid' => $isPaid]);
        }
    }
}
