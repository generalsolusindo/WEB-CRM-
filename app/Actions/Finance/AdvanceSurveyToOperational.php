<?php

namespace App\Actions\Finance;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Melepas survey ke Operasional setelah Finance selesai:
 *  - invoice survey lunas (dipanggil dari RecordPayment), atau
 *  - Finance menandai biaya vendor tercatat tanpa menagih customer.
 */
class AdvanceSurveyToOperational
{
    public function __construct(private Notify $notify) {}

    public function handle(Survey $survey, ?User $financeUser = null, ?string $note = null): Survey
    {
        return DB::transaction(function () use ($survey, $financeUser, $note) {
            $locked = Survey::query()
                ->with('lead.contact')
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [
                SurveyStatus::FinanceReview->value,
                SurveyStatus::AwaitingPayment->value,
            ], true)) {
                return $locked;
            }

            $attributes = ['status' => SurveyStatus::AwaitingBriefing->value];

            if ($financeUser) {
                $attributes['finance_handled_by'] = $locked->finance_handled_by ?? $financeUser->id;
                $attributes['finance_handled_at'] = $locked->finance_handled_at ?? now();
            }
            if ($note !== null) {
                $attributes['finance_note'] = $note;
            }

            $locked->update($attributes);

            $customer = $locked->lead->contact?->name ?? 'customer';

            $this->notify->onceForEach(
                User::query()->where('role', 'operational')->where('is_active', true)->get(),
                'survey.awaiting_briefing',
                "Survey {$locked->code} ({$customer}) sudah lolos Finance. Siap dijadwalkan & diberi arahan ke surveyor.",
                $locked,
            );

            return $locked;
        });
    }
}
