<?php

namespace App\Http\Controllers\Warehouse;

use App\Actions\Warehouse\AdjustWarehouseStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\AdjustWarehouseStockRequest;
use App\Http\Requests\Warehouse\StoreWarehouseItemRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseItemRequest;
use App\Models\WarehouseItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseItemController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WarehouseItem::class);

        $search = $request->string('search')->trim()->toString();

        $items = WarehouseItem::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Warehouse/Items/Index', [
            'items' => $items,
            'filters' => ['search' => $search],
            'canManage' => $request->user()->can('create', WarehouseItem::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', WarehouseItem::class);

        return Inertia::render('Warehouse/Items/Form');
    }

    public function store(StoreWarehouseItemRequest $request): RedirectResponse
    {
        WarehouseItem::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('warehouse.items.index')->with('success', 'Barang berhasil ditambahkan.');
    }

    public function edit(WarehouseItem $item): Response
    {
        Gate::authorize('update', $item);

        return Inertia::render('Warehouse/Items/Form', ['item' => $item]);
    }

    public function update(UpdateWarehouseItemRequest $request, WarehouseItem $item): RedirectResponse
    {
        $item->update($request->validated());

        return redirect()->route('warehouse.items.index')->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(WarehouseItem $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return redirect()->route('warehouse.items.index')->with('success', 'Barang berhasil dihapus.');
    }

    public function adjust(AdjustWarehouseStockRequest $request, WarehouseItem $item, AdjustWarehouseStock $action): RedirectResponse
    {
        $action->handle($item, $request->validated('direction'), (int) $request->validated('qty'));

        return back()->with('success', 'Stok berhasil diperbarui.');
    }
}
