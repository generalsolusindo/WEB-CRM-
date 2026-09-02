<?php

namespace App\Actions\Sales;

use App\Enums\LeadStage;
use App\Enums\LeadType;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitRequirementsToProcurement
{
    public function handle(Lead $lead, User $user): ProcurementRequest
    {
        return DB::transaction(function () use ($lead, $user) {
            $lockedLead = Lead::query()
                ->whereKey($lead->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLead->type !== LeadType::Opportunity->value) {
                throw ValidationException::withMessages([
                    'lead' => 'Lead harus berupa opportunity sebelum dikirim ke Procurement.',
                ]);
            }

            if ($lockedLead->requirementsLocked()) {
                throw ValidationException::withMessages([
                    'lead' => 'Opportunity ini masih diproses Procurement.',
                ]);
            }

            $requirements = $lockedLead->requirements()->orderBy('id')->get();

            if ($requirements->isEmpty()) {
                throw ValidationException::withMessages([
                    'requirements' => 'Tambahkan minimal satu requirement sebelum submit.',
                ]);
            }

            $procurementRequest = $lockedLead->procurementRequests()->create([
                'status' => 'submitted',
                'requested_by' => $user->id,
                'notes' => null,
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

            $lockedLead->update(['stage' => LeadStage::Procurement->value]);

            return $procurementRequest;
        });
    }
}
