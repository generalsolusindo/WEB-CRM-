<?php

namespace App\Http\Requests\Procurement;

use App\Enums\ProductCategory;
use App\Models\VendorProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VendorProduct::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ProductCategory::class)],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'unit' => ['required', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
