<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DelegateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delegate', $this->route('project')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'project_manager')->where('is_active', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['project_manager_id' => $this->input('project_manager_id') ?: null]);
    }
}
