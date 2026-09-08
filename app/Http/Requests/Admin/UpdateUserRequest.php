<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['sometimes', 'required', Rule::enum(UserStatus::class)],
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
            $role = $validator->getData()['role'] ?? $this->route('user')?->role?->value;

            if (in_array($role, [UserRole::Employee->value, UserRole::Manager->value], true)
                && ! ($validator->getData()['department_id'] ?? $this->route('user')?->department_id)) {
                $validator->errors()->add('department_id', 'department_id is required for employees and managers.');
            }

            $annual = (int) ($validator->getData()['annual_leave_days'] ?? $this->route('user')?->annual_leave_days ?? 30);
            $used = (int) ($validator->getData()['used_leave_days'] ?? $this->route('user')?->used_leave_days ?? 0);

            if ($used > $annual) {
                $validator->errors()->add('used_leave_days', 'used_leave_days cannot exceed annual_leave_days.');
            }
        });
    }
}
