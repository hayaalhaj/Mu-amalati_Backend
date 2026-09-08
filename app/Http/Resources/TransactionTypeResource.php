<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionTypeResource extends JsonResource
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
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'requires_attachment' => $this->requires_attachment,
            'is_active' => $this->when($request->routeIs('admin.transactionTypes.*') || $request->routeIs('admin.transaction-types.*'), $this->is_active),
            'destination_department' => $this->whenLoaded('destinationDepartment', fn () => $this->destinationDepartment ? [
                'id' => $this->destinationDepartment->id,
                'name' => $this->destinationDepartment->name,
            ] : null),
            'workflow_steps' => TransactionTypeWorkflowStepResource::collection($this->whenLoaded('workflowSteps')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
