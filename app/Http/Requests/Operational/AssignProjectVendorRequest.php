<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignProjectVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignVendor', $this->route('project')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('vendors', 'id')->where('provides_technical', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['vendor_id' => $this->input('vendor_id') ?: null]);
    }
}
