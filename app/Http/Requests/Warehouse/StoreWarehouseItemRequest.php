<?php

namespace App\Http\Requests\Warehouse;

use App\Models\WarehouseItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WarehouseItem::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'qty_on_hand' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
