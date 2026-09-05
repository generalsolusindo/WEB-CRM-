<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorizeMember($project);

        $project->loadMissing('salesOrder:id,number,contact_id', 'salesOrder.contact:id,name');

        $deliveryNotes = $project->salesOrder->deliveryNotes()
            ->latest()
            ->get()
            ->map(fn (DeliveryNote $dn) => [
                'id' => $dn->id,
                'number' => $dn->number,
                'status' => $dn->status,
                'created_at' => $dn->created_at,
            ]);

        return Inertia::render('Technician/DeliveryNotes/Index', [
            'project' => [
                'id' => $project->id,
                'number' => 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'customer' => $project->salesOrder->contact?->name,
            ],
            'deliveryNotes' => $deliveryNotes,
        ]);
    }

    public function show(DeliveryNote $deliveryNote): Response
    {
        Gate::authorize('view', $deliveryNote);

        $deliveryNote->load([
            'salesOrder:id,number,contact_id',
            'salesOrder.contact:id,name,company_name',
            'lines',
            'receivedBy:id,name',
        ]);

        return Inertia::render('Technician/DeliveryNotes/Show', [
            'deliveryNote' => [
                'id' => $deliveryNote->id,
                'number' => $deliveryNote->number,
                'status' => $deliveryNote->status,
                'delivery_address' => $deliveryNote->delivery_address,
                'sales_order' => [
                    'number' => $deliveryNote->salesOrder->number,
                    'customer' => $deliveryNote->salesOrder->contact?->name,
                ],
                'received_by' => $deliveryNote->receivedBy?->name,
                'received_at' => $deliveryNote->received_at,
                'lines' => $deliveryNote->lines->map(fn ($l) => [
                    'item_name' => $l->item_name,
                    'unit' => $l->unit,
                    'qty_delivered' => (float) $l->qty_delivered,
                    'qty_balance' => (float) $l->qty_balance,
                ]),
            ],
            'canReceive' => request()->user()->can('receive', $deliveryNote),
        ]);
    }

    public function receive(DeliveryNote $deliveryNote): RedirectResponse
    {
        Gate::authorize('receive', $deliveryNote);

        $deliveryNote->update([
            'status' => 'received',
            'received_by' => request()->user()->id,
            'received_at' => now(),
        ]);

        return back()->with('success', 'Delivery Note dikonfirmasi diterima.');
    }

    private function authorizeMember(Project $project): void
    {
        $isMember = $project->technicians()
            ->where('technician_id', request()->user()->id)
            ->exists();

        abort_unless($isMember, 403);
    }
}
