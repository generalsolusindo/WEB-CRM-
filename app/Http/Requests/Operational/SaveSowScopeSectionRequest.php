<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class SaveSowScopeSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSowAssets', $this->route('project')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
