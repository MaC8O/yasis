<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisions a real Admin (IT & Media Head) account outside of the demo seed data,
 * for bootstrapping the very first administrator on a fresh install (§3.6, Administration module).
 */
class CreateAdminAccount extends Command
{
    protected $signature = 'yasis:create-admin
        {name : Full name of the administrator}
        {email : Login email}
        {--staff-id= : Staff ID number (auto-generated if omitted)}
        {--password= : Password to set (a strong random one is generated and printed if omitted)}';

    protected $description = 'Create a real, active Admin (IT & Media Head) account';

    public function handle(AuditService $audit): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists. Use the Admin > User Management screen to edit it instead.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(16);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'Active',
        ]);
        $user->assignRole('admin');

        $department = Department::where('name', 'IT & Media')->first();

        StaffProfile::create([
            'id' => $user->id,
            'staff_id_number' => $this->option('staff-id') ?: 'ADM-'.Str::upper(Str::random(5)),
            'role_type' => 'Admin',
            'job_title' => 'IT & Media Head',
            'department_id' => $department?->id,
            'status' => 'Active',
            'joined_date' => now(),
        ]);

        $audit->log($user, 'Admin account provisioned via console', 'User', $user->id);

        $this->newLine();
        $this->info('Admin account created.');
        $this->table(['Field', 'Value'], [
            ['Name', $name],
            ['Email', $email],
            ['Password', $password],
        ]);
        $this->warn('This password is shown only once — sign in and change it immediately (Forgot password on the login page works too).');

        return self::SUCCESS;
    }
}
