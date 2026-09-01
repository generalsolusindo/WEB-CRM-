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
            'description' => ['nullable', 'string'],
            'qty' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'unit' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
