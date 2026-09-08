<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TransactionAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>
                $this->id,

            'transaction_id' =>
                $this->transaction_id,

            'transaction_type_field_id' =>
                $this->transaction_type_field_id,

            'original_name' =>
                $this->original_name,

            'mime_type' =>
                $this->mime_type,

            'file_size' =>
                $this->file_size,

            'url' =>
                Storage::disk('public')
                    ->url(
                        $this->file_path
                    ),

            'transaction_type_field' =>
                $this->whenLoaded(
                    'transactionTypeField',
                    fn () =>
                        $this->transactionTypeField
                            ? [
                                'id' =>
                                    $this->transactionTypeField->id,

                                'name_en' =>
                                    $this->transactionTypeField->name_en,

                                'field_type' =>
                                    $this->transactionTypeField->field_type,
                            ]
                            : null
                ),

            'uploader' =>
                $this->whenLoaded(
                    'uploader',
                    fn () =>
                        $this->uploader
                            ? [
                                'id' =>
                                    $this->uploader->id,

                                'name' =>
                                    $this->uploader->name,
                            ]
                            : null
                ),

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}