<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finalize', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'copy_items' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['copy_items' => $this->boolean('copy_items')]);
    }
}
