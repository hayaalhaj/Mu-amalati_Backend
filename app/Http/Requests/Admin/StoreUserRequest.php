<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'annual_leave_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'used_leave_days' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $role = $this->input('role');

            if (in_array($role, [UserRole::Employee->value, UserRole::Manager->value], true) && ! $this->input('department_id')) {
                $validator->errors()->add('department_id', 'department_id is required for employees and managers.');
            }

            $annual = (int) $this->input('annual_leave_days', 30);
            $used = (int) $this->input('used_leave_days', 0);

            if ($used > $annual) {
                $validator->errors()->add('used_leave_days', 'used_leave_days cannot exceed annual_leave_days.');
            }
        });
    }
}
