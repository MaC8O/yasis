<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super-administrator'],
            ['name' => 'accountant'],
            ['name' => 'teacher'],
            ['name' => 'student'],
            ['name' => 'parent'],
            // Optionally, a general 'staff' role
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}