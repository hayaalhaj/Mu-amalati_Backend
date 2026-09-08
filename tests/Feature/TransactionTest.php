<?php

namespace Tests\Feature;

use App\Enums\WorkflowStepStatus;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\TransactionTypeWorkflowStep;
use App\Models\TransactionWorkflowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up a ready transaction type with a workflow destination.
     */
    protected function makeTypeWithWorkflow(Department $department, bool $requiresAttachment = false): TransactionType
    {
        $type = TransactionType::factory()->create([
            'destination_department_id' => $department->id,
            'requires_attachment' => $requiresAttachment,
        ]);

        TransactionTypeWorkflowStep::factory()->create([
            'transaction_type_id' => $type->id,
            'department_id' => $department->id,
            'step_order' => 1,
            'name' => 'Direct Manager Review',
            'is_final' => true,
        ]);

        return $type;
    }

    /*
    |--------------------------------------------------------------------------
    | Creation
    |--------------------------------------------------------------------------
    */

    public function test_employee_can_create_a_draft_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/transactions', [
                'transaction_type_id' => $type->id,
                'title' => 'Purchase two new laptops',
                'description' => 'Laptops for the new team.',
                'priority' => 'medium',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('transactions', [
            'title' => 'Purchase two new laptops',
            'status' => 'draft',
        ]);
    }

    public function test_creator_is_taken_from_authentication_not_request_data(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $other = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/transactions', [
                'transaction_type_id' => $type->id,
                'title' => 'Test',
                'description' => 'Test',
                'priority' => 'low',
                'created_by' => $other->id,
                'status' => 'approved',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('transactions', [
            'title' => 'Test',
            'created_by' => $employee->id,
        ]);
    }

    public function test_source_department_is_taken_from_user_and_destination_from_type(): void
    {
        $sourceDept = Department::factory()->create();
        $destDept = Department::factory()->create();
        $employee = User::factory()->employee($sourceDept)->create();
        $type = $this->makeTypeWithWorkflow($destDept);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/transactions', [
                'transaction_type_id' => $type->id,
                'title' => 'Test',
                'description' => 'Test',
                'priority' => 'medium',
                'source_department_id' => 999,
                'destination_department_id' => 998,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.source_department.id', $sourceDept->id)
            ->assertJsonPath('data.destination_department.id', $destDept->id);
    }

    public function test_transaction_number_is_generated(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/transactions', [
                'transaction_type_id' => $type->id,
                'title' => 'Test',
                'description' => 'Test',
                'priority' => 'medium',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['transaction_number']]);

        $this->assertMatchesRegularExpression('/^TRX-\d{4}-\d{6}$/', $response->json('data.transaction_number'));
    }

    /*
    |--------------------------------------------------------------------------
    | Listing & Visibility
    |--------------------------------------------------------------------------
    */

    public function test_employee_can_view_only_their_transactions(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $other = User::factory()->employee($department)->create();

        $type = $this->makeTypeWithWorkflow($department);

        Transaction::factory()->count(2)->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        Transaction::factory()->count(3)->create([
            'created_by' => $other->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_employee_cannot_view_another_employees_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $other = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->create([
            'created_by' => $other->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson("/api/v1/transactions/{$transaction->id}");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Update & Delete
    |--------------------------------------------------------------------------
    */

    public function test_employee_can_update_their_draft_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->draft()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/transactions/{$transaction->id}", [
                'title' => 'Updated title',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    public function test_employee_can_update_their_returned_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->returned()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/transactions/{$transaction->id}", [
                'title' => 'Resubmitted title',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Resubmitted title');
    }

    public function test_employee_cannot_update_a_pending_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->pending()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/v1/transactions/{$transaction->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_employee_can_delete_a_draft_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->draft()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->deleteJson("/api/v1/transactions/{$transaction->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_employee_cannot_delete_a_submitted_transaction(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->pending()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->deleteJson("/api/v1/transactions/{$transaction->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Submission
    |--------------------------------------------------------------------------
    */

    public function test_submission_creates_workflow_snapshots(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->draft()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('transaction_workflow_steps', [
            'transaction_id' => $transaction->id,
            'status' => WorkflowStepStatus::Pending->value,
        ]);
    }

    public function test_submission_requires_workflow_configuration(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = TransactionType::factory()->create(['destination_department_id' => $department->id]);

        $transaction = Transaction::factory()->draft()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/submit");

        $response->assertStatus(409);
    }

    public function test_submission_requires_attachments_for_types_that_require_them(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department, true);

        $transaction = Transaction::factory()->draft()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/submit");

        $response->assertStatus(409);
    }

    public function test_resubmission_resumes_from_returned_step(): void
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee($department)->create();
        $type = $this->makeTypeWithWorkflow($department);

        $transaction = Transaction::factory()->returned()->create([
            'created_by' => $employee->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
        ]);

        $returnedStep = TransactionWorkflowStep::factory()->create([
            'transaction_id' => $transaction->id,
            'department_id' => $department->id,
            'status' => WorkflowStepStatus::Returned,
            'step_order' => 1,
        ]);

        $transaction->update([
            'current_workflow_step_id' => $returnedStep->id,
            'current_department_id' => $department->id,
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->postJson("/api/v1/transactions/{$transaction->id}/resubmit");

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('transaction_workflow_steps', [
            'id' => $returnedStep->id,
            'status' => WorkflowStepStatus::Pending->value,
        ]);
    }
}
