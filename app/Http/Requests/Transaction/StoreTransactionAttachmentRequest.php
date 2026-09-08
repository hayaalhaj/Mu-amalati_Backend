<?php

namespace App\Http\Requests\Transaction;

use App\Models\TransactionTypeField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachments' => [
                'required',
                'array',
                'max:5',
            ],

            'attachments.*' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:5120',
            ],

            'field_ids' => [
                'nullable',
                'array',
            ],

            'field_ids.*' => [
                'nullable',
                'integer',
                Rule::exists(
                    'transaction_type_fields',
                    'id'
                ),
            ],
        ];
    }
}