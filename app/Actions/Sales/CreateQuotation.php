<?php

namespace App\Actions\Sales;

use App\Enums\LeadStage;
use App\Enums\QuotationStatus;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Tax;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Services\Notifications\Notify;
use App\Services\Sales\AgreedDpp;
use App\Services\Sales\LinePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateQuotation
{
    public function __construct(private DocumentNumber $documentNumber, private Notify $notify) {}

    /** @param array<string, mixed> $data */
    public function handle(ProcurementRequest $procurementRequest, User $user, array $data): Quotation
    {
        return DB::transaction(function () use ($procurementRequest, $user, $data) {
            $request = ProcurementRequest::query()
                ->with(['lead.delegatedTo', 'lines', 'lines.vendorProduct:id,category'])
                ->whereKey($procurementRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status !== 'ready') {
                throw ValidationException::withMessages([
                    'procurement_request' => 'Quotation hanya dapat dibuat dari Procurement Request berstatus Ready.',
                ]);
            }

            if ($request->lead->sales_id !== $user->id) {
                abort(403);
            }

            if ($request->quotations()->exists()) {
                throw ValidationException::withMessages([
                    'procurement_request' => 'Quotation untuk Procurement Request ini sudah dibuat.',
                ]);
            }

            $submitted = $this->validatedLines($request, $data['lines']);
            $taxRates = Tax::pluck('rate', 'id');

            $quotation = Quotation::create([
                'number' => $this->documentNumber->nextQuotationNumber(),
                'procurement_request_id' => $request->id,
                'lead_id' => $request->lead_id,
                'contact_id' => $request->lead->contact_id,
                'sales_id' => $user->id,
                'status' => QuotationStatus::Draft->value,
                'revision_number' => 1,
                'parent_quotation_id' => null,
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'agreed_dpp' => isset($data['agreed_dpp']) && $data['agreed_dpp'] !== null && $data['agreed_dpp'] !== ''
                    ? (float) $data['agreed_dpp']
                    : null,
            ]);

            foreach ($request->lines as $source) {
                $input = $submitted[$source->id];
                $quotation->lines()->create($this->buildLine($source, $input, $taxRates));
            }

            if ($quotation->agreed_dpp !== null) {
                AgreedDpp::distribute($quotation->lines()->get(), (float) $quotation->agreed_dpp);
            }

            $request->lead->update(['stage' => LeadStage::Quotation->value]);

            $pm = $request->lead->delegatedTo;
            if ($pm) {
                $this->notify->once($pm, 'quotation.pending_pm_review', "Quotation {$quotation->number} perlu diverifikasi oleh Anda sebelum dikirim ke customer.", $quotation);
            }

            return $quotation;
        });
    }

    /**
     * @param  \App\Models\ProcurementRequestLine  $source
     * @param  array<string, mixed>  $input
     * @param  \Illuminate\Support\Collection<int, mixed>  $taxRates
     * @return array<string, mixed>
     */
    private function buildLine($source, array $input, $taxRates): array
    {
        $taxId = $input['tax_id'] ?? $source->tax_id;
        $taxRate = $input['tax_rate'] ?? ($taxId ? (float) ($taxRates[$taxId] ?? 0) : 0.0);

        $priced = LinePricing::resolve(
            (float) $source->qty,
            (float) $input['selling_price'],
            (float) $source->cost_price,
            isset($input['discount_percent']) ? (float) $input['discount_percent'] : null,
            isset($input['discount_amount']) ? (float) $input['discount_amount'] : null,
        );

        $category = in_array($input['category'] ?? null, ['material', 'service', 'reimburse'], true)
            ? $input['category']
            : ($source->category ?? $source->vendorProduct?->category ?? 'material');

        return [
            'procurement_request_line_id' => $source->id,
            'item_name' => $source->item_name,
            'category' => $category,
            'description' => $source->description,
            'sourcing_note' => array_key_exists('sourcing_note', $input)
                ? ($input['sourcing_note'] ?: null)
                : $source->sourcing_note,
            'qty' => $source->qty,
            'unit' => $source->unit,
            'cost_price' => $source->cost_price,
            'selling_price' => $input['selling_price'],
            'discount_percent' => $priced['discount_percent'],
            'discount_amount' => $priced['discount_amount'],
            'markup_percent' => $priced['markup_percent'],
            'tax_id' => $taxId,
            'tax_rate' => round((float) $taxRate, 2),
            'subtotal' => $priced['subtotal'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $submittedLines
     * @return array<int, array<string, mixed>>
     */
    private function validatedLines(ProcurementRequest $request, array $submittedLines): array
    {
        $byId = collect($submittedLines)->keyBy(fn (array $line) => (int) $line['procurement_request_line_id']);
        $sourceIds = $request->lines->pluck('id')->sort()->values();

        if ($request->lines->isEmpty() || $sourceIds->all() !== $byId->keys()->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'lines' => 'Seluruh line Procurement harus disertakan tanpa tambahan atau pengurangan.',
            ]);
        }

        return $byId->all();
    }
}
