<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\SubmitRequirementsToProcurement;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SubmitProcurementRequestController extends Controller
{
    public function __invoke(
        Lead $lead,
        SubmitRequirementsToProcurement $action,
    ): RedirectResponse {
        Gate::authorize('submitToProcurement', $lead);
        $action->handle($lead, request()->user());

        return redirect()->route('sales.leads.show', $lead)
            ->with('success', 'Requirement berhasil dikirim ke Procurement.');
    }
}
