<?php

namespace App\Services\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;

/**
 * Total biaya survey yang sudah dibayar customer untuk sebuah opportunity.
 * Dikreditkan (dipotong) di invoice Sales Order bila deal jadi.
 */
class SurveyCredit
{
    public static function forLead(int $leadId): float
    {
        return round((float) Invoice::query()
            ->where('invoice_type', InvoiceType::Survey->value)
            ->where('status', InvoiceStatus::Paid->value)
            ->whereHas('survey', fn ($q) => $q->where('lead_id', $leadId))
            ->sum('amount'), 2);
    }
}
