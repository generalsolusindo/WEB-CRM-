<?php

namespace App\Http\Controllers\Operational;

use App\Enums\ActualProcurementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\AddExtraProcurementRequest;
use App\Models\ActualProcurement;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ActualProcurementController extends Controller
{
    public function store(AddExtraProcurementRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageResources', $project);

        $project->actualProcurements()->create([
            ...$request->validated(),
            'requested_by' => $request->user()->id,
            'status' => ActualProcurementStatus::Pending->value,
        ]);

        return back()->with('success', 'Item pengadaan ekstra ditambahkan. Procurement akan memprosesnya.');
    }

    public function destroy(Project $project, ActualProcurement $actualProcurement): RedirectResponse
    {
        Gate::authorize('manageResources', $project);
        abort_unless($actualProcurement->project_id === $project->id, 404);

        if ($actualProcurement->status !== ActualProcurementStatus::Pending->value) {
            return back()->with('error', 'Item yang sudah diproses Procurement tidak bisa dihapus.');
        }

        $actualProcurement->delete();

        return back()->with('success', 'Item pengadaan dihapus.');
    }
}
