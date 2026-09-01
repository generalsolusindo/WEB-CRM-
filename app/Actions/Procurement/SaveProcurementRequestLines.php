<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementRequestStatus;
use App\Models\ProcurementRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProcurementRequestLines
{
    /**
     * @param  array<int, array<string, mixed>>  $submittedLines
     */
    public function handle(ProcurementRequest $procurementRequest, array $submittedLines): ProcurementRequest
    {
        return DB::transaction(function () use ($procurementRequest, $submittedLines) {
            $locked = ProcurementRequest::query()
                ->with('lines')
                ->whereKey($procurementRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [
                ProcurementRequestStatus::Submitted->value,
                ProcurementRequestStatus::Searching->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'procurement_request' => 'Procurement Request ini tidak lagi dapat diubah.',
                ]);
            }

            $byId = collect($submittedLines)->keyBy(fn (array $line) => (int) $line['id']);
            $ownIds = $locked->lines->pluck('id')->sort()->values();

            if ($ownIds->all() !== $byId->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'lines' => 'Seluruh line harus disertakan tanpa tambahan atau pengurangan.',
                ]);
            }

            foreach ($locked->lines as $line) {
                $input = $byId->get($line->id);

                $line->update([
                    'vendor_product_id' => $input['vendor_product_id'] ?? null,
                    'cost_price' => $input['cost_price'],
                    'tax_id' => $input['tax_id'] ?? null,
                    'availability_status' => $input['availability_status'],
                ]);
            }

            return $locked->refresh();
        });
    }
}
