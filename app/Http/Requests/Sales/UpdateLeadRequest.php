<?php

namespace App\Http\Requests\Sales;

use App\Enums\LeadStage;
use App\Enums\LeadType;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends StoreLeadRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead && ($this->user()?->can('update', $lead) ?? false);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $lead = $this->route('lead');
        $allowedStages = $lead?->type === LeadType::Opportunity->value
            ? [LeadStage::Qualified->value, LeadStage::Requirement->value]
            : [LeadStage::New->value, LeadStage::Qualified->value];

        $rules['stage'] = ['required', Rule::in($allowedStages)];

        return $rules;
    }
}
