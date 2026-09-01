<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorRequest;
use App\Http\Requests\Procurement\UpdateVendorRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vendor::class);

        $search = trim((string) $request->query('search', ''));

        $vendors = Vendor::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->withCount('products')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Procurement/Vendors/Index', [
            'vendors' => $vendors,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Vendor::class);

        return Inertia::render('Procurement/Vendors/Form');
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $vendor = Vendor::create($request->validated());

        return redirect()->route('procurement.vendors.show', $vendor)
            ->with('success', 'Vendor berhasil dibuat.');
    }

    public function show(Vendor $vendor): Response
    {
        Gate::authorize('view', $vendor);

        $vendor->load([
            'products' => fn ($query) => $query->orderBy('item_name'),
            'technicians' => fn ($query) => $query
                ->select('id', 'vendor_id', 'name', 'email', 'phone', 'is_active')
                ->orderBy('name'),
        ]);

        return Inertia::render('Procurement/Vendors/Show', [
            'vendor' => $vendor,
        ]);
    }

    public function edit(Vendor $vendor): Response
    {
        Gate::authorize('update', $vendor);

        return Inertia::render('Procurement/Vendors/Form', ['vendor' => $vendor]);
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($request->validated());

        return redirect()->route('procurement.vendors.show', $vendor)
            ->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        Gate::authorize('delete', $vendor);

        if ($vendor->products()->exists()) {
            return back()->with('error', 'Vendor masih memiliki produk. Nonaktifkan produknya saja, jangan dihapus.');
        }

        if ($vendor->technicians()->exists()) {
            return back()->with('error', 'Vendor masih punya akun surveyor/teknisi. Pindahkan asal akunnya atau nonaktifkan dulu.');
        }

        if ($vendor->surveys()->whereNotIn('status', ['closed', 'cancelled'])->exists()) {
            return back()->with('error', 'Vendor masih terkait survey yang sedang berjalan.');
        }

        $vendor->delete();

        return redirect()->route('procurement.vendors.index')
            ->with('success', 'Vendor berhasil dihapus.');
    }
}
