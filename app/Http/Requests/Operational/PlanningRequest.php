<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class PlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && ($this->user()?->can('update', $project) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date', 'after_or_equal:planned_start'],
        ];
    }
}
