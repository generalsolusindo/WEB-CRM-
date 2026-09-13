<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustWarehouseStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('item')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['in', 'out'])],
            'qty' => ['required', 'integer', 'min:1'],
        ];
    }
}
