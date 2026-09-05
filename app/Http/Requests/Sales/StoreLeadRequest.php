<?php

namespace App\Http\Requests\Sales;

use App\Enums\LeadStage;
use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_id' => [
                'required',
                'integer',
                Rule::exists(Contact::class, 'id')->where(
                    fn ($query) => $query->where('created_by', $this->user()->id),
                ),
            ],
            'stage' => ['required', Rule::in([LeadStage::New->value])],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'pic_position' => ['nullable', 'string', 'max:255'],
            'pic_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
