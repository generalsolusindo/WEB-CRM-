<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\LeadStage;
use App\Models\Lead;

/**
 * Payload read-only Opportunity untuk layar Management & Project Manager.
 */
trait BuildsOpportunityOverview
{
    /** @return array<string, mixed> */
    private function opportunityRow(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'code' => 'OPP-'.str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT),
            'customer' => $lead->contact?->name,
            'company' => $lead->contact?->company_name,
            'stage' => $lead->stage,
            'stage_label' => LeadStage::from($lead->stage)->label(),
            'sales' => $lead->sales?->name,
            'delegated_to' => $lead->delegatedTo?->name,
            'created_at' => $lead->created_at,
        ];
    }

    /** @return array<string, mixed> */
    private function opportunityDetail(Lead $lead): array
    {
        $lead->loadMissing([
            'contact:id,name,company_name,email,phone',
            'sales:id,name',
            'delegatedTo:id,name',
            'delegatedBy:id,name',
        ]);

        return [
            'id' => $lead->id,
            'code' => 'OPP-'.str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT),
            'customer' => $lead->contact?->name,
            'company' => $lead->contact?->company_name,
            'email' => $lead->contact?->email,
            'phone' => $lead->contact?->phone,
            'stage' => $lead->stage,
            'stage_label' => LeadStage::from($lead->stage)->label(),
            'sales' => $lead->sales?->name,
            'notes' => $lead->notes,
            'delegated_to' => $lead->delegatedTo ? ['id' => $lead->delegatedTo->id, 'name' => $lead->delegatedTo->name] : null,
            'delegated_by' => $lead->delegatedBy?->name,
            'delegated_at' => $lead->delegated_at,
        ];
    }
}
