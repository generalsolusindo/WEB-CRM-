<?php

namespace App\Http\Requests\Operational;

use App\Models\DeliveryNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DeliveryNote::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delivery_method' => ['required', Rule::in(['ekspedisi', 'sendiri'])],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'shipper_name' => ['nullable', 'string', 'max:150'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'approved_by_name' => ['nullable', 'string', 'max:150'],
            'dispatch_proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'integer', 'distinct', 'exists:sales_order_lines,id'],
            'lines.*.qty_delivered' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
        ];
    }
}
