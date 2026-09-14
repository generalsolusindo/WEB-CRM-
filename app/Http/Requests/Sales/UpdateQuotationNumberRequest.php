<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateNumber', $this->route('quotation')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => [
                'required', 'string', 'max:30',
                Rule::unique('quotations', 'number')->ignore($this->route('quotation')->id),
            ],
        ];
    }
}
