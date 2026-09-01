<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;

class UploadSurveyAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('workReport', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
