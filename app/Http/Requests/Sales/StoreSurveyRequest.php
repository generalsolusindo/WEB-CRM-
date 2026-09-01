<?php

namespace App\Http\Requests\Sales;

use App\Enums\SurveyDeliveryMode;
use App\Models\Survey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Survey::class, $this->route('lead')]) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'site_address' => ['required', 'string', 'max:2000'],
            'site_region' => ['required', 'string', 'max:160'],
            'delivery_mode' => ['required', Rule::enum(SurveyDeliveryMode::class)],
            'billable' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['billable' => $this->boolean('billable')]);
    }
}
