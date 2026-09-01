<?php

namespace App\Http\Controllers\Procurement;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorProductRequest;
use App\Http\Requests\Procurement\UpdateVendorProductRequest;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VendorProductController extends Controller
{
    public function create(Request $request): Response
    {
        Gate::authorize('create', VendorProduct::class);

        $vendor = Vendor::findOrFail($request->integer('vendor_id'));

        return Inertia::render('Procurement/VendorProducts/Form', [
            'vendor' => $vendor->only('id', 'name'),
            'categoryOptions' => ProductCategory::options(),
        ]);
    }

    public function store(StoreVendorProductRequest $request): RedirectResponse
    {
        $product = VendorProduct::create($request->validated());

        return redirect()->route('procurement.vendors.show', $product->vendor_id)
            ->with('success', 'Produk vendor berhasil ditambahkan.');
    }

    public function edit(VendorProduct $vendorProduct): Response
    {
        Gate::authorize('update', $vendorProduct);

        $vendorProduct->load('vendor:id,name');

        return Inertia::render('Procurement/VendorProducts/Form', [
            'vendorProduct' => $vendorProduct,
            'vendor' => $vendorProduct->vendor->only('id', 'name'),
            'categoryOptions' => ProductCategory::options(),
        ]);
    }

    public function update(UpdateVendorProductRequest $request, VendorProduct $vendorProduct): RedirectResponse
    {
        $vendorProduct->update($request->validated());

        return redirect()->route('procurement.vendors.show', $vendorProduct->vendor_id)
            ->with('success', 'Produk vendor berhasil diperbarui.');
    }

    public function destroy(VendorProduct $vendorProduct): RedirectResponse
    {
        Gate::authorize('delete', $vendorProduct);

        if ($vendorProduct->procurementRequestLines()->exists()) {
            return back()->with('error', 'Produk sudah dipakai pada Procurement Request. Nonaktifkan saja, jangan dihapus.');
        }

        $vendorId = $vendorProduct->vendor_id;
        $vendorProduct->delete();

        return redirect()->route('procurement.vendors.show', $vendorId)
            ->with('success', 'Produk vendor berhasil dihapus.');
    }
}
