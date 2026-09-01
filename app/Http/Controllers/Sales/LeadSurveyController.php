<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\FinalizeSurvey;
use App\Actions\Sales\RequestSurvey;
use App\Actions\Survey\CancelSurvey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\FinalizeSurveyRequest;
use App\Http\Requests\Sales\StoreSurveyRequest;
use App\Models\Lead;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LeadSurveyController extends Controller
{
    public function store(StoreSurveyRequest $request, Lead $lead, RequestSurvey $action): RedirectResponse
    {
        $action->handle($lead, $request->user(), $request->validated());

        return redirect()->route('sales.leads.show', $lead)
            ->with('success', 'Permintaan survey dikirim ke Procurement.');
    }

    public function finalize(FinalizeSurveyRequest $request, Lead $lead, Survey $survey, FinalizeSurvey $action): RedirectResponse
    {
        abort_unless($survey->lead_id === $lead->id, 404);

        $action->handle($survey, $request->user(), $request->boolean('copy_items'));

        return redirect()->route('sales.leads.show', $lead)->with(
            'success',
            $request->boolean('copy_items')
                ? 'Item rekomendasi disalin ke requirement. Survey ditutup.'
                : 'Survey ditutup.',
        );
    }

    public function cancel(Request $request, Lead $lead, Survey $survey, CancelSurvey $action): RedirectResponse
    {
        abort_unless($survey->lead_id === $lead->id, 404);
        Gate::authorize('cancel', $survey);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action->handle($survey, $request->user(), $data['reason'] ?? null);

        return redirect()->route('sales.leads.show', $lead)->with('success', 'Survey dibatalkan.');
    }
}
