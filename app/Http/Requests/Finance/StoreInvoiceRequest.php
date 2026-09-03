<?php

namespace App\Http\Requests\Finance;

use App\Enums\InvoicePhase;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'phase' => ['required', Rule::in([InvoicePhase::Dp->value, InvoicePhase::Full->value])],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'dp_percent' => ['nullable', 'numeric', 'min:1', 'max:99', 'decimal:0,2'],
            'agreed_dpp' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'pph23_enabled' => ['sometimes', 'boolean'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['pph23_enabled' => $this->boolean('pph23_enabled')]);
    }
}
