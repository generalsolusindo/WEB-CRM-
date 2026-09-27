<?php

namespace App\Http\Controllers\Management;

use App\Actions\Management\DeleteLeadCompletely;
use App\Enums\LeadStage;
use App\Http\Controllers\Concerns\BuildsOpportunityOverview;
use App\Http\Controllers\Concerns\NormalizesDateRangeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\DelegateOpportunityRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    use BuildsOpportunityOverview;
    use NormalizesDateRangeFilter;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $this->normalizeDateRange($request->validate([
            'stage' => ['nullable', Rule::enum(LeadStage::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $leads = Lead::query()
            ->where('type', 'opportunity')
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->with(['contact:id,name,company_name', 'sales:id,name', 'delegatedTo:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $leads->through(fn (Lead $lead) => $this->opportunityRow($lead));

        return Inertia::render('Opportunities/Overview/Index', [
            'opportunities' => $leads,
            'filters' => [
                'stage' => $filters['stage'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'stageOptions' => LeadStage::options(),
            'role' => 'management',
        ]);
    }

    public function show(Lead $lead): Response
    {
        Gate::authorize('view', $lead);
        abort_unless($lead->type === 'opportunity', 404);

        return Inertia::render('Opportunities/Overview/Show', [
            'opportunity' => $this->opportunityDetail($lead),
            'canDelegate' => request()->user()->can('delegate', $lead),
            'canForceDelete' => request()->user()->can('forceDelete', Lead::class),
            'projectManagerOptions' => User::query()
                ->where('role', 'project_manager')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function delegate(DelegateOpportunityRequest $request, Lead $lead): RedirectResponse
    {
        $pmId = $request->validated('project_manager_id');

        $lead->update([
            'delegated_to' => $pmId,
            'delegated_by' => $pmId ? $request->user()->id : null,
            'delegated_at' => $pmId ? now() : null,
        ]);

        return back()->with('success', $pmId ? 'Opportunity didelegasikan.' : 'Delegasi opportunity ditarik kembali.');
    }

    /**
     * Hapus total (permanen, tanpa batasan) — pembersihan data dummy/coba-coba.
     * Beda dari Sales\LeadController::destroy() yang dikunci begitu ada transaksi nyata.
     */
    public function destroy(Lead $lead, DeleteLeadCompletely $action): RedirectResponse
    {
        Gate::authorize('forceDelete', Lead::class);

        $action->handle($lead);

        return redirect()->route('management.opportunities.index')
            ->with('success', 'Data berhasil dihapus total beserta seluruh riwayatnya.');
    }
}
