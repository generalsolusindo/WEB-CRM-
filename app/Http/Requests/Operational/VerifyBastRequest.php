<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyBastRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && ($this->user()?->can('verifyBast', $project) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => ['required_if:decision,reject', 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
