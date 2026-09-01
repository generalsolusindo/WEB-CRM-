<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;

class SaveSurveyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('workReport', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:5000'],
            'items' => ['array'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
