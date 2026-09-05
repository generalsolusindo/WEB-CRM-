<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreVendorAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vendor-accounts') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'vendor_id' => [
                'required',
                'integer',
                'exists:vendors,id',
                Rule::unique('users', 'vendor_id')->where(fn ($query) => $query->where('role', 'vendor')),
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
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}
