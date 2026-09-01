<?php

namespace App\Http\Requests\Finance;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice && ($this->user()?->can('create', [Payment::class, $invoice]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_paid' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
