<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CloseSalesOrderAsWon;
use App\Enums\OrderType;
use App\Enums\PaymentRule;
use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveSalesOrderDocumentsRequest;
use App\Models\SalesOrder;
use App\Services\Sales\SalesOrderSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SalesOrder::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(SalesOrderStatus::class)],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        $orders = SalesOrder::query()
            ->whereHas('quotation', fn ($query) => $query->where('sales_id', $request->user()->id))
            ->with([
                'contact:id,name,company_name',
                'quotation:id,lead_id,revision_number,number',
                'invoices' => fn ($query) => $query->latest()->select(
                    'id', 'sales_order_id', 'invoice_phase', 'status', 'amount', 'tax_amount', 'due_date',
                ),
            ])
            ->withSum('lines as total_amount', 'subtotal')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', $search)
                        ->orWhereHas('contact', fn ($contact) => $contact
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Sales/SalesOrders/Index', [
            'orders' => $orders,
            'filters' => ['search' => $search, 'status' => $filters['status'] ?? ''],
            'statusOptions' => SalesOrderStatus::options(),
        ]);
    }

    public function show(
        SalesOrder $salesOrder,
        SalesOrderSettlement $settlement,
    ): Response {
        Gate::authorize('view', $salesOrder);

        $salesOrder->load([
            'contact:id,name,company_name,email,phone,address,npwp',
            'quotation:id,lead_id,revision_number,status,number',
            'quotation.lead:id,type,stage',
            'lines.tax:id,name,rate',
            'attachments' => fn ($query) => $query
                ->whereIn('category', ['quotation_signed', 'purchase_order'])
                ->latest(),
            'invoices' => fn ($query) => $query
                ->withSum('payments as total_paid', 'amount_paid')
                ->with(['payments' => fn ($payment) => $payment
                    ->with('attachments:id,attachable_type,attachable_id,category,file_path,uploaded_by,created_at')
                    ->orderByDesc('paid_at')])
                ->latest(),
        ]);

        $salesOrder->invoices->each(
            fn ($invoice) => $invoice->payments->each(
                fn ($payment) => $payment->attachments->each(function ($attachment) {
                    $attachment->url = $attachment->category === 'payment_proof'
                        ? \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($attachment->file_path, now()->addDay())
                        : null;
                })
            )
        );

        $approvalDocs = $salesOrder->attachments->map(fn ($a) => [
            'category' => $a->category,
            'label' => $a->category === 'purchase_order' ? 'Purchase Order' : 'Quotation ditandatangani',
            'url' => \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
            'uploaded_at' => $a->created_at,
        ]);

        return Inertia::render('Sales/SalesOrders/Show', [
            'salesOrder' => $salesOrder,
            'approvalDocs' => $approvalDocs,
            'canManageDocs' => request()->user()->can('manageDocuments', $salesOrder),
            'totals' => \App\Services\Sales\DocumentTotals::of($salesOrder->lines, (float) $salesOrder->survey_credit),
            'orderTypeLabel' => OrderType::from($salesOrder->order_type)->label(),
            'paymentRuleLabel' => PaymentRule::from($salesOrder->payment_rule)->label(),
            'requiredSettlementPhase' => $settlement->requiredInvoicePhase($salesOrder),
            'canCloseAsWon' => $settlement->canCloseAsWon($salesOrder)
                && request()->user()->can('closeAsWon', $salesOrder),
        ]);
    }

    public function saveApprovalDocuments(SaveSalesOrderDocumentsRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $salesOrder, $data) {
            $salesOrder->update(['po_number' => $data['po_number'] ?? null]);

            foreach (['quotation_signed' => 'signed_quotation', 'purchase_order' => 'purchase_order'] as $category => $field) {
                if (! $request->hasFile($field)) {
                    continue;
                }

                $salesOrder->attachments()->where('category', $category)->get()->each(function ($old) {
                    Storage::disk('local')->delete($old->file_path);
                    $old->delete();
                });

                $salesOrder->attachments()->create([
                    'category' => $category,
                    'file_path' => $request->file($field)->store('sales-order-approvals'),
                    'uploaded_by' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', 'Dokumen persetujuan customer diperbarui.');
    }

    public function closeAsWon(
        SalesOrder $salesOrder,
        CloseSalesOrderAsWon $action,
    ): RedirectResponse {
        Gate::authorize('closeAsWon', $salesOrder);
        $action->handle($salesOrder);

        return back()->with('success', 'Transaksi berhasil ditutup sebagai Won.');
    }
}
