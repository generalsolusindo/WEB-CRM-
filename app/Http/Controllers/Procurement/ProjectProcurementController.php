<?php

namespace App\Http\Controllers\Procurement;

use App\Actions\Procurement\UpdateActualProcurement;
use App\Enums\ActualProcurementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\UpdateActualProcurementRequest;
use App\Models\ActualProcurement;
use App\Models\VendorProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectProcurementController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', \App\Models\Vendor::class);

        $items = ActualProcurement::query()
            ->whereHas('project', fn ($q) => $q->where('status', '!=', 'completed'))
            ->with([
                'project:id,status,sales_order_id',
                'project.salesOrder:id,number,contact_id',
                'project.salesOrder.contact:id,name',
                'vendor:id,name',
            ])
            ->orderByRaw("FIELD(status, 'pending', 'purchased', 'received')")
            ->orderBy('project_id')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'project_number' => 'PRJ-'.str_pad((string) $item->project_id, 6, '0', STR_PAD_LEFT),
                'project_id' => $item->project_id,
                'customer' => $item->project->salesOrder->contact->name ?? '—',
                'sales_order' => $item->project->salesOrder->number,
                'item_name' => $item->item_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'vendor_product_id' => $item->vendor_product_id,
                'vendor' => $item->vendor?->name,
                'cost_price' => $item->cost_price,
                'status' => $item->status,
                'notes' => $item->notes,
                'is_extra' => $item->requested_by !== null,
            ]);

        return Inertia::render('Procurement/ProjectProcurements/Index', [
            'items' => $items,
            'statusOptions' => ActualProcurementStatus::options(),
            'catalog' => VendorProduct::query()
                ->where('is_active', true)
                ->with('vendor:id,name')
                ->orderBy('item_name')
                ->get(['id', 'vendor_id', 'item_name', 'price', 'unit']),
        ]);
    }

    public function update(
        UpdateActualProcurementRequest $request,
        ActualProcurement $actualProcurement,
        UpdateActualProcurement $action,
    ): RedirectResponse {
        Gate::authorize('update', $actualProcurement);

        $action->handle($actualProcurement, $request->user(), $request->validated());

        return back()->with('success', 'Data pengadaan diperbarui.');
    }
}
