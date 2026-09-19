<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSow', $this->route('project')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'number' => ['nullable', 'string', 'max:100'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'site_location' => ['nullable', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'execution_date' => ['nullable', 'string', 'max:255'],
            'background' => ['nullable', 'string', 'max:4000'],
            'scope_pre_work' => ['nullable', 'string', 'max:4000'],
            'scope_other' => ['nullable', 'string', 'max:4000'],
            'responsibilities' => ['nullable', 'string', 'max:4000'],
            'schedule_duration' => ['nullable', 'string', 'max:100'],
            'schedule_start_date' => ['nullable', 'date'],
            'schedule_end_date' => ['nullable', 'date', 'after_or_equal:schedule_start_date'],
            'safety' => ['nullable', 'string', 'max:4000'],
            'payment_terms' => ['nullable', 'string', 'max:4000'],
            'output' => ['nullable', 'string', 'max:4000'],
            'warranty' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'closing' => ['nullable', 'string', 'max:4000'],
            'section_visibility' => ['nullable', 'array'],
            'section_visibility.*' => ['boolean'],
            'custom_sections' => ['nullable', 'array', 'max:50'],
            'custom_sections.*.title' => ['required', 'string', 'max:255'],
            'custom_sections.*.content' => ['nullable', 'string', 'max:4000'],
            'custom_sections.*.after' => ['required', Rule::in([
                'general', 'background', 'scope', 'responsibilities', 'schedule', 'safety',
                'payment', 'output', 'warranty', 'notes', 'contacts', 'closing',
            ])],
            'custom_sections.*.active' => ['boolean'],
            'scope_sections' => ['nullable', 'array', 'max:50'],
            'scope_sections.*.id' => ['nullable', 'integer'],
            'scope_sections.*.title' => ['required', 'string', 'max:255'],
            'scope_sections.*.content' => ['nullable', 'string', 'max:4000'],
            'technician_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'technician')->where('vendor_id', $project?->vendor_id),
            ],
            'technician_team_note' => ['nullable', 'string', 'max:500'],
            'client_pic_name' => ['nullable', 'string', 'max:255'],
            'client_pic_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
