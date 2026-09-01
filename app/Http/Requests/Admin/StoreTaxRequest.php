<?php

namespace App\Http\Requests\Admin;

use App\Models\Tax;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tax::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
