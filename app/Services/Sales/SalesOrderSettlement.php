<?php

namespace App\Services\Sales;

use App\Enums\OrderType;
use App\Models\Invoice;
use App\Models\SalesOrder;

class SalesOrderSettlement
{
    public function requiredInvoicePhase(SalesOrder $salesOrder): string
    {
        return $salesOrder->order_type === OrderType::MaterialOnly->value ? 'full' : 'final';
    }

    /**
     * Fase invoice muka yang harus lunas sebelum Project boleh dibuat.
     */
    public function upfrontInvoicePhase(SalesOrder $salesOrder): string
    {
        return $salesOrder->order_type === OrderType::MaterialOnly->value ? 'full' : 'dp';
    }

    /**
     * GATE Project: cek eksplisit ke status invoice muka, bukan status Sales Order.
     */
    public function upfrontInvoicePaid(SalesOrder $salesOrder): bool
    {
        return $salesOrder->invoices()
            ->where('invoice_phase', $this->upfrontInvoicePhase($salesOrder))
            ->where('status', 'paid')
            ->exists();
    }

    public function settlementInvoice(SalesOrder $salesOrder): ?Invoice
    {
        return $salesOrder->invoices()
            ->where('invoice_phase', $this->requiredInvoicePhase($salesOrder))
            ->where('status', 'paid')
            ->whereHas('payments.attachments', fn ($query) => $query->where('category', 'payment_proof'))
            ->latest()
            ->first();
    }

    public function canCloseAsWon(SalesOrder $salesOrder): bool
    {
        // TODO: untuk order Material Only, tambahkan syarat project berstatus 'completed'
        // setelah alur BAST/serah-terima Material Only dikonfirmasi. Saat ini Material Only
        // hanya butuh invoice muka (full) lunas + bukti bayar.
        return $this->settlementInvoice($salesOrder) !== null;
    }

    /**
     * Final invoice boleh dibuat: order berjenis jasa, DP sudah lunas,
     * project sudah completed, dan belum ada invoice final aktif.
     */
    public function canCreateFinalInvoice(SalesOrder $salesOrder): bool
    {
        if ($salesOrder->order_type === OrderType::MaterialOnly->value) {
            return false;
        }

        $dpPaid = $salesOrder->invoices()
            ->where('invoice_phase', 'dp')
            ->where('status', 'paid')
            ->exists();

        $hasFinal = $salesOrder->invoices()
            ->where('invoice_phase', 'final')
            ->where('status', '!=', 'cancelled')
            ->exists();

        $projectDone = $salesOrder->projects()
            ->where('status', 'completed')
            ->exists();

        return $dpPaid && $projectDone && ! $hasFinal;
    }
}
