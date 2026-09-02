<?php

namespace App\Services\Sales;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumen bukti persetujuan customer (quotation ber-TTD + PO) sebuah Sales Order,
 * dengan URL bertanda tangan untuk ditampilkan read-only ke divisi lain.
 */
class CustomerApprovalDocs
{
    /** @return array<int, array{category: string, label: string, url: string}> */
    public static function of(SalesOrder $salesOrder): array
    {
        return $salesOrder->attachments()
            ->whereIn('category', ['quotation_signed', 'purchase_order'])
            ->latest()
            ->get()
            ->map(fn ($attachment) => [
                'category' => $attachment->category,
                'label' => $attachment->category === 'purchase_order'
                    ? 'Purchase Order'
                    : 'Quotation ditandatangani',
                'url' => Storage::disk('local')->temporaryUrl($attachment->file_path, now()->addDay()),
            ])
            ->all();
    }
}
