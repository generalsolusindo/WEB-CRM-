<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\OrderType;

class ConfirmQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation && ($this->user()?->can('confirm', $quotation) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'signed_quotation' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:1024'],
            'purchase_order' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:1024'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'signed_quotation.required' => 'Wajib unggah quotation yang sudah ditandatangani & distempel customer.',
        ];
    }
}
