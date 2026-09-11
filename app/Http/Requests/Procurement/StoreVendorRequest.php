<?php

namespace App\Http\Requests\Procurement;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vendor::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:120'],
            'coverage_area' => ['nullable', 'string'],
            'bank_account_note' => ['nullable', 'string', 'max:1000'],
            'provides_survey' => ['sometimes', 'boolean'],
            'provides_technical' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provides_survey' => $this->boolean('provides_survey'),
            'provides_technical' => $this->boolean('provides_technical'),
        ]);
    }
}
