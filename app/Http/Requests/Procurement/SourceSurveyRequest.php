<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class SourceSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('source', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'surveyor_id' => ['required', 'integer', 'exists:users,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['vendor_id' => $this->input('vendor_id') ?: null]);
    }
}
