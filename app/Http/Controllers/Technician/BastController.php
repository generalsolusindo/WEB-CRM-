<?php

namespace App\Http\Controllers\Technician;

use App\Actions\Technician\SubmitBast;
use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\SubmitBastRequest;
use App\Models\Bast;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BastController extends Controller
{
    public function create(Project $project): Response
    {
        Gate::authorize('create', [Bast::class, $project]);

        $project->load(['salesOrder:id,number', 'tasks:id,project_id,title,status']);

        return Inertia::render('Technician/Bast/Submit', [
            'project' => [
                'id' => $project->id,
                'number' => 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'sales_order' => $project->salesOrder->number,
                'tasks' => $project->tasks,
            ],
        ]);
    }

    public function store(SubmitBastRequest $request, Project $project, SubmitBast $action): RedirectResponse
    {
        Gate::authorize('create', [Bast::class, $project]);

        $action->handle(
            $project,
            $request->user(),
            $request->validated('notes'),
            $request->file('documents', []),
        );

        return redirect()->route('technician.tasks.index')
            ->with('success', 'BAST dikirim. Menunggu verifikasi Operational.');
    }
}
