<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->ignore($target->id)],
            // Password opsional — kosongkan kalau tidak ingin ganti.
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['sometimes', 'boolean'],
            // Role sengaja tidak bisa diubah di sini — ganti role berarti ganti seluruh
            // akses & asumsi data (mis. akun vendor/technician butuh vendor_id terkait).
            // Kalau memang perlu ganti role, buat akun baru saja.
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
