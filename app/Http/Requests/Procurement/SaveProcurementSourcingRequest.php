<?php

namespace App\Http\Requests\Procurement;

use App\Models\ProcurementPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProcurementSourcingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project !== null
            && ($this->user()?->can('submit', [ProcurementPayment::class, $project]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pricing_mode' => ['nullable', Rule::in(['itemized', 'lump_sum'])],
            'lump_sum_vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
            'lump_sum_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'bank_account_note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.from_office_stock' => ['boolean'],
            'lines.*.office_stock_note' => ['nullable', 'string', 'max:255'],
            'lines.*.vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
            'lines.*.cost_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.bank_account_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
