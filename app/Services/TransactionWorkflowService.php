<?php

namespace App\Services;

use App\Enums\TransactionHistoryAction;
use App\Enums\TransactionStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Transaction;
use App\Models\TransactionHistory;
use App\Models\TransactionWorkflowStep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionWorkflowService
{
    public function __construct(
        private readonly TransactionNotificationService $notificationService,
    ) {}

    /**
     * Approve the current pending workflow step.
     */
    public function approve(
        Transaction $transaction,
        User $actor,
        ?string $comment = null
    ): Transaction {
        return DB::transaction(function () use (
            $transaction,
            $actor,
            $comment
        ) {
            $transaction = Transaction::whereKey(
                $transaction->getKey()
            )
                ->lockForUpdate()
                ->first();

            $this->assertReviewable(
                $transaction,
                $actor
            );

            $step = $this->pendingStep(
                $transaction
            );

            $step->update([
                'status' =>
                    WorkflowStepStatus::Approved,

                'reviewed_by' =>
                    $actor->id,

                'comment' =>
                    $comment,

                'reviewed_at' =>
                    now(),
            ]);

            $this->recordHistory(
                $transaction,
                $actor,
                TransactionHistoryAction::ApprovedStep,
                $comment,
                $step->name
            );

            /*
             * Find the next usable workflow step.
             *
             * If the creator is a manager and the next step
             * belongs to a department managed by that same
             * creator, skip it automatically.
             */
            $nextStep =
                $this->findNextAvailableStep(
                    $transaction,
                    $step->step_order
                );

            /*
             * There is another valid workflow step.
             */
            if ($nextStep) {
                $nextStep->update([
                    'status' =>
                        WorkflowStepStatus::Pending,
                ]);

                $transaction->update([
                    'current_workflow_step_id' =>
                        $nextStep->id,

                    'current_department_id' =>
                        $nextStep->department_id,

                    'last_action_by' =>
                        $actor->id,
                ]);

                $this->notificationService
                    ->notifyManagerForReview(
                        $transaction,
                        $nextStep->department_id
                    );
            }

            /*
             * There are no more valid workflow steps.
             *
             * All remaining steps were either completed
             * or skipped.
             */
            else {
                $this->finalizeApproval(
                    $transaction,
                    $actor
                );
            }

            return $transaction;
        });
    }

    /**
     * Finalize a fully approved transaction.
     */
    protected function finalizeApproval(
        Transaction $transaction,
        User $actor
    ): void {
        $transaction->loadMissing(
            'transactionType'
        );

        $transaction->update([
            'status' =>
                TransactionStatus::Approved,

            'current_workflow_step_id' =>
                null,

            'current_department_id' =>
                null,

            'approved_at' =>
                now(),

            'last_action_by' =>
                $actor->id,
        ]);

        /*
         * Leave Request:
         * deduct the approved leave days
         * from the transaction creator.
         */
        if (
            $transaction->transactionType?->name_en ===
            'Leave Request'
        ) {
            $this->deductLeaveDays(
                $transaction
            );
        }

        $this->recordHistory(
            $transaction,
            $actor,
            TransactionHistoryAction::FullyApproved
        );

        $this->notificationService->notifyCreator(
            $transaction,
            'transaction_approved',
            'fully approved'
        );
    }

    /**
     * Deduct leave balance after final approval.
     */
    protected function deductLeaveDays(
        Transaction $transaction
    ): void {
        if (
            ! $transaction->start_date ||
            ! $transaction->end_date
        ) {
            throw new RuntimeException(
                'Leave request must have a start date and an end date.'
            );
        }

        $startDate = Carbon::parse(
            $transaction->start_date
        );

        $endDate = Carbon::parse(
            $transaction->end_date
        );

        if (
            $endDate->lt($startDate)
        ) {
            throw new RuntimeException(
                'Leave end date cannot be before start date.'
            );
        }

        /*
         * Inclusive calculation:
         *
         * 20 Aug → 20 Aug = 1 day
         * 20 Aug → 22 Aug = 3 days
         */
        $leaveDays =
            $startDate->diffInDays(
                $endDate
            ) + 1;

        $employee = User::whereKey(
            $transaction->created_by
        )
            ->lockForUpdate()
            ->firstOrFail();

        $remainingDays =
            $employee->annual_leave_days -
            $employee->used_leave_days;

        if (
            $leaveDays > $remainingDays
        ) {
            throw new RuntimeException(
                'The employee does not have enough leave balance.'
            );
        }

        $employee->increment(
            'used_leave_days',
            $leaveDays
        );
    }

    /**
     * Return the transaction to the creator for modification.
     */
    public function return(
        Transaction $transaction,
        User $actor,
        string $comment
    ): Transaction {
        return DB::transaction(function () use (
            $transaction,
            $actor,
            $comment
        ) {
            $transaction = Transaction::whereKey(
                $transaction->getKey()
            )
                ->lockForUpdate()
                ->first();

            $this->assertReviewable(
                $transaction,
                $actor
            );

            $step = $this->pendingStep(
                $transaction
            );

            $step->update([
                'status' =>
                    WorkflowStepStatus::Returned,

                'reviewed_by' =>
                    $actor->id,

                'comment' =>
                    $comment,

                'reviewed_at' =>
                    now(),
            ]);

            $transaction->update([
                'status' =>
                    TransactionStatus::Returned,

                'returned_at' =>
                    now(),

                'last_action_by' =>
                    $actor->id,
            ]);

            $this->recordHistory(
                $transaction,
                $actor,
                TransactionHistoryAction::Returned,
                $comment,
                $step->name
            );

            $this->notificationService->notifyCreator(
                $transaction,
                'transaction_returned',
                'returned'
            );

            return $transaction;
        });
    }

    /**
     * Reject the transaction, ending the workflow.
     */
    public function reject(
        Transaction $transaction,
        User $actor,
        string $comment
    ): Transaction {
        return DB::transaction(function () use (
            $transaction,
            $actor,
            $comment
        ) {
            $transaction = Transaction::whereKey(
                $transaction->getKey()
            )
                ->lockForUpdate()
                ->first();

            $this->assertReviewable(
                $transaction,
                $actor
            );

            $step = $this->pendingStep(
                $transaction
            );

            $step->update([
                'status' =>
                    WorkflowStepStatus::Rejected,

                'reviewed_by' =>
                    $actor->id,

                'comment' =>
                    $comment,

                'reviewed_at' =>
                    now(),
            ]);

            $transaction->update([
                'status' =>
                    TransactionStatus::Rejected,

                'current_workflow_step_id' =>
                    null,

                'current_department_id' =>
                    null,

                'rejected_at' =>
                    now(),

                'last_action_by' =>
                    $actor->id,
            ]);

            $this->recordHistory(
                $transaction,
                $actor,
                TransactionHistoryAction::Rejected,
                $comment,
                $step->name
            );

            $this->notificationService->notifyCreator(
                $transaction,
                'transaction_rejected',
                'rejected'
            );

            return $transaction;
        });
    }

    /**
     * Complete an approved transaction.
     */
    public function complete(
        Transaction $transaction,
        User $actor,
        ?string $comment = null
    ): Transaction {
        return DB::transaction(function () use (
            $transaction,
            $actor,
            $comment
        ) {
            $transaction = Transaction::whereKey(
                $transaction->getKey()
            )
                ->lockForUpdate()
                ->first();

            if (
                $transaction->status !==
                TransactionStatus::Approved
            ) {
                throw new RuntimeException(
                    'Only approved transactions can be completed.'
                );
            }

            $transaction->update([
                'status' =>
                    TransactionStatus::Completed,

                'completed_at' =>
                    now(),

                'last_action_by' =>
                    $actor->id,
            ]);

            $this->recordHistory(
                $transaction,
                $actor,
                TransactionHistoryAction::Completed,
                $comment
            );

            $this->notificationService->notifyCreator(
                $transaction,
                'transaction_completed',
                'completed'
            );

            return $transaction;
        });
    }

    /**
     * Assert the transaction is pending
     * and the actor is authorized to review it.
     */
   protected function assertReviewable(
    Transaction $transaction,
    User $actor
): void {
    if (
        $transaction->status !==
        TransactionStatus::Pending
    ) {
        throw new RuntimeException(
            'Only pending transactions can be reviewed.'
        );
    }

    /*
     * A transaction creator can never review
     * their own transaction.
     */
    if (
        $transaction->created_by ===
        $actor->id
    ) {
        throw new RuntimeException(
            'A creator cannot review their own transaction.'
        );
    }

    /*
     * A manager must be currently working
     * in order to take an approval action.
     *
     * Checkout / leave does NOT prevent the manager
     * from receiving notifications or viewing transactions.
     */
    if (
        $actor->isManager() &&
        $actor->workStatus() !== 'working'
    ) {
        throw new RuntimeException(
            'You must be checked in and working to review transactions.'
        );
    }

    $step =
        $this->pendingStepOrFail(
            $transaction
        );

    /*
     * Admins bypass department ownership checks.
     */
    if (
        ! $actor->isAdmin() &&
        ! $actor->managedDepartments()
            ->where(
                'id',
                $step->department_id
            )
            ->exists()
    ) {
        throw new RuntimeException(
            'You are not authorized to review this transaction.'
        );
    }
}

    /**
     * Find the next available workflow step.
     *
     * Any waiting step that belongs to a department managed
     * by the transaction creator is automatically skipped.
     */
    protected function findNextAvailableStep(
        Transaction $transaction,
        int $currentStepOrder
    ): ?TransactionWorkflowStep {
        $creator =
            User::find(
                $transaction->created_by
            );

        if (! $creator) {
            throw new RuntimeException(
                'Transaction creator could not be found.'
            );
        }

        $steps =
            TransactionWorkflowStep::where(
                'transaction_id',
                $transaction->id
            )
                ->where(
                    'step_order',
                    '>',
                    $currentStepOrder
                )
                ->where(
                    'status',
                    WorkflowStepStatus::Waiting
                )
                ->orderBy(
                    'step_order'
                )
                ->get();

        foreach (
            $steps as $step
        ) {
            /*
             * Creator is a manager and manages this step's
             * department: the creator must not review it.
             */
            if (
                $this->shouldSkipStepForCreator(
                    $transaction,
                    $creator,
                    $step
                )
            ) {
                $step->update([
                    'status' =>
                        WorkflowStepStatus::Skipped,
                ]);

                continue;
            }

            return $step;
        }

        return null;
    }

    /**
     * Determine whether a step must be skipped
     * because it belongs to the transaction creator's
     * managed department.
     */
    protected function shouldSkipStepForCreator(
        Transaction $transaction,
        User $creator,
        TransactionWorkflowStep $step
    ): bool {
        if (
            $transaction->created_by !==
            $creator->id
        ) {
            return false;
        }

        if (
            ! $creator->isManager()
        ) {
            return false;
        }

        return $creator
            ->managedDepartments()
            ->where(
                'id',
                $step->department_id
            )
            ->exists();
    }

    /**
     * Get the current pending step, or throw.
     */
    protected function pendingStep(
        Transaction $transaction
    ): TransactionWorkflowStep {
        return $this->pendingStepOrFail(
            $transaction
        );
    }

    /**
     * Get the current pending step or throw a conflict.
     */
    protected function pendingStepOrFail(
        Transaction $transaction
    ): TransactionWorkflowStep {
        $step =
            TransactionWorkflowStep::where(
                'transaction_id',
                $transaction->id
            )
                ->where(
                    'status',
                    WorkflowStepStatus::Pending
                )
                ->orderBy(
                    'step_order'
                )
                ->first();

        if (! $step) {
            throw new RuntimeException(
                'No pending workflow step is available for review.'
            );
        }

        return $step;
    }

    /**
     * Record a history entry for the transaction.
     */
    protected function recordHistory(
        Transaction $transaction,
        User $actor,
        TransactionHistoryAction $action,
        ?string $comment = null,
        ?string $stepName = null
    ): TransactionHistory {
        return TransactionHistory::create([
            'transaction_id' =>
                $transaction->id,

            'performed_by' =>
                $actor->id,

            'action' =>
                $action,

            'old_status' =>
                $transaction
                    ->getOriginal(
                        'status'
                    )
                instanceof TransactionStatus
                    ? $transaction
                        ->getOriginal(
                            'status'
                        )
                        ->value
                    : $transaction->getOriginal(
                        'status'
                    ),

            'new_status' =>
                $transaction->status->value,

            'workflow_step_name' =>
                $stepName,

            'comment' =>
                $comment,
        ]);
    }
}

