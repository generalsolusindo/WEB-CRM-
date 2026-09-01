<?php

namespace App\Actions\Sales;

use App\Enums\LeadType;
use App\Enums\SurveyStatus;
use App\Models\Lead;
use App\Models\Survey;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestSurvey
{
    public function __construct(private Notify $notify) {}

    /**
     * @param  array{site_address: string, site_region: string, delivery_mode: string, billable: bool, notes: ?string}  $data
     */
    public function handle(Lead $lead, User $user, array $data): Survey
    {
        return DB::transaction(function () use ($lead, $user, $data) {
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($locked->type !== LeadType::Opportunity->value) {
                throw ValidationException::withMessages([
                    'lead' => 'Survey hanya bisa diminta dari opportunity.',
                ]);
            }

            $openStatuses = [
                SurveyStatus::Requested->value,
                SurveyStatus::FinanceReview->value,
                SurveyStatus::AwaitingPayment->value,
                SurveyStatus::AwaitingBriefing->value,
                SurveyStatus::InProgress->value,
                SurveyStatus::ReportReview->value,
            ];

            if ($locked->surveys()->whereIn('status', $openStatuses)->exists()) {
                throw ValidationException::withMessages([
                    'survey' => 'Masih ada survey berjalan untuk opportunity ini. Selesaikan dulu.',
                ]);
            }

            $survey = $locked->surveys()->create([
                'requested_by' => $user->id,
                'site_address' => $data['site_address'],
                'site_region' => $data['site_region'],
                'delivery_mode' => $data['delivery_mode'],
                'billable' => $data['billable'],
                'notes' => $data['notes'] ?? null,
                'status' => SurveyStatus::Requested->value,
            ]);

            $this->notify->onceForEach(
                User::query()->where('role', 'procurement')->where('is_active', true)->get(),
                'survey.requested',
                "Permintaan survey {$survey->code} ({$locked->contact?->name}) di {$survey->site_region} menunggu sourcing surveyor.",
                $survey,
            );

            return $survey;
        });
    }
}
