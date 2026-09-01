<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CreateQuotation;
use App\Actions\Sales\UpdateQuotation;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveQuotationRequest;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
            'lines:id,procurement_request_id,item_name,description,qty,unit,cost_price,tax_id,availability_status',
            'lines.tax:id,name,rate',
        ]);

        return Inertia::render('Sales/Quotations/Form', [
            'procurementRequest' => $procurementRequest,
            'taxes' => $this->activeTaxes(),
            'surveyCredit' => \App\Services\Sales\SurveyCredit::forLead($procurementRequest->lead_id),
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
            'lead:id,type,stage',
            'procurementRequest:id,status',
            'lines.tax:id,name,rate',
            'parent:id,revision_number,status',
        ]);

        $history = Quotation::query()
            ->where('procurement_request_id', $quotation->procurement_request_id)
            ->where('sales_id', $quotation->sales_id)
            ->orderBy('revision_number')
            ->get(['id', 'revision_number', 'status', 'created_at']);

        return Inertia::render('Sales/Quotations/Show', [
            'quotation' => $quotation,
            'history' => $history,
            'totals' => \App\Services\Sales\DocumentTotals::of($quotation->lines, (float) $quotation->survey_credit),
            'permissions' => [
                'update' => request()->user()->can('update', $quotation),
                'delete' => request()->user()->can('delete', $quotation),
                'send' => request()->user()->can('send', $quotation),
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
            'surveyCredit' => (float) $quotation->survey_credit,
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

        $quotation->load([
            'contact:id,name,company_name,email,phone,address,npwp',
            'lines.tax:id,name,rate',
            'sales:id,name',
        ]);

        return view('sales.quotations.print', [
            'quotation' => $quotation,
            'totals' => \App\Services\Sales\DocumentTotals::of($quotation->lines, (float) $quotation->survey_credit),
        ]);
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
        $quotation->delete();

        return redirect()->route('sales.quotations.index')
            ->with('success', 'Quotation draft berhasil dihapus.');
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
