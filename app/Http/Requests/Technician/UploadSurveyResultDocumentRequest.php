<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;

class UploadSurveyResultDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageResultDocuments', $this->route('survey')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Dokumen hasil survey harus berupa gambar (JPG/PNG) atau PDF.',
            'file.max' => 'Ukuran dokumen maksimal 5 MB.',
        ];
    }
}
