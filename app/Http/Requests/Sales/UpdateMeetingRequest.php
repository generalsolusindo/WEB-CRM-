<?php

namespace App\Http\Requests\Sales;

class UpdateMeetingRequest extends StoreMeetingRequest
{
    public function authorize(): bool
    {
        $meeting = $this->route('meeting');

        return $meeting && ($this->user()?->can('update', $meeting) ?? false);
    }
}
