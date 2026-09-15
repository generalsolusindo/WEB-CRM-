<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateVendorAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vendor-accounts') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('vendorAccount');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'nik' => ['nullable', 'string', 'max:50'],
            'ktp_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'vendor_id' => [
                'required',
                'integer',
                'exists:vendors,id',
                Rule::unique('users', 'vendor_id')->where(fn ($query) => $query->where('role', 'vendor'))->ignore($user->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'vendor_id.unique' => 'Vendor ini sudah punya akun PIC — satu vendor hanya boleh punya satu akun.',
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
