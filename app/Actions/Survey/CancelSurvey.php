<?php

namespace App\Actions\Survey;

use App\Enums\SurveyStatus;
use App\Models\Notification;
use App\Models\Survey;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSurvey
{
    public function __construct(private Notify $notify) {}

    public function handle(Survey $survey, User $actor, ?string $reason): Survey
    {
        return DB::transaction(function () use ($survey, $actor, $reason) {
            $locked = Survey::query()
                ->with(['lead.contact', 'lead.sales', 'surveyor'])
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, [
                SurveyStatus::Verified->value,
                SurveyStatus::Closed->value,
                SurveyStatus::Cancelled->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'survey' => 'Survey ini sudah tidak bisa dibatalkan.',
                ]);
            }

            $invoice = $locked->invoices()->where('status', '!=', 'cancelled')->first();
            if ($invoice) {
                if ($invoice->payments()->exists()) {
                    throw ValidationException::withMessages([
                        'survey' => 'Invoice survey sudah menerima pembayaran. Minta Finance membatalkan invoice-nya dulu (menu Finance → Survey), baru survey bisa dibatalkan.',
                    ]);
                }
                $invoice->update(['status' => 'cancelled']);
            }

            $locked->update([
                'status' => SurveyStatus::Cancelled->value,
                'cancel_reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            // Bereskan notifikasi "menunggu ..." dari tahap sebelumnya supaya tidak jadi sampah.
            Notification::query()
                ->where('related_type', $locked->getMorphClass())
                ->where('related_id', $locked->id)
                ->where('type', 'like', 'survey.%')
                ->where('type', '!=', 'survey.cancelled')
                ->delete();

            $customer = $locked->lead->contact?->name ?? 'customer';
            $suffix = $reason ? " Alasan: {$reason}" : '';

            $recipients = collect();
            if ($locked->lead->sales && $locked->lead->sales->id !== $actor->id) {
                $recipients->push($locked->lead->sales);
            }
            if ($locked->surveyor && $locked->surveyor->id !== $actor->id) {
                $recipients->push($locked->surveyor);
            }
            $recipients = $recipients
                ->merge(User::query()->where('role', 'procurement')->where('is_active', true)->get())
                ->unique('id');

            $this->notify->onceForEach(
                $recipients,
                'survey.cancelled',
                "Survey {$locked->code} ({$customer}) dibatalkan oleh {$actor->name}.{$suffix}",
                $locked,
            );

            return $locked;
        });
    }
}
