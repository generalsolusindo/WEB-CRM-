<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class RejectProcurementRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $procurementRequest = $this->route('procurementRequest');

        return $procurementRequest
            && ($this->user()?->can('finalize', $procurementRequest) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
