<?php

namespace App\Http\Requests\Procurement;

use App\Enums\AvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProcurementRequestLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $procurementRequest = $this->route('procurementRequest');

        return $procurementRequest
            && ($this->user()?->can('update', $procurementRequest) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'distinct'],
            'lines.*.sourcing_note' => ['nullable', 'string', 'max:2000'],
            'lines.*.vendor_product_id' => [
                'nullable',
                'integer',
                Rule::exists('vendor_products', 'id')->where('is_active', true),
            ],
            'lines.*.cost_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.tax_id' => [
                'nullable',
                'integer',
                Rule::exists('taxes', 'id')->where('is_active', true),
            ],
            'lines.*.availability_status' => ['required', Rule::enum(AvailabilityStatus::class)],
        ];
    }
}
