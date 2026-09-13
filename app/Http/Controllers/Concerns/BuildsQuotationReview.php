<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Quotation;
use App\Services\Sales\DocumentTotals;

/**
 * Payload read-only quotation untuk layar verifikasi Project Manager & Manager.
 */
trait BuildsQuotationReview
{
    /** @return array<string, mixed> */
    private function quotationRow(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'number' => $quotation->number,
            'customer' => $quotation->contact?->name,
            'company' => $quotation->contact?->company_name,
            'sales' => $quotation->sales?->name,
            'status' => $quotation->status,
            'is_addendum' => $quotation->is_addendum,
            'pm_review_status' => $quotation->pm_review_status,
            'manager_review_status' => $quotation->manager_review_status,
            'created_at' => $quotation->created_at,
        ];
    }

    /** @return array<string, mixed> */
    private function quotationDetail(Quotation $quotation): array
    {
        $quotation->loadMissing([
            'contact:id,name,company_name,email,phone',
            'sales:id,name',
            'lines.tax:id,name,rate',
            'pmReviewedBy:id,name',
            'managerReviewedBy:id,name',
        ]);

        return [
            'id' => $quotation->id,
            'number' => $quotation->number,
            'status' => $quotation->status,
            'is_addendum' => $quotation->is_addendum,
            'customer' => $quotation->contact?->name,
            'company' => $quotation->contact?->company_name,
            'sales' => $quotation->sales?->name,
            'valid_until' => $quotation->valid_until,
            'notes' => $quotation->notes,
            'lines' => $quotation->lines,
            'totals' => DocumentTotals::of($quotation->lines),
            'pm_review_status' => $quotation->pm_review_status,
            'pm_reviewed_by' => $quotation->pmReviewedBy?->name,
            'pm_reviewed_at' => $quotation->pm_reviewed_at,
            'pm_review_notes' => $quotation->pm_review_notes,
            'manager_review_status' => $quotation->manager_review_status,
            'manager_reviewed_by' => $quotation->managerReviewedBy?->name,
            'manager_reviewed_at' => $quotation->manager_reviewed_at,
            'manager_review_notes' => $quotation->manager_review_notes,
        ];
    }
}
