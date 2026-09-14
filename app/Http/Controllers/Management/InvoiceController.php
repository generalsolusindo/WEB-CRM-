<?php

namespace App\Http\Controllers\Management;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Monitoring read-only untuk Management — tidak ada aksi/detail, cuma daftar lengkap. */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
        ]);

        $invoices = Invoice::query()
            ->where('invoice_type', 'sale')
            ->with(['salesOrder:id,number,contact_id', 'salesOrder.contact:id,name,company_name'])
            ->withSum('payments as paid_total', 'amount_paid')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $invoices->through(fn (Invoice $inv) => [
            'id' => $inv->id,
            'number' => $inv->number,
            'customer' => $inv->salesOrder?->contact?->name,
            'company' => $inv->salesOrder?->contact?->company_name,
            'phase' => $inv->invoice_phase,
            'status' => $inv->status,
            'grand_total' => $inv->grandTotal(),
            'paid_total' => (float) ($inv->paid_total ?? 0),
            'due_date' => $inv->due_date?->format('Y-m-d'),
        ]);

        return Inertia::render('Management/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statusOptions' => InvoiceStatus::options(),
        ]);
    }
}
