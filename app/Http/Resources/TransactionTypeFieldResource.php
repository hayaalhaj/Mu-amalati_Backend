<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionTypeFieldResource extends JsonResource
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

            'transaction_type_id' =>
                $this->transaction_type_id,

            'name_en' =>
                $this->name_en,

            'field_type' =>
                $this->field_type,

            'is_required' =>
                (bool) $this->is_required,

            'placeholder_en' =>
                $this->placeholder_en,

            'placeholder_ar' =>
                $this->placeholder_ar,

            'options' =>
                $this->options,

            'field_order' =>
                $this->field_order,
        ];
    }
}