<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ClearSurveyFinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('handleFinance', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'finance_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
