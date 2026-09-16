<?php

namespace App\Http\Requests\Sales;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $duplicate = Contact::find($this->input('duplicate_contact_id'));

        if (! $duplicate) {
            return false;
        }

        return $this->user()?->can('merge', [$this->route('contact'), $duplicate]) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'duplicate_contact_id' => [
                'required',
                'integer',
                Rule::exists('contacts', 'id')->whereNot('id', $this->route('contact')->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'duplicate_contact_id.required' => 'Pilih contact duplikat yang mau digabungkan.',
        ];
    }
}
