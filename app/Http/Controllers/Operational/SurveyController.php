<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\BriefSurvey;
use App\Actions\Operational\SyncSurveyTeam;
use App\Actions\Operational\VerifySurveyReport;
use App\Actions\Survey\CancelSurvey;
use App\Enums\SurveyDeliveryMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\BriefSurveyRequest;
use App\Http\Requests\Operational\UpdateSurveyTeamRequest;
use App\Http\Requests\Operational\UploadSurveyResultDocumentRequest;
use App\Http\Requests\Operational\VerifySurveyReportRequest;
use App\Models\Attachment;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'attachments' => fn ($q) => $q->whereIn('category', ['checkin_selfie', 'survey_result_document'])->latest(),
            'attachments.uploader:id,name',
        ]);

        $report = $survey->report;
        $resultDocuments = $survey->attachments->where('category', 'survey_result_document')->values()->map(fn ($a) => [
            'id' => $a->id,
            'name' => basename($a->file_path),
            'is_image' => in_array(strtolower(pathinfo($a->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true),
            'uploader' => $a->uploader?->name,
            'at' => $a->created_at,
            'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
        ]);
        $checkIns = $survey->attachments->where('category', 'checkin_selfie')->values()->map(fn ($a) => [
            'id' => $a->id,
            'surveyor' => $a->uploader?->name,
            'at' => $a->created_at,
            'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
        ]);

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
            'checkIns' => $checkIns,
            'resultDocuments' => $resultDocuments,
            'canManageResultDocuments' => request()->user()->can('manageResultDocuments', $survey),
            'canBrief' => request()->user()->can('brief', $survey),
            'canVerify' => request()->user()->can('verifyReport', $survey),
            'canCancel' => request()->user()->can('cancel', $survey),
            'canManageTeam' => request()->user()->can('updateTeam', $survey),
            'surveyorOptions' => User::query()
                ->where(fn ($q) => $q->where('role', 'technician')->orWhere('can_surveyor', true))
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

    public function uploadResultDocument(UploadSurveyResultDocumentRequest $request, Survey $survey): RedirectResponse
    {
        $survey->attachments()->create([
            'category' => 'survey_result_document',
            'file_path' => $request->file('file')->store('survey-results'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Dokumen hasil survey tersimpan.');
    }

    public function deleteResultDocument(Survey $survey, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('manageResultDocuments', $survey);

        abort_unless(
            $attachment->category === 'survey_result_document'
            && $attachment->attachable_type === $survey->getMorphClass()
            && $attachment->attachable_id === $survey->id,
            404,
        );

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Dokumen hasil survey dihapus.');
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

    public function updateTeam(UpdateSurveyTeamRequest $request, Survey $survey, SyncSurveyTeam $action): RedirectResponse
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

    public function cancel(Request $request, Survey $survey, CancelSurvey $action): RedirectResponse
    {
        Gate::authorize('cancel', $survey);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action->handle($survey, $request->user(), $data['reason'] ?? null);

        return redirect()->route('operational.surveys.index')->with('success', 'Survey dibatalkan.');
    }
}
