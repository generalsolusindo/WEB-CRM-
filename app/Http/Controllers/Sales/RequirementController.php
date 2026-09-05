<?php

namespace App\Http\Controllers\Sales;

use App\Enums\LeadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreRequirementRequest;
use App\Http\Requests\Sales\UpdateRequirementRequest;
use App\Models\Lead;
use App\Models\Requirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RequirementController extends Controller
{
    public function store(StoreRequirementRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless($lead->sales_id === $request->user()->id, 403);

        if ($lead->type !== LeadType::Opportunity->value) {
            return back()->with('error', 'Lead harus dikonversi menjadi opportunity sebelum menambah requirement.');
        }

        if ($lead->requirementsLocked()) {
            return back()->with('error', 'Requirement sudah dikunci karena telah dikirim ke Procurement.');
        }

        $lead->requirements()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Requirement berhasil ditambahkan.');
    }

    public function update(
        UpdateRequirementRequest $request,
        Lead $lead,
        Requirement $requirement,
    ): RedirectResponse {
        $this->ensureRequirementBelongsToLead($lead, $requirement);
        $requirement->update($request->validated());

        return back()->with('success', 'Requirement berhasil diperbarui.');
    }

    public function destroy(Lead $lead, Requirement $requirement): RedirectResponse
    {
        $this->ensureRequirementBelongsToLead($lead, $requirement);
        Gate::authorize('delete', $requirement);
        $requirement->delete();

        return back()->with('success', 'Requirement berhasil dihapus.');
    }

    private function ensureRequirementBelongsToLead(Lead $lead, Requirement $requirement): void
    {
        abort_unless($requirement->lead_id === $lead->id, 404);
    }
}
