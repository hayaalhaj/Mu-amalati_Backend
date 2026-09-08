<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionTypeRequest extends FormRequest
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
            'name_en' => ['required', 'string', 'max:255', 'unique:transaction_types,name_en'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'destination_department_id' => ['required', 'integer', 'exists:departments,id'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
