<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
        $departmentId = $this->route('department');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($departmentId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
