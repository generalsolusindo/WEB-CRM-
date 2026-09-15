<?php

namespace App\Http\Controllers\Concerns;

/**
 * Menormalkan filter tanggal dari-sampai tanpa pernah melempar ValidationException.
 *
 * Sengaja TIDAK memakai rule silang semacam `after_or_equal:from` — pada halaman
 * berbasis query-string (GET, bukan form submit), kegagalan validasi membuat Laravel
 * redirect ke url()->previous(), yang tanpa "previous URL" yang valid di sesi (mis.
 * link yang dibagikan/bookmark dibuka langsung) bisa balik ke URL yang sama persis
 * dan berulang tanpa henti (infinite redirect loop). Kalau urutannya terbalik,
 * cukup ditukar diam-diam — jauh lebih aman untuk halaman monitoring read-only.
 */
trait NormalizesDateRangeFilter
{
    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array{from?: string|null, to?: string|null}
     */
    private function normalizeDateRange(array $filters): array
    {
        if (! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        return $filters;
    }
}
