<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class AssignTechniciansRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && ($this->user()?->can('manageResources', $project) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'technician_ids' => ['required', 'array', 'min:1'],
            'technician_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'leader_id' => ['required', 'integer'],
        ];
    }
}
