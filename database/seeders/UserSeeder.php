<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the demo users for local development only.
     */
    public function run(): void
    {
        $hr = Department::where('name', 'Human Resources')->first();
        $finance = Department::where('name', 'Finance')->first();
        $it = Department::where('name', 'Information Technology')->first();
        $procurement = Department::where('name', 'Procurement')->first();
        $operations = Department::where('name', 'Operations')->first();

        // Admin
        User::updateOrCreate(
            ['email' => 'maya.admin@company.test'],
            [
                'name' => 'Maya Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'department_id' => null,
            ]
        );

        // Managers
        $managers = [
            [
                'email' => 'sara.hr@company.test',
                'name' => 'Sara Khaled',
                'department' => $hr,
            ],
            [
                'email' => 'omar.finance@company.test',
                'name' => 'Omar Hassan',
                'department' => $finance,
            ],
            [
                'email' => 'lina.it@company.test',
                'name' => 'Lina Ali',
                'department' => $it,
            ],
            [
                'email' => 'rania.procurement@company.test',
                'name' => 'Rania Ahmad',
                'department' => $procurement,
            ],
            [
                'email' => 'yousef.operations@company.test',
                'name' => 'Yousef Sami',
                'department' => $operations,
            ],
        ];

        foreach ($managers as $m) {
            $manager = User::updateOrCreate(
                ['email' => $m['email']],
                [
                    'name' => $m['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Manager,
                    'status' => UserStatus::Active,
                    'department_id' => $m['department']?->id,
                ]
            );

            if ($m['department']) {
                $m['department']->update([
                    'manager_id' => $manager->id,
                ]);
            }
        }

        // Employees
        $employees = [
            [
                'email' => 'haya@company.test',
                'name' => 'Haya Ahmad',
                'department' => $hr,
                'job_title' => 'HR Specialist',
            ],
            [
                'email' => 'rana.hr@company.test',
                'name' => 'Rana Ali',
                'department' => $hr,
                'job_title' => 'HR Coordinator',
            ],
            [
                'email' => 'khaled.finance@company.test',
                'name' => 'Khaled Hassan',
                'department' => $finance,
                'job_title' => 'Accountant',
            ],
            [
                'email' => 'noor.it@company.test',
                'name' => 'Noor Ibrahim',
                'department' => $it,
                'job_title' => 'Support Engineer',
            ],
        ];

        foreach ($employees as $e) {
            User::updateOrCreate(
                ['email' => $e['email']],
                [
                    'name' => $e['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Employee,
                    'status' => UserStatus::Active,
                    'department_id' => $e['department']?->id,
                    'job_title' => $e['job_title'],
                ]
            );
        }
    }
}