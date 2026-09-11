<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;

class CheckOutProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkOut', $this->route('project')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
