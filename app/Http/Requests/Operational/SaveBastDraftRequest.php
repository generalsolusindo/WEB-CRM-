<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class SaveBastDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageBastDraft', $this->route('project')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:100'],
            'event_date' => ['nullable', 'date'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'work_description' => ['nullable', 'string', 'max:4000'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'pic_position' => ['nullable', 'string', 'max:255'],
            'pic_address' => ['nullable', 'string', 'max:500'],
            'leader_name' => ['nullable', 'string', 'max:255'],
            'leader_position' => ['nullable', 'string', 'max:255'],
        ];
    }
}
