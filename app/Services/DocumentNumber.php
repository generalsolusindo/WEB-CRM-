<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Generates human-readable document numbers.
 *
 *   Quotation   : QUO-{YYYY}-{0001}   (sequence resets every year)
 *   Sales Order : SO-{YYYY}-{0001}
 *   Revision    : {parent number}-R{revision_number}   (revision 1 keeps the parent number)
 *
 * The sequence lookup uses lockForUpdate() so it must run inside the
 * surrounding transaction of the calling Action to stay race-safe.
 */
class DocumentNumber
{
    public function nextQuotationNumber(): string
    {
        return $this->nextSequential(
            Quotation::query()->whereNull('parent_quotation_id'),
            'QUO',
        );
    }

    public function nextSalesOrderNumber(): string
    {
        return $this->nextSequential(SalesOrder::query(), 'SO');
    }

    public function nextInvoiceNumber(): string
    {
        return $this->nextSequential(Invoice::query(), 'INV');
    }

    public function nextSurveyInvoiceNumber(): string
    {
        return $this->nextSequential(Invoice::query(), 'SRV');
    }

    public function revisionNumber(string $parentNumber, int $revisionNumber): string
    {
        return $revisionNumber <= 1
            ? $parentNumber
            : "{$parentNumber}-R{$revisionNumber}";
    }

    private function nextSequential(Builder $query, string $prefix): string
    {
        $year = now()->year;
        $fullPrefix = "{$prefix}-{$year}-";

        $lastSequence = $query
            ->where('number', 'like', "{$fullPrefix}%")
            ->lockForUpdate()
            ->pluck('number')
            ->map(fn (string $number) => (int) substr($number, strlen($fullPrefix), 4))
            ->max() ?? 0;

        return $fullPrefix.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
