<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowStepRequest extends FormRequest
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
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'step_order' => ['sometimes', 'required', 'integer', 'min:1'],
            'is_final' => ['sometimes', 'boolean'],
        ];
    }
}
