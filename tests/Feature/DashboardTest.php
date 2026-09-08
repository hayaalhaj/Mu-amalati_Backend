<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_dashboard_returns_only_employee_data(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create([
            'annual_leave_days' => 30,
            'used_leave_days' => 10,
        ]);
        $other = User::factory()->employee($department)->create();

        $type = TransactionType::factory()->create(['destination_department_id' => $department->id]);

        Transaction::factory()->count(2)->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        Transaction::factory()->count(5)->create([
            'created_by' => $other->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/dashboard/employee');

        $response->assertOk()
            ->assertJsonPath('data.transaction_counts.total', 2)
            ->assertJsonPath('data.leave_balance.remaining_leave_days', 20)
            ->assertJsonPath('data.user.id', $employee->id);
    }

    public function test_manager_dashboard_returns_only_authorized_pending_transactions(): void
    {
        $department = Department::factory()->create();
        $manager = User::factory()->manager($department)->create();
        $department->update(['manager_id' => $manager->id]);

        $otherDept = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();

        $type = TransactionType::factory()->create(['destination_department_id' => $department->id]);

        Transaction::factory()->pending()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
            'current_department_id' => $department->id,
        ]);

        $type2 = TransactionType::factory()->create(['destination_department_id' => $otherDept->id]);
        $otherEmployee = User::factory()->employee($otherDept)->create();

        Transaction::factory()->pending()->create([
            'created_by' => $otherEmployee->id,
            'transaction_type_id' => $type2->id,
            'source_department_id' => $otherDept->id,
            'destination_department_id' => $otherDept->id,
            'current_department_id' => $otherDept->id,
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/dashboard/manager');

        $response->assertOk()
            ->assertJsonPath('data.pending_approval_count', 1);
    }

    public function test_admin_dashboard_values_are_calculated_from_the_database(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $manager = User::factory()->manager($department)->create();

        $type = TransactionType::factory()->create(['destination_department_id' => $department->id]);

        Transaction::factory()->count(3)->approved()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        Transaction::factory()->count(2)->pending()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/dashboard/admin');

        $response->assertOk()
            ->assertJsonPath('data.total_employees', 1)
            ->assertJsonPath('data.total_managers', 1)
            ->assertJsonPath('data.total_transactions', 5)
            ->assertJsonPath('data.transactions_by_status.approved', 3)
            ->assertJsonPath('data.transactions_by_status.pending', 2);
    }
}
