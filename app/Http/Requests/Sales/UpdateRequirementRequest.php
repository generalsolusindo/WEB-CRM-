<?php

namespace App\Http\Requests\Sales;

class UpdateRequirementRequest extends StoreRequirementRequest
{
    public function authorize(): bool
    {
        $requirement = $this->route('requirement');

        return $requirement && ($this->user()?->can('update', $requirement) ?? false);
    }
}
