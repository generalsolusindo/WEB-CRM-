<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;

class UploadDeliveryReceivedProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadReceivedProof', $this->route('deliveryNote')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
