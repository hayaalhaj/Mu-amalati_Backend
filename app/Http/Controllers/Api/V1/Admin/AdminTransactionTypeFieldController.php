<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Resources\TransactionTypeFieldResource;
use App\Models\TransactionType;
use App\Models\TransactionTypeField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTransactionTypeFieldController extends Controller
{
    use ApiResponse;

    /**
     * List fields for a transaction type.
     */
    public function index(
        TransactionType $transactionType
    ): JsonResponse {
        $fields = $transactionType
            ->fields()
            ->orderBy('field_order')
            ->get();

        return $this->success(
            TransactionTypeFieldResource::collection(
                $fields
            )
        );
    }

    /**
     * Create a field.
     */
    public function store(
        Request $request,
        TransactionType $transactionType
    ): JsonResponse {
        $data = $request->validate([
            'name_en' => [
                'required',
                'string',
                'max:255',
            ],

            'field_type' => [
                'required',
                'string',
                'in:text,textarea,number,date,datetime,select,radio,checkbox,file,currency,email,phone',
            ],

            'is_required' => [
                'sometimes',
                'boolean',
            ],

            'placeholder_en' => [
                'nullable',
                'string',
                'max:255',
            ],

            'placeholder_ar' => [
                'nullable',
                'string',
                'max:255',
            ],

            'options' => [
                'nullable',
                'array',
            ],

            'field_order' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $field = $transactionType
            ->fields()
            ->create($data);

        return $this->success(
            new TransactionTypeFieldResource(
                $field
            ),
            'Transaction type field created successfully.',
            201
        );
    }

    /**
     * Update a field.
     */
    public function update(
        Request $request,
        TransactionTypeField $transactionTypeField
    ): JsonResponse {
        $data = $request->validate([
            'name_en' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'field_type' => [
                'sometimes',
                'required',
                'string',
                'in:text,textarea,number,date,datetime,select,radio,checkbox,file,currency,email,phone',
            ],

            'is_required' => [
                'sometimes',
                'boolean',
            ],

            'placeholder_en' => [
                'nullable',
                'string',
                'max:255',
            ],

            'placeholder_ar' => [
                'nullable',
                'string',
                'max:255',
            ],

            'options' => [
                'nullable',
                'array',
            ],

            'field_order' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $transactionTypeField->update(
            $data
        );

        return $this->success(
            new TransactionTypeFieldResource(
                $transactionTypeField->fresh()
            ),
            'Transaction type field updated successfully.'
        );
    }

    /**
     * Delete a field.
     */
    public function destroy(
        TransactionTypeField $transactionTypeField
    ): JsonResponse {
        $transactionTypeField->delete();

        return $this->success(
            null,
            'Transaction type field deleted successfully.',
            204
        );
    }

    /**
     * Reorder fields.
     */
    public function reorder(
        Request $request,
        TransactionType $transactionType
    ): JsonResponse {
        $data = $request->validate([
            'fields' => [
                'required',
                'array',
                'min:1',
            ],

            'fields.*.id' => [
                'required',
                'integer',
            ],

            'fields.*.field_order' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $existingIds = $transactionType
            ->fields()
            ->pluck('id')
            ->all();

        foreach ($data['fields'] as $item) {
            if (
                ! in_array(
                    $item['id'],
                    $existingIds,
                    true
                )
            ) {
                return $this->error(
                    'One of the fields does not belong to this transaction type.',
                    422
                );
            }
        }

        /*
         * Use temporary values first to avoid
         * order conflicts while swapping fields.
         */
        foreach (
            $data['fields'] as $index => $item
        ) {
            TransactionTypeField::whereKey(
                $item['id']
            )->update([
                'field_order' =>
                    1000 + $index,
            ]);
        }

        /*
         * Apply final order.
         */
        foreach (
            $data['fields'] as $item
        ) {
            TransactionTypeField::whereKey(
                $item['id']
            )->update([
                'field_order' =>
                    $item['field_order'],
            ]);
        }

        $fields = $transactionType
            ->fields()
            ->orderBy('field_order')
            ->get();

        return $this->success(
            TransactionTypeFieldResource::collection(
                $fields
            ),
            'Transaction type fields reordered successfully.'
        );
    }
}