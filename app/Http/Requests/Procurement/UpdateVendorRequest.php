<?php

namespace App\Http\Requests\Procurement;

class UpdateVendorRequest extends StoreVendorRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor && ($this->user()?->can('update', $vendor) ?? false);
    }
}
