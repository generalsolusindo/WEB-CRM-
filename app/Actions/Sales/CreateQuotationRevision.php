<?php

namespace App\Actions\Sales;

use App\Enums\QuotationStatus;
use App\Models\Notification;
use App\Models\Quotation;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateQuotationRevision
{
    public function __construct(private DocumentNumber $documentNumber) {}

    public function handle(Quotation $quotation, User $user): Quotation
    {
        return DB::transaction(function () use ($quotation, $user) {
            $source = Quotation::query()->with(['lines', 'lead.delegatedTo'])->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($source->status, ['sent', 'rejected'], true) || $source->revisions()->exists()) {
                throw ValidationException::withMessages([
                    'quotation' => 'Quotation ini tidak dapat direvisi atau sudah memiliki revisi lanjutan.',
                ]);
            }

            $revision = Quotation::create([
                'number' => $this->documentNumber->revisionNumber(
                    $source->number ?? $this->documentNumber->nextQuotationNumber(),
                    $source->revision_number + 1,
                ),
                'procurement_request_id' => $source->procurement_request_id,
                'lead_id' => $source->lead_id,
                'contact_id' => $source->contact_id,
                'sales_id' => $user->id,
                'status' => QuotationStatus::Draft->value,
                'revision_number' => $source->revision_number + 1,
                'parent_quotation_id' => $source->id,
                'valid_until' => $source->valid_until,
                'notes' => $source->notes,
                'agreed_dpp' => $source->agreed_dpp,
            ]);

            foreach ($source->lines as $line) {
                $revision->lines()->create($line->only([
                    'procurement_request_line_id', 'item_name', 'category', 'description', 'sourcing_note', 'qty', 'unit',
                    'cost_price', 'selling_price', 'discount_percent', 'discount_amount',
                    'markup_percent', 'tax_id', 'tax_rate', 'subtotal',
                ]));
            }

            $source->update(['status' => QuotationStatus::Revised->value]);

            $pm = $source->lead?->delegatedTo;
            if ($pm) {
                Notification::updateOrCreate(
                    [
                        'user_id' => $pm->id,
                        'type' => 'quotation.pending_pm_review',
                        'related_type' => $revision->getMorphClass(),
                        'related_id' => $revision->id,
                    ],
                    [
                        'message' => "Quotation revisi {$revision->number} perlu diverifikasi oleh Anda sebelum dikirim ke customer.",
                        'is_sent' => true,
                        'read_at' => null,
                    ],
                );
            }

            return $revision;
        });
    }
}
