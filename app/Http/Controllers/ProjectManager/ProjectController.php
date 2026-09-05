<?php

namespace App\Http\Controllers\ProjectManager;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Concerns\BuildsProjectOverview;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use BuildsProjectOverview;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);

        $projects = Project::query()
            ->where('delegated_to', $request->user()->id)
            ->with(['salesOrder:id,number,contact_id', 'salesOrder.contact:id,name', 'salesOrder.lines:id,sales_order_id,category,qty', 'delegatedTo:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $projects->through(fn (Project $p) => $this->projectOverviewRow($p));

        return Inertia::render('Projects/Overview/Index', [
            'projects' => $projects,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statusOptions' => ProjectStatus::options(),
            'role' => 'project_manager',
        ]);
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('Projects/Overview/Show', [
            'project' => $this->projectOverviewDetail($project),
            'canDelegate' => false,
            'projectManagerOptions' => [],
        ]);
    }
}
