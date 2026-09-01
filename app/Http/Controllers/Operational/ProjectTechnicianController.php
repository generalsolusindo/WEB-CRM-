<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\AssignProjectTechnicians;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\AssignTechniciansRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectTechnicianController extends Controller
{
    public function update(
        AssignTechniciansRequest $request,
        Project $project,
        AssignProjectTechnicians $action,
    ): RedirectResponse {
        Gate::authorize('manageResources', $project);

        $action->handle(
            $project,
            $request->user(),
            $request->validated('technician_ids'),
            (int) $request->validated('leader_id'),
        );

        return back()->with('success', 'Tim technician diperbarui.');
    }
}
