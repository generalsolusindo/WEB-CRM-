<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateNumber', $this->route('invoice')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => [
                'required', 'string', 'max:30',
                Rule::unique('invoices', 'number')->ignore($this->route('invoice')->id),
            ],
        ];
    }
}
