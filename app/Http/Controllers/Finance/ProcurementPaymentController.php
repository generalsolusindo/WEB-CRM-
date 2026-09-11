<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\RecordProcurementPayment;
use App\Enums\ProcurementPaymentStatus;
use App\Http\Controllers\Concerns\BuildsProcurementPaymentView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordProcurementPaymentRequest;
use App\Models\ProcurementPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementPaymentController extends Controller
{
    use BuildsProcurementPaymentView;

    public function index(): Response
    {
        Gate::authorize('viewAny', ProcurementPayment::class);

        $payments = ProcurementPayment::query()
            ->whereIn('status', [
                ProcurementPaymentStatus::ApprovedPm->value,
                ProcurementPaymentStatus::Paid->value,
                ProcurementPaymentStatus::Confirmed->value,
            ])
            ->with(['project.salesOrder:id,number,contact_id', 'project.salesOrder.contact:id,name'])
            ->orderByRaw("FIELD(status, 'approved_pm', 'paid', 'confirmed')")
            ->latest('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->number,
                'project_number' => 'PRJ-'.str_pad((string) $p->project_id, 6, '0', STR_PAD_LEFT),
                'customer' => $p->project->salesOrder->contact->name ?? '—',
                'sales_order' => $p->project->salesOrder->number,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
            ]);

        return Inertia::render('ProcurementPayments/Pay/Index', [
            'payments' => $payments,
        ]);
    }

    public function show(ProcurementPayment $procurementPayment): Response
    {
        Gate::authorize('view', $procurementPayment);

        return Inertia::render('ProcurementPayments/Pay/Show', [
            'payment' => $this->procurementPaymentDetail($procurementPayment),
            'canPay' => $procurementPayment->status === ProcurementPaymentStatus::ApprovedPm
                && request()->user()->can('pay', $procurementPayment),
        ]);
    }

    public function pay(
        RecordProcurementPaymentRequest $request,
        ProcurementPayment $procurementPayment,
        RecordProcurementPayment $action,
    ): RedirectResponse {
        $action->handle($procurementPayment, $request->user(), [
            'item_ids' => $request->input('item_ids', []),
            'proof' => $request->file('proof'),
            'proof_scope' => $request->input('proof_scope', 'all'),
        ]);

        return back()->with('success', 'Pembayaran vendor dicatat.');
    }
}
