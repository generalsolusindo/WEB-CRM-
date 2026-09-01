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
        ];
    }
}
