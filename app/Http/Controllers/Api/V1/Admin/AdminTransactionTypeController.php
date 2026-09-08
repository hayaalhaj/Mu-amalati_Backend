<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransactionTypeRequest;
use App\Http\Requests\Admin\UpdateTransactionTypeRequest;
use App\Http\Resources\TransactionTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\TransactionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTransactionTypeController extends Controller
{
    use ApiResponse;

    /**
     * List all transaction types.
     *
     * Supports filtering by workflow step count:
     *
     * workflow_steps_count=0
     * workflow_steps_count=1
     * workflow_steps_count=2
     * workflow_steps_count=3
     * workflow_steps_count=4
     * workflow_steps_count=5+
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            $request->integer('per_page', 10),
            50
        );

        $types = TransactionType::query()
            ->with([
                'destinationDepartment',
                'workflowSteps.department',
            ])
            ->withCount(
                'workflowSteps'
            )
            ->when(
                $request->filled(
                    'workflow_steps_count'
                ),
                function ($query) use ($request) {
                    $count =
                        $request->input(
                            'workflow_steps_count'
                        );

                    /*
                     * 5+ workflow steps.
                     */
                    if ($count === '5+') {
                        $query->has(
                            'workflowSteps',
                            '>=',
                            5
                        );

                        return;
                    }

                    /*
                     * Exact workflow step count.
                     */
                    if (is_numeric($count)) {
                        $query->has(
                            'workflowSteps',
                            '=',
                            (int) $count
                        );
                    }
                }
            )
            ->orderBy(
                'name_en'
            )
            ->paginate(
                $perPage
            );

        return $this->successCollection(
            TransactionTypeResource::collection(
                $types
            ),
            200,
            [
                'current_page' =>
                    $types->currentPage(),

                'per_page' =>
                    $types->perPage(),

                'total' =>
                    $types->total(),

                'last_page' =>
                    $types->lastPage(),
            ]
        );
    }

    /**
     * Create a transaction type.
     */
    public function store(
        StoreTransactionTypeRequest $request
    ): JsonResponse {
        $type =
            TransactionType::create(
                $request->validated()
            );

        $type->load([
            'destinationDepartment',
            'workflowSteps.department',
        ]);

        return $this->success(
            new TransactionTypeResource(
                $type
            ),
            'Transaction type created successfully.',
            201
        );
    }

    /**
     * Show a transaction type.
     */
    public function show(
        TransactionType $transactionType
    ): JsonResponse {
        $transactionType->load([
            'destinationDepartment',
            'workflowSteps.department',
        ]);

        return $this->success(
            new TransactionTypeResource(
                $transactionType
            )
        );
    }

    /**
     * Update a transaction type.
     */
    public function update(
        UpdateTransactionTypeRequest $request,
        TransactionType $transactionType
    ): JsonResponse {
        $transactionType->update(
            $request->validated()
        );

        $transactionType->load([
            'destinationDepartment',
            'workflowSteps.department',
        ]);

        return $this->success(
            new TransactionTypeResource(
                $transactionType
            ),
            'Transaction type updated successfully.'
        );
    }

    /**
     * Delete a transaction type.
     *
     * A transaction type cannot be deleted if it is already
     * referenced by existing transactions.
     */
    public function destroy(
        TransactionType $transactionType
    ): JsonResponse {
        if (
            $transactionType
                ->transactions()
                ->exists()
        ) {
            return $this->error(
                'This transaction type is referenced by transactions and cannot be deleted.',
                422
            );
        }

        $transactionType->delete();

        return $this->success(
            null,
            'Transaction type deleted successfully.',
            204
        );
    }

    /**
     * Activate a transaction type.
     */
    public function activate(
        TransactionType $transactionType
    ): JsonResponse {
        $transactionType->update([
            'is_active' => true,
        ]);

        $transactionType->load([
            'destinationDepartment',
            'workflowSteps.department',
        ]);

        return $this->success(
            new TransactionTypeResource(
                $transactionType
            ),
            'Transaction type activated successfully.'
        );
    }

    /**
     * Deactivate a transaction type.
     */
    public function deactivate(
        TransactionType $transactionType
    ): JsonResponse {
        $transactionType->update([
            'is_active' => false,
        ]);

        $transactionType->load([
            'destinationDepartment',
            'workflowSteps.department',
        ]);

        return $this->success(
            new TransactionTypeResource(
                $transactionType
            ),
            'Transaction type deactivated successfully.'
        );
    }
}