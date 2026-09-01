<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\StoreChangeRequestRequest;
use App\Models\Project;
use App\Models\ProjectChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectChangeRequestController extends Controller
{
    public function store(StoreChangeRequestRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageChangeRequests', $project);

        // Murni pencatatan histori — tidak mengubah task / procurement / sales order.
        $project->changeRequests()->create([
            ...$request->validated(),
            'requested_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Change request dicatat.');
    }

    public function update(Request $request, Project $project, ProjectChangeRequest $changeRequest): RedirectResponse
    {
        Gate::authorize('manageChangeRequests', $project);
        abort_unless($changeRequest->project_id === $project->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        abort_unless($changeRequest->status === 'pending', 409);
        $changeRequest->update(['status' => $data['status']]);

        return back()->with('success', 'Status change request diperbarui.');
    }
}
