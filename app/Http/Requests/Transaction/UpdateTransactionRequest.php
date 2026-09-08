<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionPriority;
use App\Models\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
        $transactionTypeId =
            $this->input(
                'transaction_type_id'
            );

        /*
         * If transaction_type_id is not sent during update,
         * use the existing transaction type from the route.
         */
        if (! $transactionTypeId) {
            $transaction =
                $this->route('transaction');

            if ($transaction) {
                $transactionTypeId =
                    $transaction->transaction_type_id;
            }
        }

        $rules = [
            'transaction_type_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:transaction_types,id',
            ],

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
                'max:5000',
            ],

            'priority' => [
                'sometimes',
                'required',
                Rule::enum(
                    TransactionPriority::class
                ),
            ],

            /*
             * Dynamic fields are optional globally.
             */
            'dynamic_fields' => [
                'nullable',
                'array',
            ],

            /*
             * Legacy fields.
             */
            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
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

        $fields =
            $transactionType->fields;

        /*
         * --------------------------------------------------
         * Dynamic Fields
         * --------------------------------------------------
         */
        if ($fields->isNotEmpty()) {
            foreach ($fields as $field) {
                $fieldPath =
                    "dynamic_fields.{$field->id}";

                $fieldRules = [];

                /*
                 * During update, required fields are still
                 * required. File fields are treated specially
                 * because an existing attachment may already
                 * satisfy the field.
                 */
                if (
                    $field->field_type === 'file'
                ) {
                    $fieldRules[] =
                        'nullable';
                } else {
                    $fieldRules[] =
                        $field->is_required
                            ? 'required'
                            : 'nullable';
                }

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
                         * Files are uploaded through the
                         * attachments endpoint.
                         */
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

            /*
             * Dynamic Fields replace legacy leave dates
             * for this transaction type.
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

            return $rules;
        }

        /*
         * --------------------------------------------------
         * Legacy Leave Request
         * --------------------------------------------------
         *
         * Keep the old behavior when there are no Dynamic
         * Fields and the transaction type is Leave Request.
         */
        if (
            $transactionType->name_en ===
            'Leave Request'
        ) {
            $rules['start_date'] = [
                'sometimes',
                'required',
                'date',
            ];

            $rules['end_date'] = [
                'sometimes',
                'required',
                'date',
                'after_or_equal:start_date',
            ];
        }

        return $rules;
    }
}