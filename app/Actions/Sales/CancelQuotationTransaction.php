<?php

namespace App\Actions\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStage;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelQuotationTransaction
{
    public function handle(Quotation $quotation, User $user, string $reason): void
    {
        DB::transaction(function () use ($quotation, $user, $reason) {
            $source = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            $rootId = $source->parent_quotation_id ?? $source->id;
            $chain = Quotation::query()
                ->where(fn ($query) => $query->where('id', $rootId)->orWhere('parent_quotation_id', $rootId))
                ->lockForUpdate()
                ->get();

            $orders = SalesOrder::query()
                ->whereIn('quotation_id', $chain->pluck('id'))
                ->with(['invoices.payments', 'projects'])
                ->lockForUpdate()
                ->get();

            $this->ensureCancellable($chain, $orders);

            $audit = [
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ];

            foreach ($orders as $order) {
                foreach ($order->invoices as $invoice) {
                    $invoice->update([...$audit, 'status' => InvoiceStatus::Cancelled->value]);
                }
                $order->update([...$audit, 'status' => SalesOrderStatus::Cancelled->value]);
            }

            Quotation::query()->whereIn('id', $chain->pluck('id'))->update([
                ...$audit,
                'status' => QuotationStatus::Cancelled->value,
            ]);

            $source->lead()->update(['stage' => LeadStage::Lost->value]);
            $this->resolveNotifications($chain, $orders);
        }, 3);
    }

    /**
     * @param  Collection<int, Quotation>  $chain
     * @param  Collection<int, SalesOrder>  $orders
     */
    private function ensureCancellable(Collection $chain, Collection $orders): void
    {
        if ($chain->contains(fn (Quotation $item) => $item->status === QuotationStatus::Cancelled->value)) {
            throw ValidationException::withMessages(['reason' => 'Rangkaian quotation ini sudah dibatalkan.']);
        }

        if ($orders->contains(fn (SalesOrder $order) => $order->projects->isNotEmpty())) {
            throw ValidationException::withMessages(['reason' => 'Transaksi sudah memiliki Project dan tidak dapat dibatalkan dari Sales.']);
        }

        if ($orders->contains(fn (SalesOrder $order) => in_array($order->status, [
            SalesOrderStatus::Completed->value,
            SalesOrderStatus::Won->value,
        ], true))) {
            throw ValidationException::withMessages(['reason' => 'Sales Order sudah selesai dan tidak dapat dibatalkan.']);
        }

        $invoices = $orders->flatMap(fn (SalesOrder $order) => $order->invoices);
        if ($invoices->contains(fn (Invoice $invoice) => $invoice->payments->isNotEmpty()
            || in_array($invoice->status, [InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value], true))) {
            throw ValidationException::withMessages(['reason' => 'Transaksi sudah memiliki pembayaran dan tidak dapat dibatalkan dari Sales.']);
        }
    }

    /**
     * @param  Collection<int, Quotation>  $chain
     * @param  Collection<int, SalesOrder>  $orders
     */
    private function resolveNotifications(Collection $chain, Collection $orders): void
    {
        $related = $chain->map(fn (Quotation $item) => [$item->getMorphClass(), $item->id])
            ->merge($orders->map(fn (SalesOrder $item) => [$item->getMorphClass(), $item->id]))
            ->merge($orders->flatMap(fn (SalesOrder $order) => $order->invoices)
                ->map(fn (Invoice $item) => [$item->getMorphClass(), $item->id]));

        foreach ($related as [$type, $id]) {
            Notification::query()
                ->where('related_type', $type)
                ->where('related_id', $id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }
}
