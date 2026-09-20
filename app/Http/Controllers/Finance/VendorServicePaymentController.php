<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CancelVendorServicePayment;
use App\Actions\Finance\RecordVendorServicePayment;
use App\Enums\VendorServicePaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordVendorServicePaymentRequest;
use App\Models\VendorServicePayment;
use App\Models\VendorServicePaymentEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Pembayaran jasa vendor luar (DP & pelunasan setelah BAST) — nominal & rekening dari deal Procurement. */
class VendorServicePaymentController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', VendorServicePayment::class);

        $payments = VendorServicePayment::query()
            ->with(['vendor:id,name', 'project.salesOrder:id,number,contact_id', 'project.salesOrder.contact:id,name'])
            ->orderByRaw("FIELD(status, 'awaiting_dp', 'in_progress', 'paid')")
            ->latest('id')
            ->get()
            ->map(fn (VendorServicePayment $p) => [
                'id' => $p->id,
                'number' => $p->number,
                'project_number' => 'PRJ-'.str_pad((string) $p->project_id, 6, '0', STR_PAD_LEFT),
                'customer' => $p->project->salesOrder->contact->name ?? '—',
                'vendor' => $p->vendor->name,
                'total_fee' => $p->total_fee,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'next' => $this->nextStep($p),
            ]);

        return Inertia::render('Finance/VendorServicePayments/Index', ['payments' => $payments]);
    }

    public function show(VendorServicePayment $vendorServicePayment): Response
    {
        Gate::authorize('view', $vendorServicePayment);

        $p = $vendorServicePayment->load([
            'vendor:id,name,contact_person,phone',
            'project.salesOrder:id,number,contact_id',
            'project.salesOrder.contact:id,name',
            'submitter:id,name',
            'entries.attachments',
            'entries.payer:id,name',
        ]);
        $cancelled = $p->entries()->onlyTrashed()->with(['attachments', 'canceller:id,name'])->latest('id')->get();
        $user = request()->user();
        $canPay = $user->can('pay', $p);

        $entryRow = fn (VendorServicePaymentEntry $e) => [
            'id' => $e->id,
            'kind' => $e->kind,
            'amount' => $e->amount,
            'paid_at' => $e->paid_at,
            'notes' => $e->notes,
            'paid_by' => $e->payer?->name,
            'proofs' => $e->attachments->where('category', 'payment_proof')->map(fn ($a) => [
                'id' => $a->id,
                'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
            ])->values(),
        ];

        return Inertia::render('Finance/VendorServicePayments/Show', [
            'payment' => [
                'id' => $p->id,
                'number' => $p->number,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'project_number' => 'PRJ-'.str_pad((string) $p->project_id, 6, '0', STR_PAD_LEFT),
                'customer' => $p->project->salesOrder->contact->name ?? '—',
                'sales_order' => $p->project->salesOrder->number,
                'vendor' => ['name' => $p->vendor->name, 'contact_person' => $p->vendor->contact_person, 'phone' => $p->vendor->phone],
                'total_fee' => $p->total_fee,
                'terms' => $p->terms,
                'dp_amount' => $p->dp_amount,
                'dp_percent' => $p->dpPercent(),
                'final_amount' => $p->finalAmount(),
                'bank_name' => $p->bank_name,
                'account_number' => $p->account_number,
                'account_holder' => $p->account_holder,
                'notes' => $p->notes,
                'submitted_by' => $p->submitter?->name,
                'bast_verified' => $p->bastVerified(),
            ],
            'entries' => $p->entries->map($entryRow)->values(),
            'cancelledEntries' => $cancelled->map(fn ($e) => [
                ...$entryRow($e),
                'cancelled_at' => $e->deleted_at,
                'cancelled_by' => $e->canceller?->name,
                'reason' => $e->cancellation_reason,
            ])->values(),
            'canPayDp' => $canPay && $p->canPayDp(),
            'canPayFinal' => $canPay && $p->canPayFinal(),
            'canCancel' => $canPay,
        ]);
    }

    public function pay(RecordVendorServicePaymentRequest $request, VendorServicePayment $vendorServicePayment, RecordVendorServicePayment $action): RedirectResponse
    {
        $action->handle(
            $vendorServicePayment,
            $request->user(),
            $request->validated('kind'),
            $request->validated('paid_at'),
            $request->validated('notes'),
            $request->file('proof'),
        );

        return back()->with('success', $request->validated('kind') === VendorServicePaymentEntry::KIND_DP
            ? 'DP tercatat. Project dilepas ke Operasional.'
            : 'Pelunasan tercatat. Deal vendor lunas.');
    }

    public function cancel(Request $request, VendorServicePayment $vendorServicePayment, int $entry, CancelVendorServicePayment $action): RedirectResponse
    {
        Gate::authorize('pay', $vendorServicePayment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $target = $vendorServicePayment->entries()->whereKey($entry)->firstOrFail();
        $action->handle($vendorServicePayment, $target, $request->user(), $data['reason']);

        return back()->with('success', 'Pembayaran vendor dibatalkan. Status deal dihitung ulang.');
    }

    private function nextStep(VendorServicePayment $p): string
    {
        return match (true) {
            $p->status === VendorServicePaymentStatus::Paid => 'Lunas',
            $p->canPayDp() => 'Bayar DP',
            $p->canPayFinal() => 'Bayar pelunasan',
            $p->status === VendorServicePaymentStatus::InProgress => 'Menunggu BAST diverifikasi',
            default => '—',
        };
    }
}
