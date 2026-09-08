<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'transaction_number' =>
                $this->transaction_number,

            'title' =>
                $this->title,

            'description' =>
                $this->description,
'priority' =>
    $this->priority->value,

'status' =>
    $this->status->value,

'start_date' =>
    $this->start_date,

'end_date' =>
    $this->end_date,
            'creator' => $this->whenLoaded(
                'creator',
                fn () => $this->creator
                    ? [
                        'id' =>
                            $this->creator->id,

                        'name' =>
                            $this->creator->name,

                        'job_title' =>
                            $this->creator->job_title,

                        'department' =>
                            $this->creator->department
                                ? [
                                    'id' =>
                                        $this->creator
                                            ->department
                                            ->id,

                                    'name' =>
                                        $this->creator
                                            ->department
                                            ->name,
                                ]
                                : null,
                    ]
                    : null
            ),

            'transaction_type' => $this->whenLoaded(
                'transactionType',
                fn () => $this->transactionType
                    ? [
                        'id' =>
                            $this->transactionType->id,

                        'name_en' =>
                            $this->transactionType->name_en,

                        'name_ar' =>
                            $this->transactionType->name_ar,
                    ]
                    : null
            ),

            'source_department' => $this->whenLoaded(
                'sourceDepartment',
                fn () => $this->sourceDepartment
                    ? [
                        'id' =>
                            $this->sourceDepartment->id,

                        'name' =>
                            $this->sourceDepartment->name,
                    ]
                    : null
            ),

            'destination_department' => $this->whenLoaded(
                'destinationDepartment',
                fn () => $this->destinationDepartment
                    ? [
                        'id' =>
                            $this->destinationDepartment->id,

                        'name' =>
                            $this->destinationDepartment->name,
                    ]
                    : null
            ),

            'current_department' => $this->whenLoaded(
                'currentDepartment',
                fn () => $this->currentDepartment
                    ? [
                        'id' =>
                            $this->currentDepartment->id,

                        'name' =>
                            $this->currentDepartment->name,
                    ]
                    : null
            ),

            'current_workflow_step' => $this->whenLoaded(
                'currentWorkflowStep',
                fn () => $this->currentWorkflowStep
                    ? [
                        'id' =>
                            $this->currentWorkflowStep->id,

                        'name' =>
                            $this->currentWorkflowStep->name,

                        'step_order' =>
                            $this->currentWorkflowStep->step_order,

                        'status' =>
                            $this->currentWorkflowStep
                                ->status
                                ->value,
                    ]
                    : null
            ),

            'workflow_steps' =>
                TransactionWorkflowStepResource::collection(
                    $this->whenLoaded(
                        'workflowSteps'
                    )
                ),

            /*
             * Dynamic field values configured by Admin.
             */
            'field_values' => $this->whenLoaded(
                'fieldValues',
                function () {
                    return $this->fieldValues
                        ->map(
                            function ($fieldValue) {
                                return [
                                    'id' =>
                                        $fieldValue->id,

                                    'field_id' =>
                                        $fieldValue
                                            ->transaction_type_field_id,

                                    'field' =>
                                        $fieldValue->field
                                            ? [
                                                'id' =>
                                                    $fieldValue
                                                        ->field
                                                        ->id,

                                                'name_en' =>
                                                    $fieldValue
                                                        ->field
                                                        ->name_en,

                                                'field_type' =>
                                                    $fieldValue
                                                        ->field
                                                        ->field_type,

                                                'is_required' =>
                                                    (bool) $fieldValue
                                                        ->field
                                                        ->is_required,
                                            ]
                                            : null,

                                    'value' =>
                                        $fieldValue->value,
                                ];
                            }
                        )
                        ->values();
                }
            ),

            'attachments' =>
                TransactionAttachmentResource::collection(
                    $this->whenLoaded(
                        'attachments'
                    )
                ),

            'histories' => $this->when(
                $this->relationLoaded(
                    'histories'
                ),
                function () {
                    return TransactionHistoryResource::collection(
                        $this->histories
                    );
                }
            ),

            'submitted_at' =>
                $this->submitted_at?->toISOString(),

            'approved_at' =>
                $this->approved_at?->toISOString(),

            'rejected_at' =>
                $this->rejected_at?->toISOString(),

            'returned_at' =>
                $this->returned_at?->toISOString(),

            'completed_at' =>
                $this->completed_at?->toISOString(),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}