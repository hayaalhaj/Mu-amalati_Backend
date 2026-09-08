<?php

namespace Tests\Feature;

use App\Enums\TransactionHistoryAction;
use App\Enums\TransactionStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\TransactionTypeWorkflowStep;
use App\Models\TransactionWorkflowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up a manager, employee, and a pending transaction
     * on the manager's department.
     */
    protected function makePendingTransaction(array $options = []): array
    {
        $department = Department::factory()->create();

        $manager = User::factory()
            ->manager($department)
            ->create();

        $department->update([
            'manager_id' => $manager->id,
        ]);

        // Manager must be checked in and working
        // because transaction review requires attendance.
        Attendance::create([
            'user_id' => $manager->id,
            'date' => now()->toDateString(),
            'check_in_at' => now(),
            'check_out_at' => null,
        ]);

        $employee = User::factory()
            ->employee($department)
            ->create();

        $type = TransactionType::factory()->create([
            'destination_department_id' => $department->id,
        ]);

        TransactionTypeWorkflowStep::factory()->create([
            'transaction_type_id' => $type->id,
            'department_id' => $department->id,
            'step_order' => 1,
            'name' => 'Direct Manager Review',
            'is_final' => true,
        ]);

        $transaction = Transaction::factory()
            ->pending()
            ->create([
                'created_by' => $employee->id,
                'transaction_type_id' => $type->id,
                'source_department_id' => $department->id,
                'destination_department_id' => $department->id,
                'current_department_id' => $department->id,
            ]);

        $step = TransactionWorkflowStep::factory()
            ->pending()
            ->create([
                'transaction_id' => $transaction->id,
                'department_id' => $department->id,
                'step_order' => 1,
                'name' => 'Direct Manager Review',
            ]);

        $transaction->update([
            'current_workflow_step_id' => $step->id,
        ]);

        return compact(
            'department',
            'manager',
            'employee',
            'type',
            'transaction',
            'step'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approval
    |--------------------------------------------------------------------------
    */

    public function test_authorized_manager_can_approve_the_current_step(): void
    {
        $ctx = $this->makePendingTransaction();

        $response = $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas(
            'transaction_workflow_steps',
            [
                'id' => $ctx['step']->id,
                'status' => WorkflowStepStatus::Approved->value,
            ]
        );
    }

    public function test_approval_activates_the_next_step(): void
    {
        $department = Department::factory()->create();

        $manager = User::factory()
            ->manager($department)
            ->create();

        $department->update([
            'manager_id' => $manager->id,
        ]);

        // Manager must be checked in and working
        // to approve the transaction.
        Attendance::create([
            'user_id' => $manager->id,
            'date' => now()->toDateString(),
            'check_in_at' => now(),
            'check_out_at' => null,
        ]);

        $employee = User::factory()
            ->employee($department)
            ->create();

        $type = TransactionType::factory()->create([
            'destination_department_id' => $department->id,
        ]);

        TransactionTypeWorkflowStep::factory()->create([
            'transaction_type_id' => $type->id,
            'department_id' => $department->id,
            'step_order' => 1,
            'name' => 'Manager Review',
        ]);

        TransactionTypeWorkflowStep::factory()->create([
            'transaction_type_id' => $type->id,
            'department_id' => $department->id,
            'step_order' => 2,
            'name' => 'Finance Review',
            'is_final' => true,
        ]);

        $transaction = Transaction::factory()
            ->pending()
            ->create([
                'created_by' => $employee->id,
                'transaction_type_id' => $type->id,
                'source_department_id' => $department->id,
                'destination_department_id' => $department->id,
                'current_department_id' => $department->id,
            ]);

        $step1 = TransactionWorkflowStep::factory()
            ->pending()
            ->create([
                'transaction_id' => $transaction->id,
                'department_id' => $department->id,
                'step_order' => 1,
                'name' => 'Manager Review',
            ]);

        $step2 = TransactionWorkflowStep::factory()
            ->create([
                'transaction_id' => $transaction->id,
                'department_id' => $department->id,
                'step_order' => 2,
                'name' => 'Finance Review',
                'status' => WorkflowStepStatus::Waiting,
            ]);

        $transaction->update([
            'current_workflow_step_id' => $step1->id,
        ]);

        $response = $this->actingAs(
            $manager,
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$transaction->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        );

        $response->assertOk();

        $this->assertDatabaseHas(
            'transaction_workflow_steps',
            [
                'id' => $step1->id,
                'status' => WorkflowStepStatus::Approved->value,
            ]
        );

        $this->assertDatabaseHas(
            'transaction_workflow_steps',
            [
                'id' => $step2->id,
                'status' => WorkflowStepStatus::Pending->value,
            ]
        );

        $this->assertDatabaseHas(
            'transactions',
            [
                'id' => $transaction->id,
                'status' => TransactionStatus::Pending->value,
                'current_workflow_step_id' => $step2->id,
            ]
        );
    }

    public function test_final_approval_changes_status_to_approved(): void
    {
        $ctx = $this->makePendingTransaction();

        $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'transactions',
            [
                'id' => $ctx['transaction']->id,
                'status' => TransactionStatus::Approved->value,
            ]
        );

        $this->assertDatabaseHas(
            'transaction_histories',
            [
                'transaction_id' => $ctx['transaction']->id,
                'action' => TransactionHistoryAction::FullyApproved->value,
            ]
        );
    }

    public function test_unauthorized_manager_cannot_approve_a_step(): void
    {
        $otherDept = Department::factory()->create();

        $unauthorizedManager = User::factory()
            ->manager($otherDept)
            ->create();

        $ctx = $this->makePendingTransaction();

        $response = $this->actingAs(
            $unauthorizedManager,
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        );

        $response->assertForbidden();
    }

    public function test_employee_cannot_approve_a_transaction(): void
    {
        $ctx = $this->makePendingTransaction();

        $response = $this->actingAs(
            $ctx['employee'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        );

        $response->assertForbidden();
    }

   public function test_manager_cannot_review_their_own_transaction(): void
{
    $department = Department::factory()->create();

    $manager = User::factory()
        ->manager($department)
        ->create();

    $department->update([
        'manager_id' => $manager->id,
    ]);

    $type = TransactionType::factory()->create([
        'destination_department_id' => $department->id,
    ]);

    TransactionTypeWorkflowStep::factory()->create([
        'transaction_type_id' => $type->id,
        'department_id' => $department->id,
        'step_order' => 1,
        'is_final' => true,
    ]);

    $transaction = Transaction::factory()
        ->pending()
        ->create([
            'created_by' => $manager->id,
            'transaction_type_id' => $type->id,
            'source_department_id' => $department->id,
            'destination_department_id' => $department->id,
            'current_department_id' => $department->id,
        ]);

    $step = TransactionWorkflowStep::factory()
        ->pending()
        ->create([
            'transaction_id' => $transaction->id,
            'department_id' => $department->id,
        ]);

    $transaction->update([
        'current_workflow_step_id' => $step->id,
    ]);

    $response = $this->actingAs(
        $manager,
        'sanctum'
    )->postJson(
        "/api/v1/manager/transactions/{$transaction->id}/approve",
        [
            'comment' => 'Approved.',
        ]
    );

    $response->assertStatus(409);
}

    public function test_manager_cannot_approve_the_same_step_twice(): void
    {
        $ctx = $this->makePendingTransaction();

        $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'First.',
            ]
        )->assertOk();

        $response = $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Second.',
            ]
        );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Return
    |--------------------------------------------------------------------------
    */

    public function test_return_requires_a_comment(): void
    {
        $ctx = $this->makePendingTransaction();

        $response = $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/return",
            []
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    }

    public function test_return_changes_status_to_returned(): void
    {
        $ctx = $this->makePendingTransaction();

        $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/return",
            [
                'comment' => 'Please attach the quotation.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'transactions',
            [
                'id' => $ctx['transaction']->id,
                'status' => TransactionStatus::Returned->value,
            ]
        );

        $this->assertDatabaseHas(
            'transaction_workflow_steps',
            [
                'id' => $ctx['step']->id,
                'status' => WorkflowStepStatus::Returned->value,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Rejection
    |--------------------------------------------------------------------------
    */

    public function test_rejection_requires_a_comment(): void
    {
        $ctx = $this->makePendingTransaction();

        $response = $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/reject",
            []
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    }

    public function test_rejection_ends_the_workflow(): void
    {
        $ctx = $this->makePendingTransaction();

        $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/reject",
            [
                'comment' => 'Exceeds budget.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'transactions',
            [
                'id' => $ctx['transaction']->id,
                'status' => TransactionStatus::Rejected->value,
            ]
        );

        $this->assertDatabaseHas(
            'transaction_workflow_steps',
            [
                'id' => $ctx['step']->id,
                'status' => WorkflowStepStatus::Rejected->value,
            ]
        );
    }

    public function test_cannot_reject_already_approved_transaction(): void
    {
        $ctx = $this->makePendingTransaction();

        // Approve once to move past pending.
        $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        )->assertOk();

        // The transaction is now approved and should no longer
        // be reviewable by the same manager.
        $response = $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/reject",
            [
                'comment' => 'Too late.',
            ]
        );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | History & Notifications
    |--------------------------------------------------------------------------
    */

    public function test_every_workflow_action_creates_history(): void
    {
        $ctx = $this->makePendingTransaction();

        $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        )->assertOk();

        $this->assertDatabaseHas(
            'transaction_histories',
            [
                'transaction_id' => $ctx['transaction']->id,
                'action' => TransactionHistoryAction::ApprovedStep->value,
            ]
        );

        $this->assertDatabaseHas(
            'transaction_histories',
            [
                'transaction_id' => $ctx['transaction']->id,
                'action' => TransactionHistoryAction::FullyApproved->value,
            ]
        );
    }

    public function test_workflow_actions_create_notifications(): void
    {
        $ctx = $this->makePendingTransaction();

        $response = $this->actingAs(
            $ctx['manager'],
            'sanctum'
        )->postJson(
            "/api/v1/manager/transactions/{$ctx['transaction']->id}/approve",
            [
                'comment' => 'Approved.',
            ]
        );

        $response->assertOk();

        $this->assertTrue(
            Notification::where(
                'transaction_id',
                $ctx['transaction']->id
            )
                ->where(
                    'user_id',
                    $ctx['employee']->id
                )
                ->exists()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Completion
    |--------------------------------------------------------------------------
    */

    public function test_approved_transaction_can_be_completed(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $department = Department::factory()->create();

        $employee = User::factory()
            ->employee($department)
            ->create();

        $type = TransactionType::factory()->create([
            'destination_department_id' => $department->id,
        ]);

        $transaction = Transaction::factory()
            ->approved()
            ->create([
                'created_by' => $employee->id,
                'transaction_type_id' => $type->id,
                'source_department_id' => $department->id,
                'destination_department_id' => $department->id,
            ]);

        $response = $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/transactions/{$transaction->id}/complete",
            [
                'comment' => 'Executed and closed.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath(
                'data.status',
                'completed'
            );

        $this->assertDatabaseHas(
            'transactions',
            [
                'id' => $transaction->id,
                'status' => TransactionStatus::Completed->value,
            ]
        );
    }

    public function test_non_approved_transaction_cannot_be_completed(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $department = Department::factory()->create();

        $employee = User::factory()
            ->employee($department)
            ->create();

        $type = TransactionType::factory()->create([
            'destination_department_id' => $department->id,
        ]);

        $transaction = Transaction::factory()
            ->pending()
            ->create([
                'created_by' => $employee->id,
                'transaction_type_id' => $type->id,
                'source_department_id' => $department->id,
                'destination_department_id' => $department->id,
            ]);

        $response = $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/transactions/{$transaction->id}/complete",
            [
                'comment' => 'Should not complete.',
            ]
        );

        $response->assertStatus(409);
    }
}