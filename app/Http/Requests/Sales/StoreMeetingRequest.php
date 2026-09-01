<?php

namespace App\Http\Requests\Sales;

use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead && ($this->user()?->can('create', [Meeting::class, $lead]) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'string'],
            'notes' => ['required', 'string'],
        ];
    }
}
