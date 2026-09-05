<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class SignSowAsAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('signAsAdmin', $this->route('sow')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:200000'],
        ];
    }
}
