<?php

namespace Database\Seeders;

use App\Enums\TransactionHistoryAction;
use App\Enums\TransactionPriority;
use App\Enums\TransactionStatus;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\TransactionHistory;
use App\Models\TransactionType;
use App\Models\TransactionWorkflowStep;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Seed demo transactions with workflow snapshots, history, and notifications.
     */
    public function run(): void
    {
        $employees = User::where('role', 'employee')->get();
        $types = TransactionType::with('workflowSteps')->get();

        if ($employees->isEmpty() || $types->isEmpty()) {
            return;
        }

        $statuses = [
            TransactionStatus::Draft,
            TransactionStatus::Pending,
            TransactionStatus::Returned,
            TransactionStatus::Rejected,
            TransactionStatus::Approved,
            TransactionStatus::Completed,
        ];

        $baseSequence = Transaction::count();

        for ($i = 0; $i < 20; $i++) {
            $employee = $employees->random();
            $type = $types->random();
            $status = $statuses[$i % count($statuses)];

            $department = $employee->department;
            $steps = $type->workflowSteps;

            $transaction = Transaction::create([
                'transaction_number' => 'TRX-'.now()->year.'-'.str_pad((string) ($baseSequence + $i + 1), 6, '0', STR_PAD_LEFT),
                'created_by' => $employee->id,
                'transaction_type_id' => $type->id,
                'source_department_id' => $department?->id,
                'destination_department_id' => $type->destination_department_id,
                'title' => fake()->sentence(4),
                'description' => fake()->paragraph(),
                'priority' => fake()->randomElement(TransactionPriority::cases()),
                'status' => $status,
                'submitted_at' => $status === TransactionStatus::Draft ? null : now()->subDays(rand(1, 10)),
                'approved_at' => in_array($status, [TransactionStatus::Approved, TransactionStatus::Completed]) ? now()->subDays(rand(1, 3)) : null,
                'rejected_at' => $status === TransactionStatus::Rejected ? now()->subDays(rand(1, 3)) : null,
                'returned_at' => $status === TransactionStatus::Returned ? now()->subDays(rand(1, 3)) : null,
                'completed_at' => $status === TransactionStatus::Completed ? now()->subDay() : null,
            ]);

            // Created history
            TransactionHistory::create([
                'transaction_id' => $transaction->id,
                'performed_by' => $employee->id,
                'action' => TransactionHistoryAction::Created,
                'old_status' => null,
                'new_status' => TransactionStatus::Draft->value,
                'comment' => 'Transaction created.',
            ]);

            if ($status === TransactionStatus::Draft) {
                continue;
            }

            // Snapshot workflow steps
            $snapshots = [];
            foreach ($steps as $index => $step) {
                $snapshots[] = TransactionWorkflowStep::create([
                    'transaction_id' => $transaction->id,
                    'department_id' => $step->department_id,
                    'original_workflow_step_id' => $step->id,
                    'step_order' => $step->step_order,
                    'name' => $step->name,
                    'status' => 'waiting',
                ]);
            }

            // Submitted history
            TransactionHistory::create([
                'transaction_id' => $transaction->id,
                'performed_by' => $employee->id,
                'action' => TransactionHistoryAction::Submitted,
                'old_status' => TransactionStatus::Draft->value,
                'new_status' => TransactionStatus::Pending->value,
                'comment' => 'Transaction submitted.',
            ]);

            if ($status === TransactionStatus::Pending) {
                $first = $snapshots[0] ?? null;
                if ($first) {
                    $first->update(['status' => 'pending']);
                    $transaction->update([
                        'current_workflow_step_id' => $first->id,
                        'current_department_id' => $first->department_id,
                    ]);
                }

                continue;
            }

            if ($status === TransactionStatus::Returned) {
                $first = $snapshots[0] ?? null;
                if ($first) {
                    $first->update(['status' => 'returned']);
                    $transaction->update(['current_workflow_step_id' => $first->id]);
                }
                TransactionHistory::create([
                    'transaction_id' => $transaction->id,
                    'performed_by' => $type->destinationDepartment?->manager_id,
                    'action' => TransactionHistoryAction::Returned,
                    'old_status' => TransactionStatus::Pending->value,
                    'new_status' => TransactionStatus::Returned->value,
                    'comment' => 'Please modify and resubmit.',
                ]);

                continue;
            }

            if ($status === TransactionStatus::Rejected) {
                $first = $snapshots[0] ?? null;
                if ($first) {
                    $first->update(['status' => 'rejected']);
                }
                TransactionHistory::create([
                    'transaction_id' => $transaction->id,
                    'performed_by' => $type->destinationDepartment?->manager_id,
                    'action' => TransactionHistoryAction::Rejected,
                    'old_status' => TransactionStatus::Pending->value,
                    'new_status' => TransactionStatus::Rejected->value,
                    'comment' => 'Request rejected.',
                ]);

                continue;
            }

            // Approved / Completed
            foreach ($snapshots as $index => $snapshot) {
                $snapshot->update(['status' => 'approved']);
                TransactionHistory::create([
                    'transaction_id' => $transaction->id,
                    'performed_by' => $type->destinationDepartment?->manager_id,
                    'action' => TransactionHistoryAction::ApprovedStep,
                    'old_status' => TransactionStatus::Pending->value,
                    'new_status' => TransactionStatus::Pending->value,
                    'workflow_step_name' => $snapshot->name,
                    'comment' => 'Step approved.',
                ]);
            }

            TransactionHistory::create([
                'transaction_id' => $transaction->id,
                'performed_by' => $type->destinationDepartment?->manager_id,
                'action' => TransactionHistoryAction::FullyApproved,
                'old_status' => TransactionStatus::Pending->value,
                'new_status' => TransactionStatus::Approved->value,
                'comment' => 'Transaction fully approved.',
            ]);

            if ($status === TransactionStatus::Completed) {
                TransactionHistory::create([
                    'transaction_id' => $transaction->id,
                    'performed_by' => User::where('role', 'admin')->first()?->id,
                    'action' => TransactionHistoryAction::Completed,
                    'old_status' => TransactionStatus::Approved->value,
                    'new_status' => TransactionStatus::Completed->value,
                    'comment' => 'Transaction completed.',
                ]);
            }
        }

        // Notifications for a few pending transactions
        $pending = Transaction::where('status', TransactionStatus::Pending)
            ->with('currentDepartment.manager')
            ->first();

        if ($pending && $pending->currentDepartment?->manager) {
            Notification::create([
                'user_id' => $pending->currentDepartment->manager->id,
                'transaction_id' => $pending->id,
                'title' => 'Review required',
                'message' => 'Transaction '.$pending->transaction_number.' requires your review.',
                'type' => 'review_required',
            ]);
        }

        $approved = Transaction::where('status', TransactionStatus::Approved)->first();

        if ($approved && $approved->creator) {
            Notification::create([
                'user_id' => $approved->creator->id,
                'transaction_id' => $approved->id,
                'title' => 'Approved',
                'message' => 'Your transaction '.$approved->transaction_number.' was approved.',
                'type' => 'transaction_approved',
            ]);
        }
    }
}
