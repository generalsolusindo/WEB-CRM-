<?php

namespace App\Http\Controllers\Management;

use App\Actions\Sales\ReviewQuotation;
use App\Http\Controllers\Concerns\BuildsQuotationReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ReviewQuotationRequest;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    use BuildsQuotationReview;

    public function index(): Response
    {
        Gate::authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->where('status', 'draft')
            ->where('pm_review_status', 'approved')
            ->where('manager_review_status', null)
            ->with(['contact:id,name,company_name', 'sales:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $quotations->through(fn (Quotation $quotation) => $this->quotationRow($quotation));

        return Inertia::render('Quotations/Review/Index', [
            'quotations' => $quotations,
            'role' => 'management',
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
