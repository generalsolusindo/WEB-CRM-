<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class BriefSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('brief', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'briefing' => ['required', 'string', 'max:5000'],
        ];
    }
}
