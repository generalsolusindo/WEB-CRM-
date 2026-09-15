<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTechnicianAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-technicians') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('technician');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'nik' => ['nullable', 'string', 'max:50'],
            'ktp_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'vendor_id' => $this->input('vendor_id') ?: null,
            'username' => Str::lower(trim((string) $this->input('username'))),
        ]);
    }
}
