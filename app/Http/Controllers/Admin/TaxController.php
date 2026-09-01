<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaxRequest;
use App\Http\Requests\Admin\UpdateTaxRequest;
use App\Models\QuotationLine;
use App\Models\SalesOrderLine;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Tax::class);

        return Inertia::render('Admin/Taxes/Index', [
            'taxes' => Tax::query()->orderBy('name')->get(['id', 'name', 'rate', 'is_active']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Tax::class);

        return Inertia::render('Admin/Taxes/Form');
    }

    public function store(StoreTaxRequest $request): RedirectResponse
    {
        Tax::create($request->validated());

        return redirect()->route('admin.taxes.index')
            ->with('success', 'Pajak berhasil dibuat.');
    }

    public function edit(Tax $tax): Response
    {
        Gate::authorize('update', $tax);

        return Inertia::render('Admin/Taxes/Form', ['tax' => $tax]);
    }

    public function update(UpdateTaxRequest $request, Tax $tax): RedirectResponse
    {
        $tax->update($request->validated());

        return redirect()->route('admin.taxes.index')
            ->with('success', 'Pajak berhasil diperbarui.');
    }

    public function destroy(Tax $tax): RedirectResponse
    {
        Gate::authorize('delete', $tax);

        $inUse = QuotationLine::where('tax_id', $tax->id)->exists()
            || SalesOrderLine::where('tax_id', $tax->id)->exists();

        if ($inUse) {
            return back()->with('error', 'Pajak sudah dipakai pada dokumen. Non-aktifkan saja, jangan dihapus.');
        }

        $tax->delete();

        return redirect()->route('admin.taxes.index')
            ->with('success', 'Pajak berhasil dihapus.');
    }
}
