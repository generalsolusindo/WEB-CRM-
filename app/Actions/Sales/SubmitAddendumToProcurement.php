<?php

namespace App\Actions\Sales;

use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Submission "tambahan" (addendum) — dipakai saat customer minta pekerjaan/scope
 * tambahan setelah Lead sudah Won dan Sales Order-nya berjalan. Tidak butuh Lead
 * baru: requirement baru yang ditambahkan Sales pada Lead yang sama dikirim sebagai
 * Procurement Request baru yang ditandai `is_addendum`, tetap tertaut ke Sales
 * Order asal untuk pelacakan riwayat.
 */
class SubmitAddendumToProcurement
{
    public function handle(Lead $lead, User $user): ProcurementRequest
    {
        return DB::transaction(function () use ($lead, $user) {
            $lockedLead = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            $originalSalesOrder = $lockedLead->activeSalesOrderForAddendum();

            if (! $originalSalesOrder) {
                throw ValidationException::withMessages([
                    'lead' => 'Opportunity ini belum punya Sales Order aktif — tidak ada dasar untuk pengajuan tambahan.',
                ]);
            }

            $requirements = $lockedLead->requirements()->whereNull('submitted_at')->orderBy('id')->get();

            if ($requirements->isEmpty()) {
                throw ValidationException::withMessages([
                    'requirements' => 'Tambahkan minimal satu requirement baru sebelum mengajukan tambahan.',
                ]);
            }

            $procurementRequest = $lockedLead->procurementRequests()->create([
                'status' => 'submitted',
                'requested_by' => $user->id,
                'notes' => null,
                'is_addendum' => true,
                'addendum_of_sales_order_id' => $originalSalesOrder->id,
            ]);

            foreach ($requirements as $requirement) {
                $procurementRequest->lines()->create([
                    'requirement_id' => $requirement->id,
                    'category' => $requirement->category,
                    'vendor_product_id' => null,
                    'item_name' => $requirement->item_name,
                    'description' => $requirement->description,
                    'qty' => $requirement->qty,
                    'unit' => $requirement->unit,
                    'cost_price' => 0,
                    'tax_id' => null,
                    'availability_status' => 'searching',
                ]);
            }

            $lockedLead->requirements()->whereIn('id', $requirements->pluck('id'))->update(['submitted_at' => now()]);

            return $procurementRequest;
        });
    }
}
