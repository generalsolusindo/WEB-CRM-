<?php

namespace App\Http\Requests\Procurement;

use App\Models\VendorServicePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVendorServicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', [VendorServicePayment::class, $this->route('project')]) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('provides_technical', true)],
            'total_fee' => ['required', 'numeric', 'min:1', 'decimal:0,2'],
            'terms' => ['required', Rule::in([VendorServicePayment::TERMS_DP_FINAL, VendorServicePayment::TERMS_PAY_AT_END])],
            'dp_amount' => ['nullable', 'required_if:terms,'.VendorServicePayment::TERMS_DP_FINAL, 'numeric', 'gt:0', 'lt:total_fee', 'decimal:0,2'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('terms') !== VendorServicePayment::TERMS_DP_FINAL || $this->input('dp_amount') === '') {
            $this->merge(['dp_amount' => null]);
        }
    }
}
