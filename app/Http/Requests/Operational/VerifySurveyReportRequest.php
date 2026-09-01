<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifySurveyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verifyReport', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ];
    }
}
