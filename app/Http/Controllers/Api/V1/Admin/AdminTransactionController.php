<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompleteTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Transaction;
use App\Services\TransactionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminTransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransactionWorkflowService $workflowService,
    ) {}

    /**
     * List all transactions.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $transactions = Transaction::query()
            ->with(['creator.department', 'transactionType', 'sourceDepartment', 'destinationDepartment', 'currentDepartment', 'currentWorkflowStep'])
            ->filter($request->all())
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
     * Show a transaction.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'currentWorkflowStep',
            'workflowSteps.department',
            'attachments',
            'histories.performer',
        ]);

        return $this->success(new TransactionResource($transaction));
    }

    /**
     * Complete an approved transaction.
     */
    public function complete(CompleteTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        try {
            $transaction = $this->workflowService->complete($transaction, $request->user(), $request->comment);
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

        return $this->success(new TransactionResource($transaction), 'Transaction completed successfully.');
    }
}
