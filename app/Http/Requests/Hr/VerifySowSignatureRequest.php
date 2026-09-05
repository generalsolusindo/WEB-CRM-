<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class VerifySowSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verifySignatureAsHr', $this->route('sow')) ?? false;
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
