<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Seed the default departments.
     */
    public function run(): void
    {
        $departments = [
            'Human Resources' => 'Responsible for employee affairs and complaints.',
            'Finance' => 'Responsible for payments and advances.',
            'Information Technology' => 'Responsible for IT support and system accounts.',
            'Procurement' => 'Responsible for company purchasing.',
            'Operations' => 'Responsible for daily operations.',
            'General Management' => 'Company general management.',
        ];

        foreach ($departments as $name => $description) {
            Department::updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true]
            );
        }
    }
}
