<?php

namespace Tests\Feature\Concerns;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Guardian and student portal accounts for user-management tests. Needs {@see SeedsCoreData}. */
trait MakesPortalAccounts
{
    protected function makeAccount(string|array $roles, string $email, string $status = 'Active'): User
    {
        $user = User::create([
            'name' => ucfirst(strtok($email, '@')).' Person',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => $status,
        ]);
        $user->assignRole((array) $roles);

        return $user;
    }

    protected function makeGuardian(string $email = 'guardian@test.local', string $status = 'Active'): Guardian
    {
        $user = $this->makeAccount('guardian', $email, $status);

        return Guardian::create(['user_id' => $user->id, 'relationship' => 'Mother', 'phone' => '0912']);
    }

    protected function makeStudent(?User $user = null): Student
    {
        return Student::create([
            'user_id' => $user?->id,
            'student_id_number' => 'S-'.fake()->unique()->numberBetween(1000, 99999),
            'name' => 'Aye Min',
            'date_of_birth' => now()->subYears(14),
            'gender' => 'F',
            'department_id' => $this->seedDepartment('High School '.fake()->unique()->numberBetween(1, 9999))->id,
            'enrollment_status' => 'Enrolled',
            'admission_date' => now()->subYear(),
        ]);
    }
}
