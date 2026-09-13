<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'administrator';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'signature' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
