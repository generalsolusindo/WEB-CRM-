<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreateQuotationRevision;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class QuotationRevisionController extends Controller
{
    public function __invoke(
        Quotation $quotation,
        CreateQuotationRevision $action,
    ): RedirectResponse {
        Gate::authorize('revise', $quotation);
        $revision = $action->handle($quotation, request()->user());

        return redirect()->route('sales.quotations.edit', $revision)
            ->with('success', 'Revision baru berhasil dibuat. Silakan sesuaikan harga sebelum dikirim.');
    }
}
