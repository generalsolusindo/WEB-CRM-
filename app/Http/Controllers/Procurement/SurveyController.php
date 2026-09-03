<?php

namespace App\Http\Controllers\Procurement;

use App\Actions\Procurement\SourceSurvey;
use App\Enums\SurveyDeliveryMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\SourceSurveyRequest;
use App\Models\Survey;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Survey::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SurveyStatus::class)],
        ]);

        $surveys = Survey::query()
            ->with(['lead.contact:id,name,company_name', 'surveyors:id,name', 'vendor:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'requested') DESC")
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Survey $survey) => [
                'id' => $survey->id,
                'code' => $survey->code,
                'customer' => $survey->lead->contact?->name,
                'site_region' => $survey->site_region,
                'delivery_mode' => SurveyDeliveryMode::from($survey->delivery_mode)->label(),
                'billable' => $survey->billable,
                'status' => $survey->status,
                'status_label' => SurveyStatus::from($survey->status)->label(),
                'vendor' => $survey->vendor?->name,
                'team_count' => $survey->surveyors->count(),
            ]);

        return Inertia::render('Procurement/Surveys/Index', [
            'surveys' => $surveys,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statusOptions' => SurveyStatus::options(),
        ]);
    }

    public function show(Survey $survey): Response
    {
        Gate::authorize('view', $survey);

        $survey->load([
            'lead.contact:id,name,company_name,email,phone,address',
            'requestedBy:id,name',
            'surveyors:id,name',
            'vendor:id,name',
            'sourcedBy:id,name',
        ]);

        return Inertia::render('Procurement/Surveys/Show', [
            'survey' => [
                ...$survey->toArray(),
                'status_label' => SurveyStatus::from($survey->status)->label(),
                'delivery_mode_label' => SurveyDeliveryMode::from($survey->delivery_mode)->label(),
                'needs_finance' => $survey->needsFinance(),
                'team' => $survey->surveyors->map(fn ($u) => [
                    'name' => $u->name,
                    'is_leader' => (bool) $u->pivot->is_leader,
                ]),
            ],
            'canSource' => request()->user()->can('source', $survey),
            'vendors' => Vendor::query()
                ->where('provides_survey', true)
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'coverage_area']),
        ]);
    }

    public function source(SourceSurveyRequest $request, Survey $survey, SourceSurvey $action): RedirectResponse
    {
        $action->handle($survey, $request->user(), $request->validated());

        return redirect()->route('procurement.surveys.show', $survey)
            ->with('success', 'Vendor & biaya ditetapkan. Tahap berikutnya sudah dinotifikasi.');
    }
}
