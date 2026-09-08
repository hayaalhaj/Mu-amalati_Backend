<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\TransactionType;
use Illuminate\Http\JsonResponse;

class TransactionTypeController extends Controller
{
    use ApiResponse;

    /**
     * List active transaction types for dropdowns.
     */
    public function index(): JsonResponse
    {
        $types = TransactionType::query()
            ->select([
                'id',
                'name_en',
                'name_ar',
                'description',
                'requires_attachment',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where(
                'is_active',
                true
            )
            ->orderBy(
                'name_en'
            )
            ->get();

        return $this->success(
            TransactionTypeResource::collection(
                $types
            )
        );
    }

    /**
     * Show a single active transaction type.
     */
    public function show(
        TransactionType $transactionType
    ): JsonResponse {
        if (! $transactionType->is_active) {
            return $this->notFound(
                'Transaction type not found.'
            );
        }

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
     * List fields for an active transaction type.
     */
    public function fields(
        TransactionType $transactionType
    ): JsonResponse {
        if (! $transactionType->is_active) {
            return $this->notFound(
                'Transaction type not found.'
            );
        }

        $fields = $transactionType
            ->fields()
            ->orderBy(
                'field_order'
            )
            ->get();

        return $this->success(
            \App\Http\Resources\TransactionTypeFieldResource::collection(
                $fields
            )
        );
    }
}