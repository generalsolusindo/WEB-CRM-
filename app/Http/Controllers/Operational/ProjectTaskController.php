<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\SaveProjectTaskRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectTaskController extends Controller
{
    public function store(SaveProjectTaskRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageTasks', $project);

        $project->tasks()->create([
            ...$request->validated(),
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Task ditambahkan.');
    }

    public function update(SaveProjectTaskRequest $request, Project $project, ProjectTask $task): RedirectResponse
    {
        Gate::authorize('manageTasks', $project);
        $this->ensureBelongs($project, $task);

        $task->update($request->validated());

        return back()->with('success', 'Task diperbarui.');
    }

    public function destroy(Project $project, ProjectTask $task): RedirectResponse
    {
        Gate::authorize('manageTasks', $project);
        $this->ensureBelongs($project, $task);
        $task->delete();

        return back()->with('success', 'Task dihapus.');
    }

    private function ensureBelongs(Project $project, ProjectTask $task): void
    {
        abort_unless($task->project_id === $project->id, 404);
    }
}
