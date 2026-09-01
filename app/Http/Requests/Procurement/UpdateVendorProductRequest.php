<?php

namespace App\Http\Requests\Procurement;

class UpdateVendorProductRequest extends StoreVendorProductRequest
{
    public function authorize(): bool
    {
        $vendorProduct = $this->route('vendor_product');

        return $vendorProduct && ($this->user()?->can('update', $vendorProduct) ?? false);
    }
}
