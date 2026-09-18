<?php

namespace App\Actions\Finance;

use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Notifications\Notify;
use App\Services\Sales\SalesOrderSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelPayment
{
    public function handle(Invoice $invoice, Payment $payment, User $finance, string $reason): void
    {
        DB::transaction(function () use ($invoice, $payment, $finance, $reason) {
            // Lock the order too: closing Won and creating a final invoice use this lock.
            $order = SalesOrder::query()->whereKey($invoice->sales_order_id)->lockForUpdate()->first();
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $entry = $locked->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $entry->setRelation('invoice', $locked);
            Gate::forUser($finance)->authorize('cancel', $entry);

            if (! $order || in_array($order->status, ['won', 'completed', 'cancelled'], true)) {
                throw ValidationException::withMessages(['reason' => 'Sales Order sudah ditutup atau dibatalkan. Koreksi pembayaran memerlukan peninjauan transaksi terkait.']);
            }

            $remaining = round((float) $locked->payments()->whereKeyNot($entry->id)->sum('amount_paid'), 2);
            $status = match (true) {
                round($remaining + (float) $locked->pph23_amount, 2) >= $locked->grandTotal() => 'paid',
                $remaining > 0 => 'partially_paid',
                $locked->status === 'draft' => 'draft',
                default => 'sent',
            };

            if ($locked->invoice_phase === 'dp' && $status !== 'paid'
                && $order->invoices()->where('invoice_phase', 'final')->where('status', '!=', 'cancelled')->exists()) {
                throw ValidationException::withMessages(['reason' => 'Invoice pelunasan aktif sudah ada. Tinjau invoice pelunasan sebelum membatalkan pembayaran DP.']);
            }

            // Keep the original amount and proof for audit, exclude it from all active sums.
            $entry->fill(['cancelled_by' => $finance->id, 'cancellation_reason' => $reason])->saveQuietly();
            $entry->delete();
            $locked->update(['status' => $status]);

            if (! app(SalesOrderSettlement::class)->canCloseAsWon($order)) {
                Notification::query()->where('type', 'sales_order.ready_to_win')
                    ->where('related_type', $order->getMorphClass())->where('related_id', $order->id)->delete();
            }

            if (in_array($locked->invoice_phase, ['dp', 'full'], true) && $status !== 'paid') {
                foreach ($order->projects()->get() as $project) {
                    app(Notify::class)->resolve('invoice.upfront_paid', $project);
                    // Never erase operational work merely because Finance corrects a receipt.
                    foreach (User::query()->whereIn('role', ['operational', 'management'])->where('is_active', true)->get() as $recipient) {
                        Notification::updateOrCreate([
                            'user_id' => $recipient->id, 'type' => 'invoice.payment_cancelled',
                            'related_type' => $project->getMorphClass(), 'related_id' => $project->id,
                        ], [
                            'message' => "Pembayaran invoice {$locked->number} dibatalkan Finance. Invoice awal belum lunas; tinjau kelanjutan project.",
                            'is_sent' => true, 'read_at' => null,
                        ]);
                    }
                }
            }
        }, 3);
    }
}
