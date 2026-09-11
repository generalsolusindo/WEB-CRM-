<?php

namespace App\Http\Requests\ProjectManager;

use Illuminate\Foundation\Http\FormRequest;

class ReviewProcurementPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('procurementPayment')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'notes' => [$this->boolean('approved') ? 'nullable' : 'required', 'string', 'max:2000'],
        ];
    }
}
