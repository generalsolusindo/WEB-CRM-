<?php

namespace App\Http\Controllers\ProjectManager;

use App\Actions\Procurement\ReviewProcurementPayment;
use App\Enums\ProcurementPaymentStatus;
use App\Http\Controllers\Concerns\BuildsProcurementPaymentView;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectManager\ReviewProcurementPaymentRequest;
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
            ->whereHas('project', fn ($q) => $q->where('delegated_to', request()->user()->id))
            ->whereIn('status', [
                ProcurementPaymentStatus::PendingPm->value,
                ProcurementPaymentStatus::ApprovedPm->value,
                ProcurementPaymentStatus::Paid->value,
            ])
            ->with(['project.salesOrder:id,number,contact_id', 'project.salesOrder.contact:id,name'])
            ->orderByRaw("FIELD(status, 'pending_pm', 'approved_pm', 'paid')")
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

        return Inertia::render('ProcurementPayments/Review/Index', [
            'payments' => $payments,
        ]);
    }

    public function show(ProcurementPayment $procurementPayment): Response
    {
        Gate::authorize('view', $procurementPayment);

        return Inertia::render('ProcurementPayments/Review/Show', [
            'payment' => $this->procurementPaymentDetail($procurementPayment),
            'canReview' => request()->user()->can('review', $procurementPayment),
        ]);
    }

    public function review(
        ReviewProcurementPaymentRequest $request,
        ProcurementPayment $procurementPayment,
        ReviewProcurementPayment $action,
    ): RedirectResponse {
        $action->handle(
            $procurementPayment,
            $request->user(),
            $request->boolean('approved'),
            $request->input('notes'),
        );

        return redirect()->route('project-manager.procurement-payments.index')
            ->with('success', $request->boolean('approved')
                ? 'Pengajuan disetujui, diteruskan ke Finance.'
                : 'Pengajuan ditolak, dikembalikan ke Procurement.');
    }
}
