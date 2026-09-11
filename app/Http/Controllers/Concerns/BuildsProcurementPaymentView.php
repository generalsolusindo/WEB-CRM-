<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ProcurementPayment;
use Illuminate\Support\Facades\Storage;

trait BuildsProcurementPaymentView
{
    /** @return array<string, mixed> */
    protected function procurementPaymentDetail(ProcurementPayment $payment): array
    {
        $payment->loadMissing([
            'project.salesOrder:id,number,contact_id',
            'project.salesOrder.contact:id,name,company_name',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.vendor:id,name',
            'lumpSumVendor:id,name',
            'submittedBy:id,name',
            'pmReviewedBy:id,name',
            'financePaidBy:id,name',
            'confirmedBy:id,name',
            'proofs',
        ]);

        $proofUrl = fn (string $path) => Storage::disk('local')->temporaryUrl($path, now()->addDay());

        return [
            'id' => $payment->id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'pricing_mode' => $payment->pricing_mode,
            'lump_sum_vendor' => $payment->lumpSumVendor?->name,
            'lump_sum_amount' => $payment->lump_sum_amount,
            'bank_account_note' => $payment->bank_account_note,
            'pm_notes' => $payment->pm_notes,
            'submitted_by' => $payment->submittedBy?->name,
            'submitted_at' => $payment->submitted_at,
            'pm_reviewed_by' => $payment->pmReviewedBy?->name,
            'pm_reviewed_at' => $payment->pm_reviewed_at,
            'finance_paid_by' => $payment->financePaidBy?->name,
            'finance_paid_at' => $payment->finance_paid_at,
            'confirmed_by' => $payment->confirmedBy?->name,
            'confirmed_at' => $payment->confirmed_at,
            'project' => [
                'id' => $payment->project_id,
                'number' => 'PRJ-'.str_pad((string) $payment->project_id, 6, '0', STR_PAD_LEFT),
                'customer' => $payment->project->salesOrder->contact->name ?? '—',
                'company' => $payment->project->salesOrder->contact->company_name,
                'sales_order' => $payment->project->salesOrder->number,
            ],
            'items' => $payment->items->map(fn ($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'is_extra' => $item->requested_by !== null,
                'from_office_stock' => $item->from_office_stock,
                'office_stock_note' => $item->office_stock_note,
                'vendor' => $item->vendor?->name,
                'bank_account_note' => $item->bank_account_note,
                'cost_price' => $item->cost_price,
                'estimated_cost' => $item->estimated_cost,
                'line_total' => (float) $item->cost_price * (float) $item->qty,
                'estimated_total' => (float) ($item->estimated_cost ?? 0) * (float) $item->qty,
                'is_paid' => $item->is_paid,
                'status' => $item->status,
                'proofs' => $item->proofs->map(fn ($p) => ['id' => $p->id, 'url' => $proofUrl($p->file_path)])->values(),
            ])->values(),
            'request_proofs' => $payment->proofs
                ->whereNull('actual_procurement_id')
                ->map(fn ($p) => ['id' => $p->id, 'url' => $proofUrl($p->file_path)])
                ->values(),
            'totals' => [
                'estimated' => (float) $payment->items->where('from_office_stock', false)
                    ->sum(fn ($i) => (float) ($i->estimated_cost ?? 0) * (float) $i->qty),
                'actual' => $payment->pricing_mode === 'lump_sum'
                    ? (float) ($payment->lump_sum_amount ?? 0)
                    : (float) $payment->items->where('from_office_stock', false)
                        ->sum(fn ($i) => (float) $i->cost_price * (float) $i->qty),
            ],
        ];
    }
}
