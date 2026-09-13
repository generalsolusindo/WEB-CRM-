<?php

namespace App\Http\Controllers\ProjectManager;

use App\Enums\LeadStage;
use App\Http\Controllers\Concerns\BuildsOpportunityOverview;
use App\Http\Controllers\Controller;
use App\Models\Lead;
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
            ->where('delegated_to', $request->user()->id)
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
            'role' => 'project_manager',
        ]);
    }

    public function show(Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        return Inertia::render('Opportunities/Overview/Show', [
            'opportunity' => $this->opportunityDetail($lead),
            'canDelegate' => false,
            'projectManagerOptions' => [],
        ]);
    }
}
