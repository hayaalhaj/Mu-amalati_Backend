<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderWorkflowStepsRequest extends FormRequest
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
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.id' => ['required', 'integer', 'distinct', 'exists:transaction_type_workflow_steps,id'],
            'steps.*.step_order' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
