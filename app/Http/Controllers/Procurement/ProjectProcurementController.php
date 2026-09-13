<?php

namespace App\Http\Controllers\Procurement;

use App\Actions\Procurement\ConfirmProcurementPayment;
use App\Actions\Procurement\ReceiveProcurementItem;
use App\Actions\Procurement\SubmitProcurementPayment;
use App\Enums\ActualProcurementStatus;
use App\Enums\ProcurementPaymentStatus;
use App\Http\Controllers\Concerns\BuildsProcurementPaymentView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\SaveProcurementSourcingRequest;
use App\Models\ActualProcurement;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectProcurementController extends Controller
{
    use BuildsProcurementPaymentView;

    public function index(): Response
    {
        Gate::authorize('viewAny', ProcurementPayment::class);

        $projects = Project::query()
            ->whereHas('actualProcurements')
            ->where('status', '!=', 'completed')
            ->with([
                'salesOrder:id,number,contact_id',
                'salesOrder.contact:id,name',
                'procurementPayment',
            ])
            ->withCount([
                'actualProcurements as items_count',
                'actualProcurements as received_count' => fn ($q) => $q->where('status', ActualProcurementStatus::Received->value),
            ])
            ->latest('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'number' => 'PRJ-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT),
                'customer' => $p->salesOrder->contact->name ?? '—',
                'sales_order' => $p->salesOrder->number,
                'items_count' => $p->items_count,
                'received_count' => $p->received_count,
                'payment_status' => $p->procurementPayment?->status->value,
                'payment_status_label' => $p->procurementPayment?->status->label() ?? 'Belum diajukan',
                'payment_number' => $p->procurementPayment?->number,
            ]);

        return Inertia::render('Procurement/ProjectProcurements/Index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project): Response
    {
        Gate::authorize('submit', [ProcurementPayment::class, $project]);

        $project->load([
            'salesOrder:id,number,contact_id',
            'salesOrder.contact:id,name,company_name',
            'actualProcurements' => fn ($q) => $q->orderBy('id'),
            'actualProcurements.vendor:id,name',
            'actualProcurements.warehouseItem:id,name,unit,qty_on_hand',
            'procurementPayment.lumpSumVendor:id,name',
            'procurementPayment.pmReviewedBy:id,name',
            'procurementPayment.financePaidBy:id,name',
            'procurementPayment.confirmedBy:id,name',
            'procurementPayments' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $payment = $project->procurementPayment;
        $hasUnlinked = $project->actualProcurements->whereNull('procurement_payment_id')->isNotEmpty();
        $editable = $payment === null || $payment->status->isEditable() || $hasUnlinked;

        return Inertia::render('Procurement/ProjectProcurements/Show', [
            'project' => [
                'id' => $project->id,
                'number' => 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'status' => $project->status,
                'customer' => $project->salesOrder->contact->name ?? '—',
                'company' => $project->salesOrder->contact->company_name,
                'sales_order' => $project->salesOrder->number,
            ],
            'items' => $project->actualProcurements->map(fn ($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'is_extra' => $item->requested_by !== null,
                'vendor_id' => $item->vendor_id,
                'vendor_name' => $item->vendor?->name,
                'cost_price' => $item->cost_price,
                'from_office_stock' => $item->from_office_stock,
                'office_stock_note' => $item->office_stock_note,
                'warehouse_item_id' => $item->warehouse_item_id,
                'warehouse_qty' => $item->warehouse_qty,
                'warehouse_item_name' => $item->warehouseItem?->name,
                'warehouse_item_unit' => $item->warehouseItem?->unit,
                'warehouse_item_stock' => $item->warehouseItem?->qty_on_hand,
                'bank_account_note' => $item->bank_account_note,
                'is_paid' => $item->is_paid,
                'status' => $item->status,
                'row_locked' => $item->is_paid || $item->status === 'received'
                    || ($item->procurement_payment_id !== null && $payment && ! $payment->status->isEditable()),
            ]),
            'payment' => $payment ? [
                'id' => $payment->id,
                'number' => $payment->number,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'pricing_mode' => $payment->pricing_mode,
                'lump_sum_vendor_id' => $payment->lump_sum_vendor_id,
                'lump_sum_vendor_name' => $payment->lumpSumVendor?->name,
                'lump_sum_amount' => $payment->lump_sum_amount,
                'bank_account_note' => $payment->bank_account_note,
                'pm_notes' => $payment->pm_notes,
                'pm_reviewed_by' => $payment->pmReviewedBy?->name,
                'pm_reviewed_at' => $payment->pm_reviewed_at,
                'finance_paid_by' => $payment->financePaidBy?->name,
                'finance_paid_at' => $payment->finance_paid_at,
            ] : null,
            'editable' => $editable,
            'canConfirm' => $payment !== null && $payment->status === ProcurementPaymentStatus::Paid,
            'canReceiveAll' => $project->actualProcurements
                ->contains(fn ($i) => $i->is_paid && $i->status !== ActualProcurementStatus::Received->value),
            'history' => $project->procurementPayments->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->number,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'submitted_at' => $p->submitted_at,
            ]),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name', 'bank_account_note']),
            'catalog' => VendorProduct::query()
                ->where('is_active', true)
                ->with('vendor:id,name,bank_account_note')
                ->orderBy('item_name')
                ->get(['id', 'vendor_id', 'item_name', 'price', 'unit']),
            'warehouseItems' => \App\Models\WarehouseItem::query()
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'qty_on_hand']),
        ]);
    }

    public function saveSourcing(SaveProcurementSourcingRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('submit', [ProcurementPayment::class, $project]);

        $payment = $project->procurementPayment;
        $hasUnlinked = $project->actualProcurements()->whereNull('procurement_payment_id')->exists();
        abort_unless($payment === null || $payment->status->isEditable() || $hasUnlinked, 409);

        $byId = collect($request->validated()['lines'])->keyBy('id');
        $paymentEditable = $payment === null || $payment->status->isEditable();

        foreach ($project->actualProcurements as $item) {
            $input = $byId->get($item->id);
            if ($input === null) {
                continue;
            }

            // Baris yang sudah dibayar/diterima atau terkunci di pengajuan yang sedang jalan — lewati.
            $locked = $item->is_paid || $item->status === 'received'
                || ($item->procurement_payment_id !== null && ! $paymentEditable);
            if ($locked) {
                continue;
            }

            $office = (bool) ($input['from_office_stock'] ?? false);
            $item->update([
                'from_office_stock' => $office,
                'office_stock_note' => $office ? ($input['office_stock_note'] ?? null) : null,
                'warehouse_item_id' => $office ? ($input['warehouse_item_id'] ?? null) : null,
                'warehouse_qty' => $office ? ($input['warehouse_qty'] ?? null) : null,
                'vendor_id' => $office ? null : ($input['vendor_id'] ?? null),
                'cost_price' => $office ? 0 : ($input['cost_price'] ?? 0),
                'bank_account_note' => $office ? null : ($input['bank_account_note'] ?? null),
            ]);
        }

        return back()->with('success', 'Sourcing disimpan.');
    }

    public function submit(SaveProcurementSourcingRequest $request, Project $project, SubmitProcurementPayment $action): RedirectResponse
    {
        Gate::authorize('submit', [ProcurementPayment::class, $project]);

        $this->saveSourcing($request, $project);

        $data = $request->validated();
        $action->handle($project, $request->user(), [
            'pricing_mode' => $data['pricing_mode'] ?? 'itemized',
            'lump_sum_vendor_id' => $data['lump_sum_vendor_id'] ?? null,
            'lump_sum_amount' => $data['lump_sum_amount'] ?? null,
            'bank_account_note' => $data['bank_account_note'] ?? null,
        ]);

        return back()->with('success', 'Pengajuan pembayaran dikirim ke Project Manager.');
    }

    public function confirm(Project $project, ConfirmProcurementPayment $action): RedirectResponse
    {
        $payment = $project->procurementPayment;
        abort_unless($payment !== null, 404);
        Gate::authorize('confirm', $payment);

        $action->handle($payment, request()->user());

        return back()->with('success', 'Pembayaran dikonfirmasi. Tinggal menunggu barang datang.');
    }

    public function receiveItem(ActualProcurement $actualProcurement, ReceiveProcurementItem $action): RedirectResponse
    {
        Gate::authorize('submit', [ProcurementPayment::class, $actualProcurement->project]);

        $action->handle($actualProcurement, request()->user());

        return back()->with('success', 'Barang ditandai sudah diterima.');
    }

    public function receiveAll(Project $project, ReceiveProcurementItem $action): RedirectResponse
    {
        Gate::authorize('submit', [ProcurementPayment::class, $project]);

        $pending = $project->actualProcurements()
            ->where('is_paid', true)
            ->where('status', '!=', ActualProcurementStatus::Received->value)
            ->get();

        foreach ($pending as $item) {
            $action->handle($item, request()->user());
        }

        return back()->with('success', "{$pending->count()} barang ditandai sudah diterima.");
    }

    public function showPayment(ProcurementPayment $procurementPayment): Response
    {
        Gate::authorize('submit', [ProcurementPayment::class, $procurementPayment->project]);

        return Inertia::render('ProcurementPayments/View/Show', [
            'payment' => $this->procurementPaymentDetail($procurementPayment),
        ]);
    }
}
