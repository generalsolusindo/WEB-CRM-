<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\UpsertBastDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\SaveBastDraftRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class BastDraftController extends Controller
{
    public function edit(Project $project): Response
    {
        Gate::authorize('manageBastDraft', $project);

        $project->load([
            'salesOrder:id,number,po_number,po_date,contact_id,quotation_id',
            'salesOrder.contact:id,name,company_name,address',
            'salesOrder.quotation:id,number,revision_number,quoted_at,created_at,lead_id',
            'salesOrder.quotation.lead:id,pic_name,pic_position',
            'leader.technician:id,name',
            'bastDraft',
        ]);

        $draft = $project->bastDraft;
        $lead = $project->salesOrder?->quotation?->lead;

        return Inertia::render('Operational/Projects/BastDraft', [
            'project' => [
                'id' => $project->id,
                'number' => 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'customer' => $project->salesOrder?->contact?->name,
                'company' => $project->salesOrder?->contact?->company_name,
                'po_number' => $project->salesOrder?->po_number,
                'po_date' => $project->salesOrder?->po_date,
            ],
            'draft' => $draft ? [
                'number' => $draft->number,
                'event_date' => $draft->event_date?->format('Y-m-d'),
                'job_title' => $draft->job_title,
                'work_description' => $draft->work_description,
                'pic_name' => $draft->pic_name,
                'pic_position' => $draft->pic_position,
                'pic_address' => $draft->pic_address,
                'leader_name' => $draft->leader_name,
                'leader_position' => $draft->leader_position,
            ] : [
                'number' => null,
                'event_date' => now()->format('Y-m-d'),
                'job_title' => null,
                'work_description' => null,
                'pic_name' => $lead?->pic_name,
                'pic_position' => $lead?->pic_position,
                'pic_address' => $project->salesOrder?->contact?->address,
                'leader_name' => $project->leader?->technician?->name,
                'leader_position' => null,
            ],
            'hasDraft' => (bool) $draft,
        ]);
    }

    public function update(SaveBastDraftRequest $request, Project $project, UpsertBastDraft $action): RedirectResponse
    {
        $action->handle($project, $request->user(), $request->validated());

        return back()->with('success', 'Draft BAST tersimpan.');
    }

    public function print(Project $project): View
    {
        Gate::authorize('manageBastDraft', $project);

        $project->load([
            'salesOrder:id,number,po_number,po_date,quotation_id',
            'salesOrder.quotation:id,number,revision_number,quoted_at,created_at',
            'bastDraft',
        ]);
        abort_unless($project->bastDraft, 404);

        return view('operational.bast-drafts.print', [
            'project' => $project,
            'draft' => $project->bastDraft,
            'salesOrder' => $project->salesOrder,
        ]);
    }
}
