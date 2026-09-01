<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\RecordPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $payments = Payment::query()
            ->whereHas('invoice', fn ($query) => $query->where('invoice_type', 'sale'))
            ->with([
                'invoice:id,number,invoice_phase,sales_order_id',
                'invoice.salesOrder:id,number,contact_id',
                'invoice.salesOrder.contact:id,name',
                'recorder:id,name',
            ])
            ->withCount(['attachments as proof_count' => fn ($query) => $query->where('category', 'payment_proof')])
            ->latest('paid_at')
            ->paginate(20);

        return Inertia::render('Finance/Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function store(StorePaymentRequest $request, Invoice $invoice, RecordPayment $action): RedirectResponse
    {
        Gate::authorize('create', [Payment::class, $invoice]);

        $action->handle(
            $invoice,
            $request->user(),
            (float) $request->validated('amount_paid'),
            $request->validated('paid_at'),
            $request->validated('notes'),
            $request->file('proof'),
        );

        return back()->with('success', 'Pembayaran tercatat.');
    }
}
