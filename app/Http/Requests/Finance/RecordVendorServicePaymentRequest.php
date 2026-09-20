<?php

namespace App\Http\Requests\Finance;

use App\Models\VendorServicePaymentEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordVendorServicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('vendorServicePayment');

        return $payment && ($this->user()?->can('pay', $payment) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([VendorServicePaymentEntry::KIND_DP, VendorServicePaymentEntry::KIND_FINAL])],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
