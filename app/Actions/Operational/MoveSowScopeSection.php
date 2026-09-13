<?php

namespace App\Actions\Operational;

use App\Models\SowScopeSection;
use Illuminate\Support\Facades\DB;

class MoveSowScopeSection
{
    /** Tukar posisi sub-bab dengan tetangganya (naik/turun) supaya urutan bisa diatur. */
    public function handle(SowScopeSection $section, string $direction): void
    {
        DB::transaction(function () use ($section, $direction) {
            $neighbor = $direction === 'up'
                ? $section->sow->scopeSections()->where('position', '<', $section->position)->orderByDesc('position')->first()
                : $section->sow->scopeSections()->where('position', '>', $section->position)->orderBy('position')->first();

            if (! $neighbor) {
                return;
            }

            [$a, $b] = [$section->position, $neighbor->position];
            $section->update(['position' => $b]);
            $neighbor->update(['position' => $a]);
        });
    }
}
