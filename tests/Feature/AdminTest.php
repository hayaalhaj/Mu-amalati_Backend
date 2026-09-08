<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\TransactionTypeWorkflowStep;
use App\Models\TransactionWorkflowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Admin User Management
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/users', [
                'name' => 'Ahmad Mohammad',
                'email' => 'ahmad@company.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'employee',
                'department_id' => $department->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'employee');

        $this->assertDatabaseHas('users', [
            'email' => 'ahmad@company.test',
            'role' => 'employee',
        ]);
    }

    public function test_non_admin_cannot_create_a_user(): void
    {
        $employee = User::factory()->employee()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/admin/users', [
                'name' => 'Hacker',
                'email' => 'hacker@company.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'employee',
                'department_id' => $department->id,
                'status' => 'active',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'hacker@company.test']);
    }

    public function test_admin_can_activate_and_deactivate_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->employee()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$user->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$user->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_admin_cannot_assign_employee_as_department_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/departments', [
                'name' => 'Procurement',
                'manager_id' => $employee->id,
            ]);

        $response->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_manage_departments(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/departments', [
                'name' => 'Human Resources',
                'description' => 'Handles people operations.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Human Resources');

        $department = Department::where('name', 'Human Resources')->first();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/departments/{$department->id}", [
                'description' => 'Updated description.',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated description.');
    }

    public function test_referenced_department_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();
        User::factory()->employee($department)->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/departments/{$department->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Types
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_manage_transaction_types(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/transaction-types', [
                'name_en' => 'Leave Request',
                'name_ar' => 'طلب إجازة',
                'destination_department_id' => $department->id,
                'requires_attachment' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name_en', 'Leave Request');

        $this->assertDatabaseHas('transaction_types', ['name_en' => 'Leave Request']);
    }

    public function test_inactive_transaction_type_cannot_be_submitted(): void
    {
        $employee = User::factory()->employee()->create();
        $type = TransactionType::factory()->inactive()->create();

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/transactions', [
                'transaction_type_id' => $type->id,
                'title' => 'Test transaction',
                'description' => 'Test description',
                'priority' => 'medium',
            ]);

        $response->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Workflow Configuration
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_configure_workflow_steps(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TransactionType::factory()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/transaction-types/{$type->id}/workflow-steps", [
                'department_id' => $department->id,
                'name' => 'Direct Manager Review',
                'step_order' => 1,
                'is_final' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Direct Manager Review');

        $this->assertDatabaseHas('transaction_type_workflow_steps', [
            'transaction_type_id' => $type->id,
            'name' => 'Direct Manager Review',
        ]);
    }

    public function test_non_admin_cannot_configure_workflow_steps(): void
    {
        $employee = User::factory()->employee()->create();
        $type = TransactionType::factory()->create();
        $department = Department::factory()->create();

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/admin/transaction-types/{$type->id}/workflow-steps", [
                'department_id' => $department->id,
                'name' => 'Review',
                'step_order' => 1,
            ]);

        $response->assertForbidden();
    }

    public function test_workflow_step_referenced_by_transaction_cannot_be_edited(): void
    {
        $admin = User::factory()->admin()->create();
        $type = TransactionType::factory()->create();
        $department = Department::factory()->create();

        $step = TransactionTypeWorkflowStep::factory()->create([
            'transaction_type_id' => $type->id,
            'department_id' => $department->id,
        ]);

        // Simulate a snapshot referencing this step.
        $transaction = Transaction::factory()->create([
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        TransactionWorkflowStep::factory()->create([
            'transaction_id' => $transaction->id,
            'department_id' => $department->id,
            'original_workflow_step_id' => $step->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/workflow-steps/{$step->id}", [
                'name' => 'Changed',
            ]);

        $response->assertStatus(409);
    }
}
