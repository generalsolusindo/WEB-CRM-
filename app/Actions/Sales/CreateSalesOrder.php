<?php

namespace App\Actions\Sales;

use App\Enums\LeadStage;
use App\Enums\OrderType;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Services\Notifications\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSalesOrder
{
    public function __construct(
        private DocumentNumber $documentNumber,
        private Notify $notify,
    ) {}

    /**
     * @param  array<string, UploadedFile|null>  $documents  keyed by attachment category
     */
    public function handle(
        Quotation $quotation,
        User $user,
        OrderType $orderType,
        array $documents = [],
        ?string $poNumber = null,
    ): SalesOrder {
        return DB::transaction(function () use ($quotation, $user, $orderType, $documents, $poNumber) {
            $source = Quotation::query()
                ->with(['lines', 'lead'])
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($source->status !== QuotationStatus::Sent->value) {
                throw ValidationException::withMessages([
                    'quotation' => 'Hanya quotation berstatus Sent yang dapat dikonfirmasi.',
                ]);
            }

            if ($source->sales_id !== $user->id) {
                abort(403);
            }

            if ($source->salesOrder()->exists()) {
                throw ValidationException::withMessages([
                    'quotation' => 'Sales Order untuk quotation ini sudah tersedia.',
                ]);
            }

            if ($source->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'quotation' => 'Quotation tidak memiliki line item.',
                ]);
            }

            $salesOrder = SalesOrder::create([
                'number' => $this->documentNumber->nextSalesOrderNumber(),
                'quotation_id' => $source->id,
                'contact_id' => $source->contact_id,
                'order_type' => $orderType->value,
                'payment_rule' => $orderType->paymentRule()->value,
                'status' => SalesOrderStatus::Confirmed->value,
                'survey_credit' => $source->survey_credit,
                'po_number' => $poNumber,
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
            ]);

            foreach ($documents as $category => $file) {
                if ($file instanceof UploadedFile) {
                    $salesOrder->attachments()->create([
                        'category' => $category,
                        'file_path' => $file->store('sales-order-approvals'),
                        'uploaded_by' => $user->id,
                    ]);
                }
            }

            foreach ($source->lines as $line) {
                $salesOrder->lines()->create([
                    'quotation_line_id' => $line->id,
                    'item_name' => $line->item_name,
                    'description' => $line->description,
                    'qty' => $line->qty,
                    'unit' => $line->unit,
                    'cost_price' => $line->cost_price,
                    'selling_price' => $line->selling_price,
                    'discount_percent' => $line->discount_percent,
                    'discount_amount' => $line->discount_amount,
                    'tax_id' => $line->tax_id,
                    'tax_rate' => $line->tax_rate,
                    'subtotal' => $line->subtotal,
                ]);
            }

            $source->update(['status' => QuotationStatus::Confirmed->value]);
            $source->lead->update(['stage' => LeadStage::Negotiation->value]);

            $this->notify->onceForEach(
                User::query()->where('role', 'finance')->where('is_active', true)->get(),
                'sales_order.created',
                "Sales Order {$salesOrder->number} sudah dikonfirmasi dan siap dibuatkan invoice.",
                $salesOrder,
            );

            return $salesOrder;
        });
    }
}
