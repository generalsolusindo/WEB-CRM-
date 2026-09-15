<?php

namespace App\Http\Controllers\Management;

use App\Actions\Sales\ReviewQuotation;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Concerns\BuildsQuotationReview;
use App\Http\Controllers\Concerns\NormalizesDateRangeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ReviewQuotationRequest;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    use BuildsQuotationReview;
    use NormalizesDateRangeFilter;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Quotation::class);

        $filters = $this->normalizeDateRange($request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $quotations = Quotation::query()
            ->where('status', 'draft')
            ->where('pm_review_status', 'approved')
            ->where('manager_review_status', null)
            ->with(['contact:id,name,company_name', 'sales:id,name'])
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $quotations->through(fn (Quotation $quotation) => $this->quotationRow($quotation));

        return Inertia::render('Quotations/Review/Index', [
            'quotations' => $quotations,
            'role' => 'management',
            'filters' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
        ]);
    }

    /**
     * Monitoring read-only SEMUA quotation (bukan cuma yang menunggu approval
     * Manager) — dipakai untuk tracking, bukan aksi. Beda dari index() di atas
     * yang khusus jadi inbox "Verifikasi Quotation".
     */
    public function all(Request $request): Response
    {
        Gate::authorize('viewAny', Quotation::class);

        $filters = $this->normalizeDateRange($request->validate([
            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $quotations = Quotation::query()
            ->with(['contact:id,name,company_name', 'sales:id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $quotations->through(fn (Quotation $quotation) => $this->quotationRow($quotation));

        return Inertia::render('Management/Quotations/Index', [
            'quotations' => $quotations,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'statusOptions' => QuotationStatus::options(),
        ]);
    }

    public function show(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        return Inertia::render('Quotations/Review/Show', [
            'quotation' => $this->quotationDetail($quotation),
            'canReview' => request()->user()->can('reviewAsManager', $quotation),
            'role' => 'management',
        ]);
    }

    public function review(ReviewQuotationRequest $request, Quotation $quotation, ReviewQuotation $action): RedirectResponse
    {
        $action->handle(
            $quotation,
            $request->user(),
            'manager',
            $request->boolean('approved'),
            $request->input('notes'),
        );

        return redirect()->route('management.quotations.index')
            ->with('success', $request->boolean('approved') ? 'Quotation disetujui — siap dikirim Sales ke customer.' : 'Quotation ditolak, dikembalikan ke Sales.');
    }
}
