<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::firstOrCreate(
            ['code' => 'CSC'],
            ['name' => 'Computer Science']
        );

        $session = AcademicSession::firstOrCreate(
            ['label' => '2025/2026'],
            [
                'is_active' => true,
                'start_date' => '2025-09-01',
                'end_date' => '2026-07-31',
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@aaua.edu.ng'],
            [
                'full_name' => 'System Administrator',
                'password' => Hash::make('AdminPass2026!'),
                'role' => 'admin',
                'department_id' => $department->department_id,
                'session_id' => $session->session_id,
            ]
        );

        $this->command->info('Admin account ready: admin@aaua.edu.ng / AdminPass2026!');
    }
}