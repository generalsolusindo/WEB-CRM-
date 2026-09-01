<?php

namespace App\Http\Requests\Sales;

class UpdateContactRequest extends StoreContactRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact && ($this->user()?->can('update', $contact) ?? false);
    }
}
