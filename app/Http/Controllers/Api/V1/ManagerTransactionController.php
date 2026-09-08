<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ApproveTransactionRequest;
use App\Http\Requests\Manager\RejectTransactionRequest;
use App\Http\Requests\Manager\ReturnTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Transaction;
use App\Services\TransactionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ManagerTransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransactionWorkflowService $workflowService,
    ) {}

    /**
     * List transactions awaiting the authenticated manager's review.
     */
    public function pendingTransactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 10), 50);

        $departmentIds = $user->managedDepartmentIds();

        $transactions = Transaction::query()
            ->where('status', 'pending')
            ->whereIn('current_department_id', $departmentIds)
->with(['creator.department', 'transactionType', 'sourceDepartment', 'destinationDepartment', 'currentDepartment', 'currentWorkflowStep'])
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successCollection(
            TransactionResource::collection($transactions),
            200,
            [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ]
        );
    }

    /**
     * Show a transaction for review.
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('review', $transaction);

        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'currentWorkflowStep',
            'workflowSteps.department',
            'attachments',
        ]);

        return $this->success(new TransactionResource($transaction));
    }

    /**
     * Approve the current workflow step.
     */
    public function approve(ApproveTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('review', $transaction);

        try {
            $transaction = $this->workflowService->approve($transaction, $request->user(), $request->comment);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }

        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'currentWorkflowStep',
            'workflowSteps.department',
            'attachments',
        ]);

        return $this->success(new TransactionResource($transaction), 'Step approved successfully.');
    }

    /**
     * Return the transaction to the creator.
     */
    public function return(ReturnTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('review', $transaction);

        try {
            $transaction = $this->workflowService->return($transaction, $request->user(), $request->comment);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }

        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'workflowSteps.department',
            'attachments',
        ]);

        return $this->success(new TransactionResource($transaction), 'Transaction returned.');
    }

    /**
     * Reject the transaction.
     */
    public function reject(RejectTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('review', $transaction);

        try {
            $transaction = $this->workflowService->reject($transaction, $request->user(), $request->comment);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 409);
        }

        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'workflowSteps.department',
            'attachments',
        ]);

        return $this->success(new TransactionResource($transaction), 'Transaction rejected.');
    }
}
