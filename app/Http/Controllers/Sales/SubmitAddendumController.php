<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\SubmitAddendumToProcurement;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SubmitAddendumController extends Controller
{
    public function __invoke(
        Lead $lead,
        SubmitAddendumToProcurement $action,
    ): RedirectResponse {
        Gate::authorize('submitAddendum', $lead);
        $action->handle($lead, request()->user());

        return redirect()->route('sales.leads.show', $lead)
            ->with('success', 'Pengajuan tambahan berhasil dikirim ke Procurement.');
    }
}
