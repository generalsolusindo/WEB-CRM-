<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueSurveyInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('handleFinance', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'due_date' => ['nullable', 'date'],
            'tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')->where('is_active', true)],
            'finance_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['tax_id' => $this->input('tax_id') ?: null]);
    }
}
