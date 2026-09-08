<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionAttachmentRequest;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionAttachmentResource;
use App\Http\Resources\TransactionHistoryResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransactionWorkflowStepResource;
use App\Http\Responses\ApiResponse;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\TransactionFieldValue;
use App\Models\TransactionHistory;
use App\Models\TransactionType;
use App\Models\TransactionTypeField;
use App\Services\TransactionNumberService;
use App\Services\TransactionSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TransactionNumberService $numberService,
        private readonly TransactionSubmissionService $submissionService,
    ) {}

    /**
     * List transactions scoped to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min(
            $request->integer('per_page', 10),
            50
        );

        $query = Transaction::query()
            ->with([
                'creator.department',
                'transactionType',
                'sourceDepartment',
                'destinationDepartment',
                'currentDepartment',
                'currentWorkflowStep',
            ]);

        if ($user->isEmployee()) {
            $query->where(
                'created_by',
                $user->id
            );
        }

        if ($user->isManager()) {
            $departmentIds =
                $user->managedDepartmentIds();

            $query->where(function ($q) use (
                $departmentIds,
                $user
            ) {
                $q->whereIn(
                    'current_department_id',
                    $departmentIds
                )->orWhere(
                    'created_by',
                    $user->id
                );
            });
        }

        $transactions = $query
            ->filter($request->all())
            ->paginate($perPage);

        return $this->successCollection(
            TransactionResource::collection(
                $transactions
            ),
            200,
            [
                'current_page' =>
                    $transactions->currentPage(),

                'per_page' =>
                    $transactions->perPage(),

                'total' =>
                    $transactions->total(),

                'last_page' =>
                    $transactions->lastPage(),
            ]
        );
    }

    /**
     * Create a draft transaction.
     */
    public function store(
        StoreTransactionRequest $request
    ): JsonResponse {
        $this->authorize(
            'create',
            Transaction::class
        );

        $user = $request->user();

        /*
         * Employee and manager must be checked in
         * before they can create a transaction.
         *
         * Admin is intentionally excluded because
         * admins are not allowed to create transactions.
         */
        if (
            $user->isEmployee() ||
            $user->isManager()
        ) {
            $attendance =
                $user->todayAttendance();

            /*
             * Reject when:
             * - there is no attendance record
             * - there is no check-in
             * - the user already checked out
             */
            if (
                ! $attendance ||
                ! $attendance->check_in_at ||
                $attendance->check_out_at
            ) {
                return $this->error(
                    'You must check in before creating a transaction.',
                    422
                );
            }
        }

        $type = TransactionType::findOrFail(
            $request->transaction_type_id
        );

        if (! $type->is_active) {
            return $this->error(
                'The selected transaction type is not active.',
                422
            );
        }

        $fields = $type
            ->fields()
            ->orderBy('field_order')
            ->get();

        $dynamicFields = $request->input(
            'dynamic_fields',
            []
        );

        foreach ($fields as $field) {
            $value =
                $dynamicFields[$field->id]
                ?? null;

            if (
                $field->is_required &&
                $field->field_type !== 'file' &&
                (
                    $value === null ||
                    $value === ''
                )
            ) {
                return $this->error(
                    "{$field->name_en} is required.",
                    422
                );
            }
        }

        $transaction = DB::transaction(
            function () use (
                $request,
                $user,
                $type,
                $fields,
                $dynamicFields
            ) {
                $transaction =
                    Transaction::create([
                        'transaction_number' =>
                            $this->numberService
                                ->generate(),

                        'created_by' =>
                            $user->id,

                        'transaction_type_id' =>
                            $type->id,

                        'source_department_id' =>
                            $user->department_id,

                        'destination_department_id' =>
                            $type->destination_department_id,

                        'title' =>
                            $request->title,

                        'description' =>
                            $request->description,

                        'start_date' =>
                            $request->start_date,

                        'end_date' =>
                            $request->end_date,

                        'priority' =>
                            $request->priority,

                        'status' =>
                            'draft',
                    ]);

                foreach ($fields as $field) {
                    $value =
                        $dynamicFields[$field->id]
                        ?? null;

                    if (
                        $field->field_type === 'file'
                    ) {
                        continue;
                    }

                    if (
                        $value === null ||
                        $value === ''
                    ) {
                        continue;
                    }

                    TransactionFieldValue::create([
                        'transaction_id' =>
                            $transaction->id,

                        'transaction_type_field_id' =>
                            $field->id,

                        'value' =>
                            is_array($value)
                                ? json_encode(
                                    $value,
                                    JSON_UNESCAPED_UNICODE
                                )
                                : (string) $value,
                    ]);
                }

                $this->storeAttachments(
                    $request,
                    $transaction,
                    $user
                );

                TransactionHistory::create([
                    'transaction_id' =>
                        $transaction->id,

                    'performed_by' =>
                        $user->id,

                    'action' =>
                        'created',

                    'old_status' =>
                        null,

                    'new_status' =>
                        'draft',

                    'comment' =>
                        'Transaction created.',
                ]);

                return $transaction;
            }
        );

        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'attachments.transactionTypeField',
            'fieldValues.field',
        ]);

        return $this->success(
            new TransactionResource(
                $transaction
            ),
            'Transaction created successfully.',
            201
        );
    }

    /**
     * Show a single transaction.
     */
    public function show(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'view',
            $transaction
        );

        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'currentWorkflowStep',
            'workflowSteps.department',
            'attachments.transactionTypeField',
            'fieldValues.field',
        ]);

        return $this->success(
            new TransactionResource(
                $transaction
            )
        );
    }

    /**
     * Update a draft or returned transaction.
     */
    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'update',
            $transaction
        );

        $user = $request->user();

        /*
         * User must be checked in to edit
         * their transaction as well.
         *
         * This applies only to employees
         * and managers.
         */
        if (
            $user->isEmployee() ||
            $user->isManager()
        ) {
            $attendance =
                $user->todayAttendance();

            if (
                ! $attendance ||
                ! $attendance->check_in_at ||
                $attendance->check_out_at
            ) {
                return $this->error(
                    'You must check in before editing a transaction.',
                    422
                );
            }
        }

        $validated = $request->validated();

        /*
         * Dynamic fields must not be sent directly
         * to the transactions table.
         */
        $dynamicFields =
            $validated['dynamic_fields'] ?? [];

        unset(
            $validated['dynamic_fields']
        );

        /*
         * The transaction type should not be changed
         * during a normal edit.
         */
        unset(
            $validated['transaction_type_id']
        );

        $transaction = DB::transaction(
            function () use (
                $transaction,
                $validated,
                $dynamicFields,
                $user
            ) {
                /*
                 * Update normal transaction fields.
                 */
                $transaction->update(
                    $validated
                );

                /*
                 * Load the fields configured for
                 * this transaction type.
                 */
                $fields = $transaction
                    ->transactionType
                    ->fields()
                    ->orderBy('field_order')
                    ->get();

                /*
                 * Update dynamic field values.
                 */
                foreach ($fields as $field) {
                    /*
                     * Files are handled separately
                     * through the attachments endpoint.
                     */
                    if (
                        $field->field_type === 'file'
                    ) {
                        continue;
                    }

                    $fieldId =
                        $field->id;

                    $value =
                        $dynamicFields[$fieldId]
                        ?? null;

                    /*
                     * If the value was removed,
                     * remove the old saved value.
                     */
                    if (
                        $value === null ||
                        $value === ''
                    ) {
                        TransactionFieldValue::where(
                            'transaction_id',
                            $transaction->id
                        )
                            ->where(
                                'transaction_type_field_id',
                                $fieldId
                            )
                            ->delete();

                        continue;
                    }

                    /*
                     * Update existing value or create it.
                     */
                    TransactionFieldValue::updateOrCreate(
                        [
                            'transaction_id' =>
                                $transaction->id,

                            'transaction_type_field_id' =>
                                $fieldId,
                        ],
                        [
                            'value' =>
                                is_array($value)
                                    ? json_encode(
                                        $value,
                                        JSON_UNESCAPED_UNICODE
                                    )
                                    : (string) $value,
                        ]
                    );
                }

                /*
                 * History.
                 */
                TransactionHistory::create([
                    'transaction_id' =>
                        $transaction->id,

                    'performed_by' =>
                        $user->id,

                    'action' =>
                        'updated',

                    'old_status' =>
                        $transaction->status->value,

                    'new_status' =>
                        $transaction->status->value,

                    'comment' =>
                        'Transaction updated.',
                ]);

                return $transaction;
            }
        );

        $transaction->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'currentWorkflowStep',
            'workflowSteps.department',
            'attachments.transactionTypeField',
            'fieldValues.field',
        ]);

        return $this->success(
            new TransactionResource(
                $transaction
            ),
            'Transaction updated successfully.'
        );
    }

    /**
     * Delete a draft transaction.
     */
    public function destroy(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'delete',
            $transaction
        );

        DB::transaction(
            function () use ($transaction) {
                foreach (
                    $transaction->attachments
                    as $attachment
                ) {
                    Storage::disk('public')
                        ->delete(
                            $attachment->file_path
                        );

                    $attachment->delete();
                }

                $transaction->delete();
            }
        );

        return $this->success(
            null,
            'Transaction deleted successfully.',
            204
        );
    }

    /**
     * Submit a draft transaction.
     */
    public function submit(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'submit',
            $transaction
        );

        try {
            $result =
                $this->submissionService->submit(
                    $transaction,
                    $request->user()
                );
        } catch (RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                409
            );
        }

        $result['transaction']->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'currentWorkflowStep',
            'workflowSteps.department',
            'attachments.transactionTypeField',
            'fieldValues.field',
        ]);

        return $this->success(
            new TransactionResource(
                $result['transaction']
            ),
            $result['message']
        );
    }

    /**
     * Resubmit a returned transaction.
     */
    public function resubmit(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'submit',
            $transaction
        );

        try {
            $result =
                $this->submissionService->resubmit(
                    $transaction,
                    $request->user()
                );
        } catch (RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                409
            );
        }

        $result['transaction']->load([
            'creator.department',
            'transactionType',
            'sourceDepartment',
            'destinationDepartment',
            'currentDepartment',
            'currentWorkflowStep',
            'workflowSteps.department',
            'attachments.transactionTypeField',
            'fieldValues.field',
        ]);

        return $this->success(
            new TransactionResource(
                $result['transaction']
            ),
            $result['message']
        );
    }

    /**
     * Show the transaction history.
     */
    public function history(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'view',
            $transaction
        );

        $history = $transaction
            ->histories()
            ->with('performer')
            ->get();

        return $this->success(
            TransactionHistoryResource::collection(
                $history
            )
        );
    }

    /**
     * Show the transaction workflow.
     */
    public function workflow(
        Request $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'view',
            $transaction
        );

        $steps = $transaction
            ->workflowSteps()
            ->with(
                'department',
                'reviewer'
            )
            ->get();

        return $this->success(
            TransactionWorkflowStepResource::collection(
                $steps
            )
        );
    }

    /**
     * Upload attachments to a transaction.
     */
    public function attachments(
        StoreTransactionAttachmentRequest $request,
        Transaction $transaction
    ): JsonResponse {
        $this->authorize(
            'manageAttachments',
            $transaction
        );

        $user = $request->user();

        /*
         * User must be checked in to manage
         * transaction attachments.
         */
        if (
            $user->isEmployee() ||
            $user->isManager()
        ) {
            $attendance =
                $user->todayAttendance();

            if (
                ! $attendance ||
                ! $attendance->check_in_at ||
                $attendance->check_out_at
            ) {
                return $this->error(
                    'You must check in before managing transaction attachments.',
                    422
                );
            }
        }

        $existing = $transaction
            ->attachments()
            ->count();

        $incoming = count(
            $request->file(
                'attachments',
                []
            )
        );

        if (
            $existing + $incoming > 5
        ) {
            return $this->error(
                'A transaction can have a maximum of 5 attachments.',
                422
            );
        }

        $fieldIds = $request->input(
            'field_ids',
            []
        );

        try {
            $attachments = DB::transaction(
                function () use (
                    $request,
                    $transaction,
                    $user,
                    $fieldIds
                ) {
                    $created = [];

                    foreach (
                        $request->file('attachments')
                        as $index => $file
                    ) {
                        $fieldId =
                            $fieldIds[$index]
                            ?? null;

                        /*
                         * If this is a Dynamic File Field,
                         * verify ownership and type.
                         */
                        if ($fieldId) {
                            $field =
                                TransactionTypeField::query()
                                    ->whereKey(
                                        $fieldId
                                    )
                                    ->where(
                                        'transaction_type_id',
                                        $transaction->transaction_type_id
                                    )
                                    ->where(
                                        'field_type',
                                        'file'
                                    )
                                    ->first();

                            if (! $field) {
                                throw new RuntimeException(
                                    'The selected file field does not belong to this transaction type.'
                                );
                            }
                        }

                        $path =
                            $file->storeAs(
                                'transactions',
                                $file->getClientOriginalName(),
                                'public'
                            );

                        $record =
                            TransactionAttachment::create([
                                'transaction_id' =>
                                    $transaction->id,

                                'transaction_type_field_id' =>
                                    $fieldId,

                                'uploaded_by' =>
                                    $user->id,

                                'original_name' =>
                                    $file->getClientOriginalName(),

                                'file_path' =>
                                    $path,

                                'mime_type' =>
                                    $file->getMimeType(),

                                'file_size' =>
                                    $file->getSize(),
                            ]);

                        /*
                         * Load relationships while $record
                         * is still an Eloquent Model.
                         */
                        $record->load([
                            'transactionTypeField',
                            'uploader',
                        ]);

                        $created[] =
                            $record;
                    }

                    TransactionHistory::create([
                        'transaction_id' =>
                            $transaction->id,

                        'performed_by' =>
                            $user->id,

                        'action' =>
                            'attachment_added',

                        'comment' =>
                            'Attachment(s) added.',
                    ]);

                    return $created;
                }
            );
        } catch (RuntimeException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        }

        return $this->success(
            TransactionAttachmentResource::collection(
                $attachments
            ),
            'Attachments uploaded successfully.',
            201
        );
    }

    /**
     * Delete an attachment.
     */
    public function destroyAttachment(
        Request $request,
        Transaction $transaction,
        TransactionAttachment $attachment
    ): JsonResponse {
        $this->authorize(
            'delete',
            $attachment
        );

        if (
            $attachment->transaction_id !==
            $transaction->id
        ) {
            return $this->notFound(
                'Attachment not found for this transaction.'
            );
        }

        DB::transaction(
            function () use (
                $attachment,
                $request
            ) {
                Storage::disk('public')
                    ->delete(
                        $attachment->file_path
                    );

                $attachment->delete();

                TransactionHistory::create([
                    'transaction_id' =>
                        $attachment->transaction_id,

                    'performed_by' =>
                        $request->user()->id,

                    'action' =>
                        'attachment_removed',

                    'comment' =>
                        'Attachment removed.',
                ]);
            }
        );

        return $this->success(
            null,
            'Attachment deleted successfully.',
            204
        );
    }

    /**
     * Store normal attachments from creation.
     */
    protected function storeAttachments(
        Request $request,
        Transaction $transaction,
        $user
    ): void {
        if (
            ! $request->hasFile('attachments')
        ) {
            return;
        }

        $fieldIds = $request->input(
            'field_ids',
            []
        );

        foreach (
            $request->file('attachments') as $index => $file
        ) {
            $fieldId =
                $fieldIds[$index] ?? null;

            /*
             * If the uploaded file belongs to a
             * Dynamic File Field, verify that the field
             * belongs to this transaction type and is
             * actually a file field.
             */
            if ($fieldId) {
                $field = TransactionTypeField::query()
                    ->whereKey($fieldId)
                    ->where(
                        'transaction_type_id',
                        $transaction->transaction_type_id
                    )
                    ->where(
                        'field_type',
                        'file'
                    )
                    ->first();

                if (! $field) {
                    throw new RuntimeException(
                        'The selected file field does not belong to this transaction type.'
                    );
                }
            }

            $path =
                $file->storeAs(
                    'transactions',
                    $file->getClientOriginalName(),
                    'public'
                );

            TransactionAttachment::create([
                'transaction_id' =>
                    $transaction->id,

                'transaction_type_field_id' =>
                    $fieldId,

                'uploaded_by' =>
                    $user->id,

                'original_name' =>
                    $file->getClientOriginalName(),

                'file_path' =>
                    $path,

                'mime_type' =>
                    $file->getMimeType(),

                'file_size' =>
                    $file->getSize(),
            ]);
        }
    }
}