<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\TransactionType;
use App\Models\TransactionTypeWorkflowStep;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Seed the transaction types and their default workflows.
     */
    public function run(): void
    {
        $hr = Department::where('name', 'Human Resources')->first();
        $finance = Department::where('name', 'Finance')->first();
        $it = Department::where('name', 'Information Technology')->first();
        $procurement = Department::where('name', 'Procurement')->first();
        $operations = Department::where('name', 'Operations')->first();

        $types = [
            [
                'name_en' => 'Leave Request',
                'name_ar' => 'طلب إجازة',
                'description' => 'Request for annual or unpaid leave.',
                'destination_department_id' => $hr?->id,
                'requires_attachment' => false,
                'steps' => [
                    [
                        'name' => 'Direct Manager Review',
                        'department' => $operations,
                    ],
                    [
                        'name' => 'Human Resources Review',
                        'department' => $hr,
                        'is_final' => true,
                    ],
                ],
            ],

            [
                'name_en' => 'Purchase Request',
                'name_ar' => 'طلب شراء',
                'description' => 'Request to purchase company equipment or supplies.',
                'destination_department_id' => $procurement?->id,
                'requires_attachment' => true,
                'steps' => [
                    [
                        'name' => 'Direct Manager Review',
                        'department' => $operations,
                    ],
                    [
                        'name' => 'Procurement Review',
                        'department' => $procurement,
                    ],
                    [
                        'name' => 'Finance Review',
                        'department' => $finance,
                        'is_final' => true,
                    ],
                ],
            ],

            [
                'name_en' => 'IT Support Request',
                'name_ar' => 'طلب دعم تقني',
                'description' => 'Request for IT support or hardware/software assistance.',
                'destination_department_id' => $it?->id,
                'requires_attachment' => false,
                'steps' => [
                    [
                        'name' => 'Information Technology Review',
                        'department' => $it,
                        'is_final' => true,
                    ],
                ],
            ],

            [
                'name_en' => 'Advance Payment Request',
                'name_ar' => 'طلب سلفة مالية',
                'description' => 'Request for an advance on salary.',
                'destination_department_id' => $finance?->id,
                'requires_attachment' => false,
                'steps' => [
                    [
                        'name' => 'Direct Manager Review',
                        'department' => $hr,
                    ],
                    [
                        'name' => 'Finance Review',
                        'department' => $finance,
                        'is_final' => true,
                    ],
                ],
            ],

            [
                'name_en' => 'Account Request',
                'name_ar' => 'طلب إنشاء حساب',
                'description' => 'Request to create or modify a system account.',
                'destination_department_id' => $it?->id,
                'requires_attachment' => false,
                'steps' => [
                    [
                        'name' => 'Direct Manager Review',
                        'department' => $operations,
                    ],
                    [
                        'name' => 'Information Technology Review',
                        'department' => $it,
                        'is_final' => true,
                    ],
                ],
            ],

            [
                'name_en' => 'Complaint / Suggestion',
                'name_ar' => 'شكوى أو اقتراح',
                'description' => 'Submit a complaint or suggestion to Human Resources.',
                'destination_department_id' => $hr?->id,
                'requires_attachment' => false,
                'steps' => [
                    [
                        'name' => 'Human Resources Review',
                        'department' => $hr,
                        'is_final' => true,
                    ],
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $steps = $typeData['steps'];

            unset($typeData['steps']);

            $type = TransactionType::updateOrCreate(
                [
                    'name_en' => $typeData['name_en'],
                ],
                $typeData
            );

            foreach ($steps as $index => $step) {
                TransactionTypeWorkflowStep::updateOrCreate(
                    [
                        'transaction_type_id' => $type->id,
                        'step_order' => $index + 1,
                    ],
                    [
                        'name' => $step['name'],
                        'department_id' => $step['department']?->id,
                        'is_final' => $step['is_final'] ?? false,
                    ]
                );
            }
        }
    }
}