<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\BriefSurvey;
use App\Actions\Operational\VerifySurveyReport;
use App\Actions\Survey\CancelSurvey;
use App\Enums\SurveyDeliveryMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\BriefSurveyRequest;
use App\Http\Requests\Operational\VerifySurveyReportRequest;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAnyOperational', Survey::class);

        $surveys = Survey::query()
            ->whereIn('status', [
                SurveyStatus::AwaitingBriefing->value,
                SurveyStatus::InProgress->value,
                SurveyStatus::ReportReview->value,
            ])
            ->with(['lead.contact:id,name,company_name', 'surveyors:id,name', 'report:id,survey_id,status,revision'])
            ->orderByRaw("FIELD(status, 'report_review', 'awaiting_briefing', 'in_progress')")
            ->latest()
            ->paginate(12)
            ->through(fn (Survey $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'customer' => $s->lead->contact?->name,
                'site_region' => $s->site_region,
                'surveyor' => $s->leaderUser()?->name ?? ($s->surveyors->pluck('name')->join(', ') ?: null),
                'status' => $s->status,
                'status_label' => SurveyStatus::from($s->status)->label(),
                'revision' => $s->report?->revision,
            ]);

        return Inertia::render('Operational/Surveys/Index', ['surveys' => $surveys]);
    }

    public function show(Survey $survey): Response
    {
        Gate::authorize('viewOperational', $survey);

        $survey->load([
            'lead.contact:id,name,company_name,email,phone,address',
            'requestedBy:id,name',
            'surveyors:id,name,phone',
            'vendor:id,name',
            'report.items' => fn ($q) => $q->orderBy('id'),
            'report.submittedBy:id,name',
            'report.attachments:id,attachable_type,attachable_id,category,file_path,created_at',
        ]);

        $report = $survey->report;

        return Inertia::render('Operational/Surveys/Show', [
            'survey' => [
                'id' => $survey->id,
                'code' => $survey->code,
                'customer' => $survey->lead->contact?->name,
                'company' => $survey->lead->contact?->company_name,
                'site_region' => $survey->site_region,
                'site_address' => $survey->site_address,
                'delivery_mode' => SurveyDeliveryMode::from($survey->delivery_mode)->label(),
                'surveyor' => $survey->leaderUser()?->name ?? ($survey->surveyors->pluck('name')->join(', ') ?: null),
                'team' => $survey->surveyors->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'is_leader' => (bool) $u->pivot->is_leader,
                ]),
                'delivery_mode_raw' => $survey->delivery_mode,
                'vendor_id' => $survey->vendor_id,
                'vendor' => $survey->vendor?->name,
                'status' => $survey->status,
                'status_label' => SurveyStatus::from($survey->status)->label(),
                'briefing' => $survey->briefing,
                'notes' => $survey->notes,
            ],
            'report' => $report ? [
                'status' => $report->status,
                'revision' => $report->revision,
                'summary' => $report->summary,
                'review_notes' => $report->review_notes,
                'submitted_by' => $report->submittedBy?->name,
                'submitted_at' => $report->submitted_at,
                'items' => $report->items->map(fn ($i) => [
                    'id' => $i->id, 'item_name' => $i->item_name, 'qty' => $i->qty, 'unit' => $i->unit, 'notes' => $i->notes,
                ]),
                'attachments' => $report->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
                    'name' => basename($a->file_path),
                ]),
            ] : null,
            'canBrief' => request()->user()->can('brief', $survey),
            'canVerify' => request()->user()->can('verifyReport', $survey),
            'canCancel' => request()->user()->can('cancel', $survey),
            'canManageTeam' => request()->user()->can('updateTeam', $survey),
            'surveyorOptions' => \App\Models\User::query()
                ->where('role', 'technician')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'vendor_id'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'phone' => $u->phone,
                    'vendor_id' => $u->vendor_id,
                ]),
        ]);
    }

    public function brief(BriefSurveyRequest $request, Survey $survey, BriefSurvey $action): RedirectResponse
    {
        $action->handle(
            $survey,
            $request->user(),
            $request->validated('briefing'),
            array_map('intval', $request->validated('surveyor_ids')),
            (int) $request->validated('leader_id'),
        );

        return redirect()->route('operational.surveys.show', $survey)
            ->with('success', 'Tim surveyor ditugaskan & arahan dikirim.');
    }

    public function updateTeam(\App\Http\Requests\Operational\UpdateSurveyTeamRequest $request, Survey $survey, \App\Actions\Operational\SyncSurveyTeam $action): RedirectResponse
    {
        $action->handle(
            $survey,
            array_map('intval', $request->validated('surveyor_ids')),
            (int) $request->validated('leader_id'),
        );

        return redirect()->route('operational.surveys.show', $survey)
            ->with('success', 'Komposisi tim surveyor diperbarui.');
    }

    public function verify(VerifySurveyReportRequest $request, Survey $survey, VerifySurveyReport $action): RedirectResponse
    {
        $action->handle(
            $survey,
            $request->user(),
            $request->validated('decision') === 'approve',
            $request->validated('notes'),
        );

        return redirect()->route('operational.surveys.show', $survey)
            ->with('success', 'Verifikasi laporan tersimpan.');
    }

    public function cancel(\Illuminate\Http\Request $request, Survey $survey, CancelSurvey $action): RedirectResponse
    {
        Gate::authorize('cancel', $survey);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action->handle($survey, $request->user(), $data['reason'] ?? null);

        return redirect()->route('operational.surveys.index')->with('success', 'Survey dibatalkan.');
    }
}
