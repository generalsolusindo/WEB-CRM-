<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class AddExtraProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && ($this->user()?->can('manageExtraProcurement', $project) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'unit' => ['required', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
