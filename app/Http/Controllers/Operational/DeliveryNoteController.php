<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\CreateDeliveryNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\StoreDeliveryNoteRequest;
use App\Http\Requests\Operational\UploadDeliveryReceivedProofRequest;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function index(SalesOrder $salesOrder): Response
    {
        Gate::authorize('viewAny', DeliveryNote::class);

        $salesOrder->load('contact:id,name,company_name');

        $deliveryNotes = $salesOrder->deliveryNotes()
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (DeliveryNote $dn) => [
                'id' => $dn->id,
                'number' => $dn->number,
                'status' => $dn->status,
                'created_by' => $dn->creator?->name,
                'created_at' => $dn->created_at,
            ]);

        return Inertia::render('Operational/DeliveryNotes/Index', [
            'salesOrder' => [
                'id' => $salesOrder->id,
                'number' => $salesOrder->number,
                'customer' => $salesOrder->contact?->name,
            ],
            'deliveryNotes' => $deliveryNotes,
            'canCreate' => request()->user()->can('create', DeliveryNote::class)
                && $this->remainingMaterialLines($salesOrder)->isNotEmpty(),
        ]);
    }

    public function create(SalesOrder $salesOrder): Response
    {
        Gate::authorize('create', DeliveryNote::class);

        $salesOrder->load('contact:id,name,company_name,address');

        return Inertia::render('Operational/DeliveryNotes/Create', [
            'salesOrder' => [
                'id' => $salesOrder->id,
                'number' => $salesOrder->number,
                'customer' => $salesOrder->contact?->name,
            ],
            'defaultAddress' => $salesOrder->contact?->address,
            'lines' => $this->remainingMaterialLines($salesOrder)->values(),
        ]);
    }

    public function store(StoreDeliveryNoteRequest $request, SalesOrder $salesOrder, CreateDeliveryNote $action): RedirectResponse
    {
        $deliveryNote = $action->handle(
            $salesOrder,
            $request->user(),
            $request->validated('lines'),
            $request->validated('delivery_method'),
            $request->validated('delivery_address'),
            $request->validated('shipper_name'),
            $request->validated('tracking_number'),
            $request->validated('approved_by_name'),
            $request->file('dispatch_proof'),
        );

        return redirect()->route('operational.delivery-notes.show', $deliveryNote)
            ->with('success', 'Delivery Note berhasil dibuat.');
    }

    public function show(DeliveryNote $deliveryNote): Response
    {
        Gate::authorize('view', $deliveryNote);

        $deliveryNote->load([
            'salesOrder:id,number,contact_id,po_number,po_date',
            'salesOrder.contact:id,name,company_name,address',
            'invoice:id,number,created_at',
            'lines',
            'receivedBy:id,name',
            'creator:id,name',
            'attachments',
        ]);

        return Inertia::render('Operational/DeliveryNotes/Show', [
            'deliveryNote' => $this->payload($deliveryNote),
            'canUploadReceivedProof' => request()->user()->can('uploadReceivedProof', $deliveryNote),
        ]);
    }

    public function uploadReceivedProof(UploadDeliveryReceivedProofRequest $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $deliveryNote->attachments()->create([
            'category' => 'delivery_received_proof',
            'file_path' => $request->file('proof')->store('delivery-proofs'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Bukti barang diterima customer tersimpan.');
    }

    public function pdf(DeliveryNote $deliveryNote): \Illuminate\Http\Response
    {
        Gate::authorize('view', $deliveryNote);

        $deliveryNote->load([
            'salesOrder.contact:id,name,company_name,address',
            'invoice:id,number,created_at',
            'lines',
        ]);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('operational.delivery-notes.print', [
            'deliveryNote' => $deliveryNote,
            'forPdf' => true,
        ])->stream(str_replace('/', '-', $deliveryNote->number).'.pdf');
    }

    /** @return array<string, mixed> */
    private function payload(DeliveryNote $deliveryNote): array
    {
        $dispatchProof = $deliveryNote->attachments->firstWhere('category', 'delivery_dispatch_proof');
        $receivedProof = $deliveryNote->attachments->firstWhere('category', 'delivery_received_proof');

        return [
            'id' => $deliveryNote->id,
            'number' => $deliveryNote->number,
            'status' => $deliveryNote->status,
            'delivery_method' => $deliveryNote->delivery_method,
            'delivery_address' => $deliveryNote->delivery_address,
            'shipper_name' => $deliveryNote->shipper_name,
            'tracking_number' => $deliveryNote->tracking_number,
            'approved_by_name' => $deliveryNote->approved_by_name,
            'created_by' => $deliveryNote->creator?->name,
            'created_at' => $deliveryNote->created_at,
            'received_by' => $deliveryNote->receivedBy?->name,
            'received_at' => $deliveryNote->received_at,
            'dispatch_proof_url' => $dispatchProof
                ? \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($dispatchProof->file_path, now()->addDay())
                : null,
            'received_proof_url' => $receivedProof
                ? \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($receivedProof->file_path, now()->addDay())
                : null,
            'sales_order' => [
                'id' => $deliveryNote->salesOrder->id,
                'number' => $deliveryNote->salesOrder->number,
                'customer' => $deliveryNote->salesOrder->contact?->name,
                'po_number' => $deliveryNote->salesOrder->po_number,
                'po_date' => $deliveryNote->salesOrder->po_date,
            ],
            'invoice_number' => $deliveryNote->invoice?->number,
            'invoice_date' => $deliveryNote->invoice?->created_at,
            'lines' => $deliveryNote->lines->map(fn (DeliveryNoteLine $l) => [
                'item_name' => $l->item_name,
                'unit' => $l->unit,
                'qty_ordered' => (float) $l->qty_ordered,
                'qty_previous_balance' => (float) $l->qty_previous_balance,
                'qty_delivered' => (float) $l->qty_delivered,
                'qty_balance' => (float) $l->qty_balance,
            ]),
        ];
    }

    /** Baris material SO yang masih punya sisa belum terkirim, siap dipakai form buat DN baru. */
    private function remainingMaterialLines(SalesOrder $salesOrder)
    {
        $salesOrder->loadMissing('lines');
        $materialLines = $salesOrder->lines->where('category', 'material');

        $alreadyDelivered = DeliveryNoteLine::query()
            ->whereIn('sales_order_line_id', $materialLines->pluck('id'))
            ->selectRaw('sales_order_line_id, SUM(qty_delivered) as total')
            ->groupBy('sales_order_line_id')
            ->pluck('total', 'sales_order_line_id');

        return $materialLines
            ->map(function ($line) use ($alreadyDelivered) {
                $delivered = (float) ($alreadyDelivered[$line->id] ?? 0);

                return [
                    'sales_order_line_id' => $line->id,
                    'item_name' => $line->item_name,
                    'unit' => $line->unit,
                    'qty_ordered' => (float) $line->qty,
                    'qty_remaining' => round((float) $line->qty - $delivered, 2),
                ];
            })
            ->filter(fn ($line) => $line['qty_remaining'] > 0);
    }
}
