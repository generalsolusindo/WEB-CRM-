<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateFinalInvoice;
use App\Actions\Finance\CreateInvoice;
use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvoiceRequest;
use App\Http\Requests\Finance\UpdateInvoiceNumberRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Services\Sales\SalesOrderSettlement;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        $lineRates = $salesOrder->lines->pluck('tax_rate')->map(fn ($r) => (float) $r)->unique()->values();

        return Inertia::render('Finance/Invoices/Create', [
            'salesOrder' => $salesOrder,
            'allowedPhase' => ['value' => $allowedPhase->value, 'label' => $allowedPhase->label()],
            'isDp' => $allowedPhase === InvoicePhase::Dp,
            'defaultDpPercent' => 50,
            'agreedDpp' => $salesOrder->agreed_dpp !== null ? (float) $salesOrder->agreed_dpp : null,
            'currentPpnRate' => $lineRates->count() === 1 ? $lineRates->first() : null,
            'hasServiceLine' => $salesOrder->lines->contains('category', 'service'),
            'defaultPph23Rate' => 2,
            'alreadyInvoiced' => $alreadyInvoiced,
            'approvalDocs' => \App\Services\Sales\CustomerApprovalDocs::of($salesOrder),
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoice $action): RedirectResponse
    {
        $salesOrder = SalesOrder::findOrFail($request->validated('sales_order_id'));

        $dpPercent = $request->validated('dp_percent');
        $agreedDpp = $request->validated('agreed_dpp');
        $ppnRate = $request->validated('ppn_rate');
        $pph23Rate = $request->validated('pph23_rate');

        $invoice = $action->handle(
            $salesOrder,
            $request->user(),
            InvoicePhase::from($request->validated('phase')),
            $request->validated('due_date'),
            $dpPercent !== null ? (float) $dpPercent : null,
            $pph23Rate !== null ? (float) $pph23Rate : null,
            $agreedDpp !== null && $agreedDpp !== '' ? (float) $agreedDpp : null,
            $ppnRate !== null && $ppnRate !== '' ? (float) $ppnRate : null,
            (bool) $request->validated('pph23_enabled'),
            $request->validated('lines'),
            $request->validated('notes'),
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
            'salesOrder:id,number,order_type,payment_rule,contact_id,dp_percent,agreed_dpp',
            'salesOrder.contact:id,name,company_name,email,phone,address,npwp',
            'salesOrder.lines:id,sales_order_id,category,subtotal,tax_rate',
            'survey.lead.contact:id,name,phone',
            'lines.tax:id,name,rate',
            'payments' => fn ($query) => $query
                ->with('attachments:id,attachable_type,attachable_id,category,file_path,created_at')
                ->orderByDesc('paid_at'),
            'attachments' => fn ($query) => $query->where('category', 'pph23_slip'),
            'pph23RecordedBy:id,name',
            'creator:id,name',
        ]);

        $contact = $invoice->salesOrder?->contact ?? $invoice->survey?->lead?->contact;

        $pph23Slip = $invoice->attachments->firstWhere('category', 'pph23_slip');

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
            'settlement' => $this->settlementInfo($invoice),
            'customerHasWhatsapp' => $contact?->whatsappNumber() !== null,
            'pph23' => [
                'rate' => (float) $invoice->pph23_rate,
                'amount' => (float) $invoice->pph23_amount,
                'payable' => $invoice->payableAmount(),
                'settled' => $invoice->settledAmount(),
                'bukti_potong_no' => $invoice->pph23_bukti_potong_no,
                'recorded_at' => $invoice->pph23_recorded_at,
                'recorded_by' => $invoice->pph23RecordedBy?->name,
                'slip_url' => $pph23Slip
                    ? Storage::disk('local')->temporaryUrl($pph23Slip->file_path, now()->addDay())
                    : null,
                'rate_editable' => request()->user()->can('managePph23', $invoice)
                    && ! $invoice->payments()->exists()
                    && in_array($invoice->status, ['draft', 'sent'], true),
                'applies' => (bool) $invoice->pph23_enabled,
                'has_service' => $invoice->lines->contains('category', 'service'),
                'can_toggle' => request()->user()->can('managePph23', $invoice)
                    && ! $invoice->payments()->exists()
                    && in_array($invoice->status, ['draft', 'sent'], true)
                    && $invoice->lines->contains('category', 'service'),
            ],
            'permissions' => [
                'send' => request()->user()->can('send', $invoice),
                'sendWhatsapp' => request()->user()->can('sendWhatsapp', $invoice),
                'updateNumber' => request()->user()->can('updateNumber', $invoice),
                'managePph23' => request()->user()->can('managePph23', $invoice),
                'cancel' => request()->user()->can('cancel', $invoice),
                'recordPayment' => request()->user()->can('create', [Payment::class, $invoice]),
            ],
        ]);
    }

    public function print(Invoice $invoice): \Illuminate\View\View
    {
        Gate::authorize('view', $invoice);

        return view('finance.invoices.print', $this->printData($invoice));
    }

    /** Versi PDF (untuk Finance, ditampilkan inline di browser). */
    public function pdf(Invoice $invoice): \Illuminate\Http\Response
    {
        Gate::authorize('view', $invoice);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.invoices.print', $this->printData($invoice, forPdf: true))
            ->stream($this->pdfFilename($invoice));
    }

    /** Unduhan publik lewat tautan bertanda tangan (dipakai di pesan WhatsApp ke customer). */
    public function downloadPdf(Invoice $invoice): \Illuminate\Http\Response
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.invoices.print', $this->printData($invoice, forPdf: true))
            ->download($this->pdfFilename($invoice));
    }

    private function pdfFilename(Invoice $invoice): string
    {
        return str_replace('/', '-', $invoice->number ?? "INV-{$invoice->id}").'.pdf';
    }

    /** @return array<string, mixed> */
    private function printData(Invoice $invoice, bool $forPdf = false): array
    {
        $invoice->load([
            'salesOrder:id,number,contact_id,po_number,dp_percent,agreed_dpp',
            'salesOrder.contact:id,name,company_name,email,phone,address,npwp',
            'salesOrder.lines:id,sales_order_id,category,subtotal,tax_rate',
            'survey:id,lead_id,site_region',
            'survey.lead.contact:id,name,company_name,email,phone,address,npwp',
            'lines.tax:id,name,rate',
            'lines.salesOrderLine:id,unit',
            'creator:id,name',
            'payments' => fn ($q) => $q->orderBy('paid_at'),
        ]);

        $customer = $invoice->salesOrder?->contact ?? $invoice->survey?->lead?->contact;

        $dpLabel = rtrim(rtrim(number_format((float) ($invoice->salesOrder?->dp_percent ?? 50), 2), '0'), '.');
        $title = $invoice->isSurvey()
            ? 'Invoice Biaya Survey'
            : ([
                'dp' => "Invoice DP {$dpLabel}%",
                'full' => 'Invoice Pembayaran 100%',
                'final' => 'Invoice Pelunasan',
            ][$invoice->invoice_phase] ?? 'Invoice');

        $reference = $invoice->isSurvey()
            ? 'Survey: '.$invoice->survey?->code.($invoice->survey?->site_region ? ' · '.$invoice->survey->site_region : '')
            : 'Sales Order: '.$invoice->salesOrder?->number
                .($invoice->salesOrder?->po_number ? ' · PO Customer: '.$invoice->salesOrder->po_number : '');

        return [
            'invoice' => $invoice,
            'customer' => $customer,
            'docTitle' => $title,
            'reference' => $reference,
            'forPdf' => $forPdf,
            'totals' => [
                ...\App\Services\Sales\DocumentTotals::of($invoice->lines),
                'grand_total' => $invoice->grandTotal(),
                'subtotal' => (float) $invoice->amount,
                'tax' => (float) $invoice->tax_amount,
                'pph23_rate' => (float) $invoice->pph23_rate,
                'pph23_amount' => (float) $invoice->pph23_amount,
                'payable' => $invoice->payableAmount(),
            ],
            'pph23BuktiPotong' => $invoice->pph23_bukti_potong_no,
            'totalPaid' => (float) $invoice->payments->sum('amount_paid'),
            'settlement' => $this->settlementInfo($invoice),
            'grouped' => ! $invoice->isSurvey(),
            'globalDiscount' => $invoice->salesOrder?->agreed_dpp !== null,
            'preparedBy' => $invoice->creator?->name,
            'company' => [
                'name' => 'CV General Solusindo',
                'tagline' => 'IT - Consultant Integrator Supplier Training',
                'address' => 'Pondok Jati II AS - 31, Sidoarjo, 61252',
                'email' => 'informasi@generalsolusindo.com',
                'phone' => '08113219992',
                'website' => 'generalsolusindo.com',
            ],
            'bank' => [
                'holder' => 'CV GENERAL SOLUSINDO',
                'bank' => 'BANK MANDIRI CAB. SIDOARJO',
                'account' => '141-00-1353843-4',
            ],
            'terms' => $invoice->isSurvey()
                ? [
                    'Harga sudah termasuk pajak',
                    'Dibayar penuh saat invoice diterima',
                    'Biaya survey tidak dapat dikembalikan',
                ]
                : [
                    'Harga sudah termasuk pajak',
                    'Pembayaran DP di muka',
                    'Pelunasan 50% setelah BAST',
                    'Garansi 1 bulan',
                    'Tidak dapat dibatalkan',
                ],
        ];
    }

    /**
     * Untuk invoice DP: rincian nilai kontrak penuh + sisa pelunasan yang
     * ditagih setelah BAST. Null untuk invoice full / pelunasan / survey.
     *
     * @return array<string, float|string>|null
     */
    private function settlementInfo(Invoice $invoice): ?array
    {
        if ($invoice->invoice_phase !== InvoicePhase::Dp->value || ! $invoice->salesOrder) {
            return null;
        }

        $lines = $invoice->salesOrder->lines;
        $fullDpp = round((float) $lines->sum('subtotal'), 2);
        $fullTax = round((float) $lines->sum(fn ($l) => (float) $l->subtotal * (float) $l->tax_rate / 100), 2);
        $fullGrand = round($fullDpp + $fullTax, 2);

        $fullPph23 = 0.0;
        if ($invoice->pph23_enabled && (float) $invoice->pph23_rate > 0) {
            $serviceDpp = round((float) $lines->where('category', 'service')->sum('subtotal'), 2);
            $fullPph23 = round($serviceDpp * (float) $invoice->pph23_rate / 100);
        }

        $fullPayable = round($fullGrand - $fullPph23, 2);
        $dpPayable = $invoice->payableAmount();
        $percent = rtrim(rtrim(number_format((float) ($invoice->salesOrder->dp_percent ?? 50), 2), '0'), '.');

        return [
            'dp_percent' => $percent,
            'contract_grand' => $fullGrand,
            'contract_payable' => $fullPayable,
            'dp_payable' => $dpPayable,
            'remaining' => round($fullPayable - $dpPayable, 2),
        ];
    }

    public function sendWhatsapp(Invoice $invoice, WhatsappGateway $whatsapp): RedirectResponse
    {
        Gate::authorize('sendWhatsapp', $invoice);

        $invoice->load(['salesOrder.contact', 'survey.lead.contact']);
        $contact = $invoice->salesOrder?->contact ?? $invoice->survey?->lead?->contact;
        $number = $contact?->whatsappNumber();

        if (! $number) {
            return back()->with('error', 'Nomor WhatsApp customer belum ada / tidak valid. Lengkapi di data Contact dulu.');
        }

        DB::transaction(function () use ($invoice) {
            if ($invoice->status === InvoiceStatus::Draft->value) {
                $invoice->status = InvoiceStatus::Sent->value;
            }
            $invoice->whatsapp_sent_at = now();
            $invoice->whatsapp_sent_by = request()->user()->id;
            $invoice->save();
        });

        $pdfUrl = URL::temporarySignedRoute('invoices.pdf.public', now()->addDays(7), ['invoice' => $invoice->id]);

        $grand = number_format($invoice->grandTotal(), 0, ',', '.');
        $due = $invoice->due_date ? Carbon::parse($invoice->due_date)->translatedFormat('d F Y') : '-';
        $message = "Yth. {$contact->name},\n\n"
            ."Terlampir invoice *{$invoice->number}* dari PT General Solusindo.\n"
            ."Total tagihan: Rp {$grand}\n"
            ."Jatuh tempo: {$due}\n\n"
            ."Unduh invoice (PDF):\n{$pdfUrl}\n\n"
            .'Terima kasih.';

        return back()->with('whatsappUrl', $whatsapp->link($number, $message));
    }

    public function updatePph23(Request $request, Invoice $invoice, \App\Services\Sales\SalesOrderWinNotifier $winNotifier): RedirectResponse
    {
        Gate::authorize('managePph23', $invoice);

        $data = $request->validate([
            'rate' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
            'enabled' => ['sometimes', 'boolean'],
            'bukti_potong_no' => ['nullable', 'string', 'max:100'],
            'slip' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:1024'],
        ]);

        DB::transaction(function () use ($request, $invoice, $data, $winNotifier) {
            $rateEditable = ! $invoice->payments()->exists()
                && in_array($invoice->status, [InvoiceStatus::Draft->value, InvoiceStatus::Sent->value], true);

            if ($rateEditable && array_key_exists('enabled', $data)) {
                $invoice->pph23_enabled = (bool) $data['enabled'];
            }

            if ($rateEditable && (($data['rate'] ?? null) !== null || array_key_exists('enabled', $data))) {
                $invoice->loadMissing('lines');
                $serviceDpp = round((float) $invoice->lines->where('category', 'service')->sum('subtotal'), 2);
                $rate = max(0.0, min(10.0, (float) ($data['rate'] ?? $invoice->pph23_rate ?: 2.0)));
                $on = $invoice->pph23_enabled && $serviceDpp > 0;
                $invoice->pph23_rate = $on ? $rate : 0;
                $invoice->pph23_amount = $on ? round($serviceDpp * $rate / 100) : 0;
            }

            if (! empty($data['bukti_potong_no'])) {
                $invoice->pph23_bukti_potong_no = $data['bukti_potong_no'];
                $invoice->pph23_recorded_at = now();
                $invoice->pph23_recorded_by = $request->user()->id;
            }

            if ($request->hasFile('slip')) {
                $invoice->attachments()->where('category', 'pph23_slip')->get()->each(function ($old) {
                    Storage::disk('local')->delete($old->file_path);
                    $old->delete();
                });
                $invoice->attachments()->create([
                    'category' => 'pph23_slip',
                    'file_path' => $request->file('slip')->store('pph23-slips'),
                    'uploaded_by' => $request->user()->id,
                ]);
                $invoice->pph23_recorded_at ??= now();
                $invoice->pph23_recorded_by ??= $request->user()->id;
            }

            $invoice->save();

            $cash = (float) $invoice->payments()->sum('amount_paid');
            $settled = round($cash + (float) $invoice->pph23_amount, 2);
            $newStatus = match (true) {
                $invoice->status === InvoiceStatus::Cancelled->value => $invoice->status,
                $settled >= $invoice->grandTotal() => InvoiceStatus::Paid->value,
                $cash > 0 => InvoiceStatus::PartiallyPaid->value,
                default => $invoice->status,
            };
            if ($newStatus !== $invoice->status) {
                $invoice->update(['status' => $newStatus]);
                $winNotifier->evaluate($invoice->salesOrder);
            }
        });

        return back()->with('success', 'Data PPh 23 diperbarui.');
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

    /** Ubah nomor invoice secara manual, mis. menyambung dari sistem lama. */
    public function updateNumber(UpdateInvoiceNumberRequest $request, Invoice $invoice): RedirectResponse
    {
        $invoice->update(['number' => $request->validated('number')]);

        return back()->with('success', 'Nomor invoice berhasil diperbarui.');
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
