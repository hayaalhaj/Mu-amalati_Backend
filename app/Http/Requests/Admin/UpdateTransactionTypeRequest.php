<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionTypeRequest extends FormRequest
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
        $typeId = $this->route('transaction_type');

        return [
            'name_en' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('transaction_types', 'name_en')->ignore($typeId)],
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'destination_department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
