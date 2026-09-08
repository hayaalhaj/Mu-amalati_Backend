<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionHistoryResource extends JsonResource
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
            'action' => $this->action->value,
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'workflow_step_name' => $this->workflow_step_name,
            'comment' => $this->comment,
            'performer' => $this->whenLoaded('performer', fn () => $this->performer ? [
                'id' => $this->performer->id,
                'name' => $this->performer->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
