<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionPriority;
use App\Models\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
        $transactionTypeId = $this->input(
            'transaction_type_id'
        );

        $rules = [
            'transaction_type_id' => [
                'required',
                'integer',
                'exists:transaction_types,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'max:5000',
            ],

            'priority' => [
                'required',
                Rule::enum(
                    TransactionPriority::class
                ),
            ],

            /*
             * Dynamic fields are optional at the
             * transaction level.
             */
            'dynamic_fields' => [
                'nullable',
                'array',
            ],

            'attachments' => [
                'nullable',
                'array',
                'max:5',
            ],

            'attachments.*' => [
                'file',
                'max:10240',
            ],
        ];

        if (! $transactionTypeId) {
            return $rules;
        }

        $transactionType =
            TransactionType::with('fields')
                ->find($transactionTypeId);

        if (! $transactionType) {
            return $rules;
        }

        $dynamicFields =
            $transactionType->fields;

        /*
         * --------------------------------------------------
         * New configurable transaction types
         * --------------------------------------------------
         *
         * If the Admin configured fields for this
         * transaction type, validate those fields.
         */
        if ($dynamicFields->isNotEmpty()) {
            foreach (
                $dynamicFields as $field
            ) {
                $fieldPath =
                    "dynamic_fields.{$field->id}";

                $fieldRules = [];

                $fieldRules[] =
                    $field->is_required
                        ? 'required'
                        : 'nullable';

                switch (
                    $field->field_type
                ) {
                    case 'text':
                        $fieldRules[] =
                            'string';

                        $fieldRules[] =
                            'max:5000';
                        break;

                    case 'textarea':
                        $fieldRules[] =
                            'string';

                        $fieldRules[] =
                            'max:10000';
                        break;

                    case 'number':
                        $fieldRules[] =
                            'numeric';
                        break;

                    case 'currency':
                        $fieldRules[] =
                            'numeric';

                        $fieldRules[] =
                            'min:0';
                        break;

                    case 'date':
                        $fieldRules[] =
                            'date';
                        break;

                    case 'datetime':
                        $fieldRules[] =
                            'date';
                        break;

                    case 'email':
                        $fieldRules[] =
                            'email';

                        $fieldRules[] =
                            'max:255';
                        break;

                    case 'phone':
                        $fieldRules[] =
                            'string';

                        $fieldRules[] =
                            'max:30';
                        break;

                    case 'checkbox':
                        $fieldRules[] =
                            'boolean';
                        break;

                    case 'select':
                    case 'radio':
                        $fieldRules[] =
                            'string';

                        if (
                            is_array(
                                $field->options
                            ) &&
                            count(
                                $field->options
                            ) > 0
                        ) {
                            $fieldRules[] =
                                Rule::in(
                                    $field->options
                                );
                        }

                        break;

                    case 'file':
                        /*
                         * File fields are handled by the
                         * existing attachment endpoint.
                         */
                        $fieldRules = [
                            'nullable',
                        ];
                        break;

                    default:
                        $fieldRules[] =
                            'string';

                        $fieldRules[] =
                            'max:5000';
                        break;
                }

                $rules[$fieldPath] =
                    $fieldRules;
            }

            return $rules;
        }

        /*
         * --------------------------------------------------
         * Legacy Leave Request
         * --------------------------------------------------
         *
         * Keep the old Leave Request behavior only when
         * this transaction type has no dynamic fields.
         */
        $isLegacyLeaveRequest =
            $transactionType->name_en ===
            'Leave Request';

        if ($isLegacyLeaveRequest) {
            $rules['start_date'] = [
                'required',
                'date',
            ];

            $rules['end_date'] = [
                'required',
                'date',
                'after_or_equal:start_date',
            ];
        } else {
            /*
             * Keep old date fields optional for all other
             * legacy transaction types.
             */
            $rules['start_date'] = [
                'nullable',
                'date',
            ];

            $rules['end_date'] = [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ];
        }

        return $rules;
    }
}