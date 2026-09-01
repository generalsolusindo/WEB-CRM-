<?php

namespace App\Http\Requests\Procurement;

use App\Enums\ActualProcurementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActualProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('actualProcurement');

        return $item && ($this->user()?->can('update', $item) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vendor_product_id' => [
                'nullable',
                'integer',
                Rule::exists('vendor_products', 'id')->where('is_active', true),
            ],
            'cost_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'status' => ['required', Rule::enum(ActualProcurementStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
