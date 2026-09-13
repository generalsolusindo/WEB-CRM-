<?php

namespace App\Http\Requests\Sales;

use App\Models\Requirement;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Requirement::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', \Illuminate\Validation\Rule::in(['material', 'service', 'reimburse'])],
            'description' => ['nullable', 'string'],
            'qty' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'unit' => ['required', \Illuminate\Validation\Rule::in(Requirement::UNITS)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
