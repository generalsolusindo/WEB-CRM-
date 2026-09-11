<?php

namespace App\Http\Controllers\Technician;

use App\Actions\Technician\SubmitSurveyReport;
use App\Enums\SurveyReportStatus;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\CheckInSurveyRequest;
use App\Http\Requests\Technician\CheckOutSurveyRequest;
use App\Http\Requests\Technician\SaveSurveyReportRequest;
use App\Http\Requests\Technician\UploadSurveyAttachmentRequest;
use App\Models\Attachment;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $surveys = Survey::query()
            ->whereHas('surveyorAssignments', fn ($q) => $q->where('technician_id', $userId))
            ->whereIn('status', [
                SurveyStatus::InProgress->value,
                SurveyStatus::ReportReview->value,
                SurveyStatus::Verified->value,
            ])
            ->with('lead.contact:id,name', 'report:id,survey_id,status,revision')
            ->latest()
            ->get()
            ->map(fn (Survey $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'customer' => $s->lead->contact?->name,
                'site_region' => $s->site_region,
                'status' => $s->status,
                'status_label' => SurveyStatus::from($s->status)->label(),
                'report_status' => $s->report?->status,
                'revision' => $s->report?->revision,
                'checked_in' => $s->hasCheckedIn($request->user()),
                'checked_out' => $s->hasCheckedOut($request->user()),
            ]);

        return Inertia::render('Technician/Surveys/Index', ['surveys' => $surveys]);
    }

    public function show(Survey $survey): Response
    {
        Gate::authorize('viewAsSurveyor', $survey);

        $survey->load([
            'lead.contact:id,name,company_name,phone,address',
            'surveyors:id,name',
            'report.items' => fn ($q) => $q->orderBy('id'),
            'report.attachments:id,attachable_type,attachable_id,category,file_path,created_at',
        ]);

        $user = request()->user();
        $userId = $user->id;
        $checkedIn = $survey->hasCheckedIn($user);
        $checkedOut = $survey->hasCheckedOut($user);
        $mySelfie = $checkedIn
            ? $survey->attachments()
                ->where('category', 'checkin_selfie')
                ->where('uploaded_by', $userId)
                ->latest()
                ->first()
            : null;
        $myCheckoutSelfie = $checkedOut
            ? $survey->attachments()
                ->where('category', 'checkout_selfie')
                ->where('uploaded_by', $userId)
                ->latest()
                ->first()
            : null;

        return Inertia::render('Technician/Surveys/Show', [
            'survey' => [
                'id' => $survey->id,
                'code' => $survey->code,
                'customer' => $survey->lead->contact?->name,
                'company' => $survey->lead->contact?->company_name,
                'site_region' => $survey->site_region,
                'site_address' => $survey->site_address,
                'status' => $survey->status,
                'status_label' => SurveyStatus::from($survey->status)->label(),
                'briefing' => $survey->briefing,
                'team' => $survey->surveyors->map(fn ($u) => [
                    'name' => $u->name,
                    'is_leader' => (bool) $u->pivot->is_leader,
                ]),
                'is_leader' => (bool) $survey->surveyors->firstWhere('id', $userId)?->pivot->is_leader,
            ],
            'report' => $this->reportPayload($survey),
            'canWork' => request()->user()->can('workReport', $survey),
            'canSubmit' => request()->user()->can('submitReport', $survey),
            'checkedIn' => $checkedIn,
            'canCheckIn' => $user->can('checkIn', $survey),
            'selfieUrl' => $mySelfie ? Storage::disk('local')->temporaryUrl($mySelfie->file_path, now()->addDay()) : null,
            'checkedOut' => $checkedOut,
            'canCheckOut' => $user->can('checkOut', $survey),
            'checkoutSelfieUrl' => $myCheckoutSelfie ? Storage::disk('local')->temporaryUrl($myCheckoutSelfie->file_path, now()->addDay()) : null,
        ]);
    }

    public function checkIn(CheckInSurveyRequest $request, Survey $survey): RedirectResponse
    {
        $survey->attachments()->create([
            'category' => 'checkin_selfie',
            'file_path' => $request->file('photo')->store('checkin-selfies'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Absen kehadiran tersimpan.');
    }

    public function checkOut(CheckOutSurveyRequest $request, Survey $survey): RedirectResponse
    {
        $survey->attachments()->create([
            'category' => 'checkout_selfie',
            'file_path' => $request->file('photo')->store('checkout-selfies'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Absen pulang tersimpan.');
    }

    public function saveReport(SaveSurveyReportRequest $request, Survey $survey): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($survey, $data) {
            $report = $survey->report()->firstOrCreate(
                ['survey_id' => $survey->id],
                ['status' => SurveyReportStatus::Draft->value, 'revision' => 1],
            );

            $report->update(['summary' => $data['summary'] ?? null]);

            $report->items()->delete();
            foreach ($data['items'] ?? [] as $item) {
                $report->items()->create([
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Draft laporan tersimpan.');
    }

    public function submitReport(Survey $survey, SubmitSurveyReport $action): RedirectResponse
    {
        Gate::authorize('submitReport', $survey);
        $action->handle($survey, request()->user());

        return redirect()->route('technician.surveys.show', $survey)
            ->with('success', 'Laporan dikirim ke Operasional untuk verifikasi.');
    }

    public function uploadAttachment(UploadSurveyAttachmentRequest $request, Survey $survey): RedirectResponse
    {
        $report = $survey->report()->firstOrCreate(
            ['survey_id' => $survey->id],
            ['status' => SurveyReportStatus::Draft->value, 'revision' => 1],
        );

        $path = $request->file('file')->store('survey-reports');
        $report->attachments()->create([
            'category' => 'survey_report',
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Lampiran tersimpan.');
    }

    public function deleteAttachment(Survey $survey, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('workReport', $survey);

        abort_unless(
            $attachment->attachable_type === (new \App\Models\SurveyReport)->getMorphClass()
            && $survey->report
            && $attachment->attachable_id === $survey->report->id,
            404,
        );

        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }

    /** @return array<string, mixed>|null */
    private function reportPayload(Survey $survey): ?array
    {
        $report = $survey->report;
        if (! $report) {
            return null;
        }

        return [
            'status' => $report->status,
            'revision' => $report->revision,
            'summary' => $report->summary,
            'review_notes' => $report->review_notes,
            'items' => $report->items->map(fn ($i) => [
                'id' => $i->id,
                'item_name' => $i->item_name,
                'qty' => $i->qty,
                'unit' => $i->unit,
                'notes' => $i->notes,
            ]),
            'attachments' => $report->attachments->map(fn ($a) => [
                'id' => $a->id,
                'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
                'name' => basename($a->file_path),
            ]),
        ];
    }
}
