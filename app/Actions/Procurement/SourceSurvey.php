<?php

namespace App\Actions\Procurement;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SourceSurvey
{
    public function __construct(private Notify $notify) {}

    /**
     * Procurement mengamankan sumber daya: mode/vendor + biaya. Penugasan orang
     * (tim surveyor) dilakukan Operasional saat memberi arahan.
     *
     * @param  array{vendor_id: ?int, cost: float|string}  $data
     */
    public function handle(Survey $survey, User $user, array $data): Survey
    {
        return DB::transaction(function () use ($survey, $user, $data) {
            $locked = Survey::query()
                ->with('lead.contact', 'lead.sales')
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reassigning = in_array($locked->status, [
                SurveyStatus::FinanceReview->value,
                SurveyStatus::AwaitingBriefing->value,
            ], true);

            if (! $reassigning && $locked->status !== SurveyStatus::Requested->value) {
                throw ValidationException::withMessages([
                    'survey' => 'Survey ini sudah dijadwalkan / diproses, vendor & biaya tidak bisa diubah lagi.',
                ]);
            }

            if ($locked->invoices()->where('status', '!=', 'cancelled')->exists()) {
                throw ValidationException::withMessages([
                    'survey' => 'Invoice survey sudah diterbitkan Finance — biaya & vendor terkunci.',
                ]);
            }

            if ($locked->delivery_mode === 'vendor' && empty($data['vendor_id'])) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'Pilih vendor untuk survey di luar jangkauan.',
                ]);
            }

            $next = $reassigning
                ? $locked->status
                : ($locked->needsFinance()
                    ? SurveyStatus::FinanceReview->value
                    : SurveyStatus::AwaitingBriefing->value);

            $locked->update([
                'vendor_id' => $locked->delivery_mode === 'vendor' ? (int) $data['vendor_id'] : null,
                'cost' => $data['cost'],
                'sourced_by' => $user->id,
                'sourced_at' => now(),
                'status' => $next,
            ]);

            if ($reassigning) {
                return $locked;
            }

            $this->notify->resolve('survey.requested', $locked);

            $customer = $locked->lead->contact?->name ?? 'customer';

            if ($next === SurveyStatus::FinanceReview->value) {
                $this->notify->onceForEach(
                    User::query()->where('role', 'finance')->where('is_active', true)->get(),
                    'survey.finance_review',
                    "Survey {$locked->code} ({$customer}) siap ditagih / dicatat biayanya di Finance.",
                    $locked,
                );
            } else {
                $this->notify->onceForEach(
                    User::query()->where('role', 'operational')->where('is_active', true)->get(),
                    'survey.awaiting_briefing',
                    "Survey {$locked->code} ({$customer}) siap dijadwalkan & ditugaskan surveyor.",
                    $locked,
                );
            }

            return $locked;
        });
    }
}
