<?php

namespace App\Services;

use App\Enums\TransactionHistoryAction;
use App\Enums\TransactionStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Transaction;
use App\Models\TransactionHistory;
use App\Models\TransactionTypeWorkflowStep;
use App\Models\TransactionWorkflowStep;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionSubmissionService
{
    public function __construct(
        private readonly TransactionNotificationService $notificationService,
    ) {}

    /**
     * Submit a draft transaction, snapshotting the configured workflow.
     *
     * If the creator is a manager who manages the department
     * responsible for a workflow step, that step is skipped
     * automatically so the creator cannot review their own transaction.
     *
     * If all workflow steps are skipped, the transaction is
     * automatically approved.
     *
     * @return array{transaction: Transaction, submitted: bool, message: string}
     */
    public function submit(
        Transaction $transaction,
        User $actor
    ): array {
        return DB::transaction(
            function () use (
                $transaction,
                $actor
            ) {
                $transaction =
                    Transaction::whereKey(
                        $transaction->getKey()
                    )
                        ->lockForUpdate()
                        ->first();

                if (
                    $transaction->status !==
                    TransactionStatus::Draft
                ) {
                    throw new RuntimeException(
                        'Only draft transactions can be submitted.'
                    );
                }

                $type =
                    $transaction
                        ->transactionType()
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $type ||
                    ! $type->is_active
                ) {
                    throw new RuntimeException(
                        'The selected transaction type is not active.'
                    );
                }

                $steps =
                    TransactionTypeWorkflowStep::where(
                        'transaction_type_id',
                        $type->id
                    )
                        ->orderBy('step_order')
                        ->get();

                if (
                    $steps->isEmpty()
                ) {
                    throw new RuntimeException(
                        'This transaction type has no workflow configured.'
                    );
                }

                if (
                    $type->requires_attachment &&
                    $transaction->attachments()->count() === 0
                ) {
                    throw new RuntimeException(
                        'This transaction type requires at least one attachment.'
                    );
                }

                $snapshots =
                    $this->snapshotWorkflow(
                        $transaction,
                        $steps,
                        $actor
                    );

                $first =
                    $this->firstPendingStep(
                        $snapshots
                    );

                /*
                 * All workflow steps were skipped.
                 *
                 * This happens when a manager creates a transaction
                 * and every workflow department is managed by that
                 * same manager.
                 */
                if (! $first) {
                    $this->finalizeApproval(
                        $transaction,
                        $actor
                    );

                    $this->recordHistory(
                        $transaction,
                        $actor,
                        TransactionHistoryAction::Submitted,
                        'Transaction submitted. All workflow steps were skipped because the creator cannot review their own transaction.'
                    );

                    return [
                        'transaction' =>
                            $transaction,

                        'submitted' =>
                            true,

                        'message' =>
                            'Transaction submitted successfully and was automatically approved because all workflow steps were skipped.',
                    ];
                }

                $transaction->update([
                    'status' =>
                        TransactionStatus::Pending,

                    'current_workflow_step_id' =>
                        $first->id,

                    'current_department_id' =>
                        $first->department_id,

                    'submitted_at' =>
                        now(),

                    'last_action_by' =>
                        $actor->id,
                ]);

                $this->recordHistory(
                    $transaction,
                    $actor,
                    TransactionHistoryAction::Submitted
                );

                $this->notificationService
                    ->notifyManagerForReview(
                        $transaction,
                        $first->department_id
                    );

                return [
                    'transaction' =>
                        $transaction,

                    'submitted' =>
                        true,

                    'message' =>
                        'Transaction submitted successfully.',
                ];
            }
        );
    }

    /**
     * Resubmit a returned transaction, resuming from the returned step.
     *
     * If the returned step belongs to the creator's managed department,
     * the step is skipped again and the workflow continues to the next
     * applicable step.
     *
     * If no valid step remains, the transaction is automatically approved.
     *
     * @return array{transaction: Transaction, submitted: bool, message: string}
     */
    public function resubmit(
        Transaction $transaction,
        User $actor
    ): array {
        return DB::transaction(
            function () use (
                $transaction,
                $actor
            ) {
                $transaction =
                    Transaction::whereKey(
                        $transaction->getKey()
                    )
                        ->lockForUpdate()
                        ->first();

                if (
                    $transaction->status !==
                    TransactionStatus::Returned
                ) {
                    throw new RuntimeException(
                        'Only returned transactions can be resubmitted.'
                    );
                }

                $type =
                    $transaction->transactionType;

                if (
                    ! $type ||
                    ! $type->is_active
                ) {
                    throw new RuntimeException(
                        'The selected transaction type is not active.'
                    );
                }

                if (
                    $type->requires_attachment &&
                    $transaction->attachments()->count() === 0
                ) {
                    throw new RuntimeException(
                        'This transaction type requires at least one attachment.'
                    );
                }

                $returnedStep =
                    $transaction
                        ->workflowSteps()
                        ->where(
                            'status',
                            WorkflowStepStatus::Returned
                        )
                        ->orderBy('step_order')
                        ->first();

                if (! $returnedStep) {
                    throw new RuntimeException(
                        'No returned workflow step was found for this transaction.'
                    );
                }

                /*
                 * Reset the returned step first.
                 *
                 * It is temporarily set to Waiting because we still
                 * need to determine whether this step should be skipped.
                 */
                $returnedStep->update([
                    'status' =>
                        WorkflowStepStatus::Waiting,

                    'reviewed_by' =>
                        null,

                    'comment' =>
                        null,

                    'reviewed_at' =>
                        null,
                ]);

                /*
                 * Find the first valid step from the returned
                 * step onwards.
                 */
                $nextStep =
                    $this->findNextValidStep(
                        $transaction,
                        $returnedStep->step_order,
                        $actor
                    );

                /*
                 * All remaining steps belong to the creator's
                 * managed departments and were skipped.
                 */
                if (! $nextStep) {
                    $this->finalizeApproval(
                        $transaction,
                        $actor
                    );

                    $this->recordHistory(
                        $transaction,
                        $actor,
                        TransactionHistoryAction::Resubmitted,
                        'Transaction resubmitted. All remaining workflow steps were skipped because the creator cannot review their own transaction.'
                    );

                    return [
                        'transaction' =>
                            $transaction,

                        'submitted' =>
                            true,

                        'message' =>
                            'Transaction resubmitted successfully and was automatically approved because no review step remained.',
                    ];
                }

                /*
                 * The selected workflow step is now active.
                 *
                 * This is important for resubmission:
                 * the returned step must become Pending so the
                 * responsible manager can review it again.
                 */
                $nextStep->update([
                    'status' =>
                        WorkflowStepStatus::Pending,
                ]);

                $transaction->update([
                    'status' =>
                        TransactionStatus::Pending,

                    'current_workflow_step_id' =>
                        $nextStep->id,

                    'current_department_id' =>
                        $nextStep->department_id,

                    'returned_at' =>
                        null,

                    'last_action_by' =>
                        $actor->id,
                ]);

                $this->recordHistory(
                    $transaction,
                    $actor,
                    TransactionHistoryAction::Resubmitted
                );

                $this->notificationService
                    ->notifyManagerForReview(
                        $transaction,
                        $nextStep->department_id
                    );

                return [
                    'transaction' =>
                        $transaction,

                    'submitted' =>
                        true,

                    'message' =>
                        'Transaction resubmitted successfully.',
                ];
            }
        );
    }

    /**
     * Copy the transaction type workflow definitions into snapshot records.
     *
     * Creator-owned manager steps are immediately marked as skipped.
     *
     * @param Collection<int, TransactionTypeWorkflowStep> $definitions
     * @return Collection<int, TransactionWorkflowStep>
     */
    protected function snapshotWorkflow(
        Transaction $transaction,
        Collection $definitions,
        User $actor
    ): Collection {
        $snapshots =
            collect();

        foreach (
            $definitions as $definition
        ) {
            $status =
                $this->shouldSkipStep(
                    $transaction,
                    $actor,
                    $definition->department_id
                )
                    ? WorkflowStepStatus::Skipped
                    : WorkflowStepStatus::Waiting;

            $snapshot =
                TransactionWorkflowStep::create([
                    'transaction_id' =>
                        $transaction->id,

                    'department_id' =>
                        $definition->department_id,

                    'original_workflow_step_id' =>
                        $definition->id,

                    'step_order' =>
                        $definition->step_order,

                    'name' =>
                        $definition->name,

                    'status' =>
                        $status,
                ]);

            $snapshots->push(
                $snapshot
            );
        }

        /*
         * The first non-skipped step becomes Pending.
         */
        $first =
            $this->firstWaitingStep(
                $snapshots
            );

        if ($first) {
            $first->update([
                'status' =>
                    WorkflowStepStatus::Pending,
            ]);
        }

        return $snapshots;
    }

    /**
     * Determine whether a workflow step should be skipped.
     *
     * Only a manager creating their own transaction can trigger this rule.
     */
    protected function shouldSkipStep(
        Transaction $transaction,
        User $actor,
        int $departmentId
    ): bool {
        if (
            $transaction->created_by !==
            $actor->id
        ) {
            return false;
        }

        if (
            ! $actor->isManager()
        ) {
            return false;
        }

        return $actor
            ->managedDepartments()
            ->where(
                'id',
                $departmentId
            )
            ->exists();
    }

    /**
     * Return the first pending step.
     */
    protected function firstPendingStep(
        Collection $steps
    ): ?TransactionWorkflowStep {
        return $steps
            ->first(
                fn (
                    TransactionWorkflowStep $step
                ) =>
                    $step->status ===
                    WorkflowStepStatus::Pending
            );
    }

    /**
     * Return the first waiting step.
     */
    protected function firstWaitingStep(
        Collection $steps
    ): ?TransactionWorkflowStep {
        return $steps
            ->first(
                fn (
                    TransactionWorkflowStep $step
                ) =>
                    $step->status ===
                    WorkflowStepStatus::Waiting
            );
    }

    /**
     * Find the first valid workflow step after a given order.
     *
     * Creator-owned manager steps are skipped.
     */
    protected function findNextValidStep(
        Transaction $transaction,
        int $currentOrder,
        User $actor
    ): ?TransactionWorkflowStep {
        $steps =
            TransactionWorkflowStep::where(
                'transaction_id',
                $transaction->id
            )
                ->where(
                    'step_order',
                    '>=',
                    $currentOrder
                )
                ->whereIn(
                    'status',
                    [
                        WorkflowStepStatus::Waiting,
                        WorkflowStepStatus::Pending,
                    ]
                )
                ->orderBy(
                    'step_order'
                )
                ->get();

        foreach (
            $steps as $step
        ) {
            if (
                $this->shouldSkipStep(
                    $transaction,
                    $actor,
                    $step->department_id
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
     * Finalize a transaction as fully approved.
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

            'submitted_at' =>
                $transaction->submitted_at ??
                now(),

            'returned_at' =>
                null,

            'last_action_by' =>
                $actor->id,
        ]);

        /*
         * Leave Request:
         * deduct approved leave days
         * from the transaction creator.
         */
        if (
            $transaction
                ->transactionType
                ?->name_en ===
            'Leave Request'
        ) {
            $this->deductLeaveDays(
                $transaction
            );
        }

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

        $startDate =
            \Carbon\Carbon::parse(
                $transaction->start_date
            );

        $endDate =
            \Carbon\Carbon::parse(
                $transaction->end_date
            );

        if (
            $endDate->lt(
                $startDate
            )
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

        $employee =
            User::whereKey(
                $transaction->created_by
            )
                ->lockForUpdate()
                ->firstOrFail();

        $remainingDays =
            $employee->annual_leave_days -
            $employee->used_leave_days;

        if (
            $leaveDays >
            $remainingDays
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
     * Record a history entry for the transaction.
     */
    protected function recordHistory(
        Transaction $transaction,
        User $actor,
        TransactionHistoryAction $action,
        ?string $comment = null
    ): TransactionHistory {
        return TransactionHistory::create([
            'transaction_id' =>
                $transaction->id,

            'performed_by' =>
                $actor->id,

            'action' =>
                $action,

            'old_status' =>
                $transaction->getOriginal(
                    'status'
                ) instanceof TransactionStatus
                    ? $transaction
                        ->getOriginal('status')
                        ->value
                    : $transaction->getOriginal(
                        'status'
                    ),

            'new_status' =>
                $transaction->status->value,

            'comment' =>
                $comment,
        ]);
    }
}