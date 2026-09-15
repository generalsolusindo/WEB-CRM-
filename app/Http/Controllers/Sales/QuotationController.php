<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreateQuotation;
use App\Actions\Sales\UpdateQuotation;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveQuotationRequest;
use App\Http\Requests\Sales\UpdateQuotationNumberRequest;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Tax;
use App\Services\Whatsapp\WhatsappGateway;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Quotation::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        $quotations = Quotation::query()
            ->where('sales_id', $request->user()->id)
            ->with(['contact:id,name,company_name', 'lead:id,type,stage'])
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

        return Inertia::render('Sales/Quotations/Index', [
            'quotations' => $quotations,
            'filters' => ['search' => $search, 'status' => $filters['status'] ?? ''],
            'statusOptions' => QuotationStatus::options(),
        ]);
    }

    public function create(ProcurementRequest $procurementRequest): Response
    {
        Gate::authorize('create', [Quotation::class, $procurementRequest]);

        if ($procurementRequest->quotations()->exists()) {
            abort(409, 'Quotation untuk Procurement Request ini sudah tersedia.');
        }

        $procurementRequest->load([
            'lead.contact:id,name,company_name,email,phone,address,npwp',
            'lines:id,procurement_request_id,vendor_product_id,item_name,category,description,sourcing_note,qty,unit,cost_price,tax_id,availability_status',
            'lines.tax:id,name,rate',
            'lines.vendorProduct:id,category',
        ]);

        return Inertia::render('Sales/Quotations/Form', [
            'procurementRequest' => $procurementRequest,
            'taxes' => $this->activeTaxes(),
            'defaultTerms' => \App\Support\QuotationDefaults::terms(),
            'unitOptions' => \App\Models\Requirement::UNITS,
        ]);
    }

    public function store(
        SaveQuotationRequest $request,
        ProcurementRequest $procurementRequest,
        CreateQuotation $action,
    ): RedirectResponse {
        Gate::authorize('create', [Quotation::class, $procurementRequest]);
        $quotation = $action->handle($procurementRequest, $request->user(), $request->validated());

        return redirect()->route('sales.quotations.show', $quotation)
            ->with('success', 'Quotation draft berhasil dibuat.');
    }

    public function show(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        $quotation->load([
            'contact:id,name,company_name,email,phone,address,npwp',
            'lead:id,type,stage,delegated_to',
            'lead.delegatedTo:id,name',
            'sales:id,name',
            'procurementRequest:id,status',
            'lines.tax:id,name,rate',
            'parent:id,revision_number,status',
            'pmReviewedBy:id,name',
            'managerReviewedBy:id,name',
        ]);

        $history = Quotation::query()
            ->where('procurement_request_id', $quotation->procurement_request_id)
            ->where('sales_id', $quotation->sales_id)
            ->orderBy('revision_number')
            ->get(['id', 'revision_number', 'status', 'created_at']);

        return Inertia::render('Sales/Quotations/Show', [
            'quotation' => $quotation,
            'history' => $history,
            'totals' => \App\Services\Sales\DocumentTotals::of($quotation->lines),
            'customerHasWhatsapp' => $quotation->contact?->whatsappNumber() !== null,
            'permissions' => [
                'update' => request()->user()->can('update', $quotation),
                'delete' => request()->user()->can('delete', $quotation),
                'send' => request()->user()->can('send', $quotation),
                'sendWhatsapp' => request()->user()->can('sendWhatsapp', $quotation),
                'updateNumber' => request()->user()->can('updateNumber', $quotation),
                'revise' => request()->user()->can('revise', $quotation),
                'reject' => request()->user()->can('reject', $quotation),
                'confirm' => request()->user()->can('confirm', $quotation),
            ],
        ]);
    }

    public function edit(Quotation $quotation): Response
    {
        Gate::authorize('update', $quotation);
        $quotation->load([
            'contact:id,name,company_name,email,phone,address,npwp',
            'lines.tax:id,name,rate',
        ]);

        return Inertia::render('Sales/Quotations/Form', [
            'quotation' => $quotation,
            'taxes' => $this->activeTaxes(),
            'unitOptions' => \App\Models\Requirement::UNITS,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Tax> */
    private function activeTaxes()
    {
        return Tax::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate']);
    }

    public function print(Quotation $quotation): \Illuminate\View\View
    {
        Gate::authorize('view', $quotation);

        return view('sales.quotations.print', $this->printData($quotation));
    }

    public function pdf(Quotation $quotation): \Illuminate\Http\Response
    {
        Gate::authorize('view', $quotation);

        return Pdf::loadView('sales.quotations.print', $this->printData($quotation, forPdf: true))
            ->stream($this->pdfFilename($quotation));
    }

    /** Unduhan publik lewat tautan bertanda tangan (dipakai di pesan WhatsApp ke customer). */
    public function downloadPdf(Quotation $quotation): \Illuminate\Http\Response
    {
        return Pdf::loadView('sales.quotations.print', $this->printData($quotation, forPdf: true))
            ->stream($this->pdfFilename($quotation));
    }

    public function sendWhatsapp(Quotation $quotation, WhatsappGateway $whatsapp): RedirectResponse
    {
        Gate::authorize('sendWhatsapp', $quotation);

        $quotation->load('contact');
        $number = $quotation->contact?->whatsappNumber();

        if (! $number) {
            return back()->with('error', 'Nomor WhatsApp customer belum ada / tidak valid. Lengkapi di data Contact dulu.');
        }

        DB::transaction(function () use ($quotation) {
            if ($quotation->status === QuotationStatus::Draft->value) {
                $quotation->status = QuotationStatus::Sent->value;
            }
            $quotation->whatsapp_sent_at = now();
            $quotation->whatsapp_sent_by = request()->user()->id;
            $quotation->save();
        });

        $pdfUrl = URL::temporarySignedRoute(
            'quotations.pdf.public',
            now()->addDays(7),
            ['quotation' => $quotation->id],
        );

        $quotationNumber = $quotation->number ?? "QT-{$quotation->id}";
        $message = "Yth. {$quotation->contact->name},\n\n"
            ."Terlampir penawaran (quotation) *{$quotationNumber}* dari CV. General Solusindo.\n\n"
            ."Unduh quotation (PDF):\n{$pdfUrl}\n\n"
            .'Terima kasih.';

        return back()->with('whatsappUrl', $whatsapp->link($number, $message));
    }

    private function pdfFilename(Quotation $quotation): string
    {
        return str_replace('/', '-', $quotation->number ?? "QT-{$quotation->id}").'.pdf';
    }

    /** @return array<string, mixed> */
    private function printData(Quotation $quotation, bool $forPdf = false): array
    {
        $quotation->load([
            'contact:id,name,company_name,email,phone,address,npwp',
            'lines.tax:id,name,rate',
            'sales:id,name',
        ]);

        return [
            'quotation' => $quotation,
            'totals' => \App\Services\Sales\DocumentTotals::of($quotation->lines),
            'forPdf' => $forPdf,
        ];
    }

    public function update(
        SaveQuotationRequest $request,
        Quotation $quotation,
        UpdateQuotation $action,
    ): RedirectResponse {
        Gate::authorize('update', $quotation);
        $action->handle($quotation, $request->validated());

        return redirect()->route('sales.quotations.show', $quotation)
            ->with('success', 'Quotation berhasil diperbarui.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('delete', $quotation);

        DB::transaction(function () use ($quotation) {
            // Bersihkan notifikasi PM/Manager/Sales yang menunjuk ke quotation ini
            // (mis. "perlu diverifikasi") supaya tidak ada link mati di bell notifikasi.
            Notification::where('related_type', $quotation->getMorphClass())
                ->where('related_id', $quotation->id)
                ->delete();

            $quotation->delete();
        });

        return redirect()->route('sales.quotations.index')
            ->with('success', 'Quotation berhasil dihapus.');
    }

    /** Ubah nomor quotation secara manual, mis. menyambung dari sistem lama. */
    public function updateNumber(UpdateQuotationNumberRequest $request, Quotation $quotation): RedirectResponse
    {
        $quotation->update(['number' => $request->validated('number')]);

        return back()->with('success', 'Nomor quotation berhasil diperbarui.');
    }

    public function send(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('send', $quotation);

        DB::transaction(function () use ($quotation) {
            $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === QuotationStatus::Draft->value, 409);
            $locked->update(['status' => QuotationStatus::Sent->value]);
        });

        return back()->with('success', 'Quotation ditandai sudah dikirim ke customer.');
    }

    public function reject(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('reject', $quotation);
        $quotation->update(['status' => QuotationStatus::Rejected->value]);

        return back()->with('success', 'Quotation ditandai rejected dan dapat direvisi.');
    }
}
