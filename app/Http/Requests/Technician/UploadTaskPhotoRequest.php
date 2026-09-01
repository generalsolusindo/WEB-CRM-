<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadTaskPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task && ($this->user()?->can('uploadPhoto', $task) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['task_before', 'task_after'])],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
