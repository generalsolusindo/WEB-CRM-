<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateFinalInvoice;
use App\Actions\Finance\CreateInvoice;
use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Payment;
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

class InvoiceController extends Controller
{
    public function index(Request $request, SalesOrderSettlement $settlement): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $request->validate([
            'phase' => ['nullable', Rule::enum(InvoicePhase::class)],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
        ]);

        $needsInvoice = SalesOrder::query()
            ->whereNotIn('status', ['cancelled'])
            ->whereDoesntHave('invoices', fn ($query) => $query
                ->whereIn('invoice_phase', [InvoicePhase::Dp->value, InvoicePhase::Full->value])
                ->where('status', '!=', InvoiceStatus::Cancelled->value))
            ->with(['contact:id,name,company_name'])
            ->withSum('lines as total_amount', 'subtotal')
            ->latest()
            ->get(['id', 'number', 'contact_id', 'order_type'])
            ->map(fn ($so) => [
                'id' => $so->id,
                'number' => $so->number,
                'customer' => $so->contact->name,
                'order_type' => OrderType::from($so->order_type)->label(),
                'total' => (float) $so->total_amount,
            ]);

        $invoices = Invoice::query()
            ->where('invoice_type', 'sale')
            ->with(['salesOrder:id,number,contact_id', 'salesOrder.contact:id,name'])
            ->withSum('payments as total_paid', 'amount_paid')
            ->when($filters['phase'] ?? null, fn ($q, $phase) => $q->where('invoice_phase', $phase))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $readyForFinal = SalesOrder::query()
            ->whereIn('order_type', [OrderType::ServiceOnly->value, OrderType::Mixed->value])
            ->with(['contact:id,name'])
            ->latest()
            ->get(['id', 'number', 'contact_id', 'order_type'])
            ->filter(fn ($so) => $settlement->canCreateFinalInvoice($so))
            ->map(fn ($so) => [
                'id' => $so->id,
                'number' => $so->number,
                'customer' => $so->contact->name,
            ])
            ->values();

        return Inertia::render('Finance/Invoices/Index', [
            'needsInvoice' => $needsInvoice,
            'readyForFinal' => $readyForFinal,
            'invoices' => $invoices,
            'filters' => ['phase' => $filters['phase'] ?? '', 'status' => $filters['status'] ?? ''],
            'phaseOptions' => InvoicePhase::options(),
            'statusOptions' => InvoiceStatus::options(),
        ]);
    }

    public function create(SalesOrder $salesOrder): Response
    {
        Gate::authorize('create', Invoice::class);

        $salesOrder->load(['contact:id,name,company_name,email,phone,address,npwp', 'lines.tax:id,name,rate']);

        $allowedPhase = $salesOrder->order_type === OrderType::MaterialOnly->value
            ? InvoicePhase::Full
            : InvoicePhase::Dp;

        $alreadyInvoiced = $salesOrder->invoices()
            ->whereIn('invoice_phase', [InvoicePhase::Dp->value, InvoicePhase::Full->value])
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->exists();

        return Inertia::render('Finance/Invoices/Create', [
            'salesOrder' => $salesOrder,
            'allowedPhase' => ['value' => $allowedPhase->value, 'label' => $allowedPhase->label()],
            'ratio' => $allowedPhase === InvoicePhase::Dp ? 0.5 : 1.0,
            'alreadyInvoiced' => $alreadyInvoiced,
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoice $action): RedirectResponse
    {
        $salesOrder = SalesOrder::findOrFail($request->validated('sales_order_id'));

        $invoice = $action->handle(
            $salesOrder,
            $request->user(),
            InvoicePhase::from($request->validated('phase')),
            $request->validated('due_date'),
        );

        return redirect()->route('finance.invoices.show', $invoice)
            ->with('success', 'Invoice draft berhasil dibuat.');
    }

    public function storeFinal(SalesOrder $salesOrder, CreateFinalInvoice $action): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $action->handle($salesOrder, request()->user(), null);

        return redirect()->route('finance.invoices.show', $invoice)
            ->with('success', 'Invoice pelunasan draft berhasil dibuat.');
    }

    public function show(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'salesOrder:id,number,order_type,payment_rule,contact_id',
            'salesOrder.contact:id,name,company_name,email,phone,address,npwp',
            'lines.tax:id,name,rate',
            'payments' => fn ($query) => $query
                ->with('attachments:id,attachable_type,attachable_id,category,file_path,created_at')
                ->orderByDesc('paid_at'),
            'creator:id,name',
        ]);

        $payments = $invoice->payments->map(fn ($payment) => [
            'id' => $payment->id,
            'amount_paid' => $payment->amount_paid,
            'paid_at' => $payment->paid_at,
            'notes' => $payment->notes,
            'proofs' => $payment->attachments
                ->where('category', 'payment_proof')
                ->map(fn ($a) => ['id' => $a->id, 'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay())])
                ->values(),
        ]);

        return Inertia::render('Finance/Invoices/Show', [
            'invoice' => $invoice->makeHidden('payments'),
            'payments' => $payments,
            'totals' => [
                ...\App\Services\Sales\DocumentTotals::of($invoice->lines),
                'grand_total' => $invoice->grandTotal(),
                'subtotal' => (float) $invoice->amount,
                'tax' => (float) $invoice->tax_amount,
            ],
            'totalPaid' => (float) $invoice->payments->sum('amount_paid'),
            'permissions' => [
                'send' => request()->user()->can('send', $invoice),
                'cancel' => request()->user()->can('cancel', $invoice),
                'recordPayment' => request()->user()->can('create', [Payment::class, $invoice]),
            ],
        ]);
    }

    public function print(Invoice $invoice): \Illuminate\View\View
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'salesOrder:id,number,contact_id',
            'salesOrder.contact:id,name,company_name,email,phone,address,npwp',
            'survey:id,lead_id,site_region',
            'survey.lead.contact:id,name,company_name,email,phone,address,npwp',
            'lines.tax:id,name,rate',
            'payments' => fn ($q) => $q->orderBy('paid_at'),
        ]);

        $customer = $invoice->salesOrder?->contact ?? $invoice->survey?->lead?->contact;

        $title = $invoice->isSurvey()
            ? 'Invoice Biaya Survey'
            : (['dp' => 'Invoice DP 50%', 'full' => 'Invoice Pembayaran 100%', 'final' => 'Invoice Pelunasan'][$invoice->invoice_phase] ?? 'Invoice');

        $reference = $invoice->isSurvey()
            ? 'Survey: '.$invoice->survey?->code.($invoice->survey?->site_region ? ' · '.$invoice->survey->site_region : '')
            : 'Sales Order: '.$invoice->salesOrder?->number;

        return view('finance.invoices.print', [
            'invoice' => $invoice,
            'customer' => $customer,
            'docTitle' => $title,
            'reference' => $reference,
            'totals' => [
                ...\App\Services\Sales\DocumentTotals::of($invoice->lines),
                'grand_total' => $invoice->grandTotal(),
                'subtotal' => (float) $invoice->amount,
                'tax' => (float) $invoice->tax_amount,
            ],
            'totalPaid' => (float) $invoice->payments->sum('amount_paid'),
        ]);
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('send', $invoice);

        DB::transaction(function () use ($invoice) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === InvoiceStatus::Draft->value, 409);
            $locked->update(['status' => InvoiceStatus::Sent->value]);
        });

        return back()->with('success', 'Invoice ditandai sudah dikirim ke customer.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);

        DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => InvoiceStatus::Cancelled->value]);

            // Invoice survey dibatalkan -> kembalikan survey agar Finance bisa terbitkan ulang / catat biaya.
            if ($invoice->isSurvey()) {
                $invoice->loadMissing('survey');
                $invoice->survey?->update(['status' => \App\Enums\SurveyStatus::FinanceReview->value]);
            }
        });

        return back()->with('success', 'Invoice dibatalkan.');
    }
}
