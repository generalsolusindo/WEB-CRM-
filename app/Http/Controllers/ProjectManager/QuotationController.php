<?php

namespace App\Http\Controllers\ProjectManager;

use App\Actions\Sales\ReviewQuotation;
use App\Http\Controllers\Concerns\BuildsQuotationReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectManager\ReviewQuotationRequest;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    use BuildsQuotationReview;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->whereHas('lead', fn ($query) => $query->where('delegated_to', $request->user()->id))
            ->where('status', 'draft')
            ->with(['contact:id,name,company_name', 'sales:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $quotations->through(fn (Quotation $quotation) => $this->quotationRow($quotation));

        return Inertia::render('Quotations/Review/Index', [
            'quotations' => $quotations,
            'role' => 'project_manager',
        ]);
    }

    public function show(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        return Inertia::render('Quotations/Review/Show', [
            'quotation' => $this->quotationDetail($quotation),
            'canReview' => request()->user()->can('reviewAsPm', $quotation),
            'role' => 'project_manager',
        ]);
    }

    public function review(ReviewQuotationRequest $request, Quotation $quotation, ReviewQuotation $action): RedirectResponse
    {
        $action->handle(
            $quotation,
            $request->user(),
            'pm',
            $request->boolean('approved'),
            $request->input('notes'),
        );

        return redirect()->route('project-manager.quotations.index')
            ->with('success', $request->boolean('approved') ? 'Quotation disetujui.' : 'Quotation ditolak, dikembalikan ke Sales.');
    }
}
