<?php

namespace App\Http\Controllers\Management;

use App\Enums\LeadStage;
use App\Http\Controllers\Concerns\BuildsOpportunityOverview;
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

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $request->validate([
            'stage' => ['nullable', Rule::enum(LeadStage::class)],
        ]);

        $leads = Lead::query()
            ->where('type', 'opportunity')
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage))
            ->with(['contact:id,name,company_name', 'sales:id,name', 'delegatedTo:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $leads->through(fn (Lead $lead) => $this->opportunityRow($lead));

        return Inertia::render('Opportunities/Overview/Index', [
            'opportunities' => $leads,
            'filters' => ['stage' => $filters['stage'] ?? ''],
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
}
