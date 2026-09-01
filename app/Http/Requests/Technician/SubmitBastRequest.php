<?php

namespace App\Http\Requests\Technician;

use App\Models\Bast;
use Illuminate\Foundation\Http\FormRequest;

class SubmitBastRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && ($this->user()?->can('create', [Bast::class, $project]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
