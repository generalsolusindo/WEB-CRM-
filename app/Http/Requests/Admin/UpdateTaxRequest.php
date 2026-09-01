<?php

namespace App\Http\Requests\Admin;

class UpdateTaxRequest extends StoreTaxRequest
{
    public function authorize(): bool
    {
        $tax = $this->route('tax');

        return $tax && ($this->user()?->can('update', $tax) ?? false);
    }
}
