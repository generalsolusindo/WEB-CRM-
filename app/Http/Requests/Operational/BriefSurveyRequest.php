<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'surveyor_ids' => ['required', 'array', 'min:1'],
            'surveyor_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where(fn ($query) => $query->where('role', 'technician')->orWhere('can_surveyor', true))
                    ->where('is_active', true)),
            ],
            'leader_id' => ['required', 'integer', Rule::in($this->input('surveyor_ids', []))],
        ];
    }

    public function messages(): array
    {
        return [
            'surveyor_ids.required' => 'Pilih minimal satu surveyor.',
            'leader_id.in' => 'Leader harus salah satu anggota tim yang dipilih.',
        ];
    }
}
