<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;

class SignSowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('signAsTechnician', $this->route('sow')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:200000'],
        ];
    }
}
