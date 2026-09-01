<?php

namespace App\Http\Requests\Operational;

use App\Enums\ChangeRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project && ($this->user()?->can('manageChangeRequests', $project) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ChangeRequestType::class)],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
