<?php

namespace App\Services\Sales;

use App\Models\Payment;
use App\Models\SalesOrder;
use App\Services\Notifications\Notify;

/**
 * Sends the "ready to be closed as Won" notification to the owning sales rep
 * once a Sales Order's settlement invoice is Paid and has a payment proof.
 * Idempotent via Notify::once() — repeated payments/proofs never re-notify.
 */
class SalesOrderWinNotifier
{
    public function __construct(
        private SalesOrderSettlement $settlement,
        private Notify $notify,
    ) {}

    public function fromPayment(Payment $payment): void
    {
        $payment->loadMissing('invoice.salesOrder.quotation.sales');

        $this->evaluate($payment->invoice?->salesOrder);
    }

    public function evaluate(?SalesOrder $salesOrder): void
    {
        if (! $salesOrder) {
            return;
        }

        $salesOrder->loadMissing('quotation.sales');
        $sales = $salesOrder->quotation?->sales;

        if (! $sales || ! $this->settlement->canCloseAsWon($salesOrder)) {
            return;
        }

        $this->notify->once(
            $sales,
            'sales_order.ready_to_win',
            "Sales Order {$salesOrder->number} sudah lunas dan bukti bayar lengkap. Siap ditutup sebagai Won.",
            $salesOrder,
        );
    }
}
