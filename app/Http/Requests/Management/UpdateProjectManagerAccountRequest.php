<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProjectManagerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-project-managers') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('projectManager');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'username' => Str::lower(trim((string) $this->input('username'))),
        ]);
    }
}
