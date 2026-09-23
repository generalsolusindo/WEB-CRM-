<?php

namespace App\Http\Requests\Admin;

use App\Services\UserSignature;
use Illuminate\Foundation\Http\FormRequest;

class UploadUserSignatureRequest extends FormRequest
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

    /** Target upload harus salah satu role yang boleh menandatangani SOW secara internal. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $target = $this->route('user');

            if ($target && ! in_array($target->role, UserSignature::ELIGIBLE_ROLES, true)) {
                $validator->errors()->add('signature', 'User ini bukan Operasional, Project Manager, atau Management.');
            }
        });
    }
}
