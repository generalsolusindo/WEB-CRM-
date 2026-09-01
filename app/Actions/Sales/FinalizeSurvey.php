<?php

namespace App\Actions\Sales;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeSurvey
{
    public function handle(Survey $survey, User $user, bool $copyItems): Survey
    {
        return DB::transaction(function () use ($survey, $user, $copyItems) {
            $locked = Survey::query()
                ->with(['lead', 'report.items'])
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SurveyStatus::Verified->value) {
                throw ValidationException::withMessages([
                    'survey' => 'Survey belum terverifikasi.',
                ]);
            }

            if ($copyItems) {
                $items = $locked->report?->items ?? collect();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'survey' => 'Laporan tidak punya item rekomendasi untuk disalin.',
                    ]);
                }

                if ($locked->lead->requirementsLocked()) {
                    throw ValidationException::withMessages([
                        'survey' => 'Requirement terkunci (sudah dikirim ke Procurement). Buka dulu sebelum menyalin.',
                    ]);
                }

                foreach ($items as $item) {
                    $locked->lead->requirements()->create([
                        'item_name' => $item->item_name,
                        'description' => $item->notes,
                        'qty' => $item->qty,
                        'unit' => $item->unit ?: 'unit',
                        'notes' => 'Dari survey '.$locked->code,
                        'created_by' => $user->id,
                    ]);
                }
            }

            $locked->update(['status' => SurveyStatus::Closed->value]);

            return $locked;
        });
    }
}
