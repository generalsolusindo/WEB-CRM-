<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Concerns\NormalizesDateRangeFilter;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Management\ProjectProfitCalculator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan profit per-project untuk Manager: HPP, harga jual, dan profit tiap project,
 * bisa difilter per rentang tanggal (dari tanggal project mulai berjalan) dan berdasarkan
 * status (Won / masih berjalan). Read-only, tidak ada aksi.
 *
 * Rumus HPP/harga jual/profit ada di ProjectProfitCalculator — dipakai bersama dengan
 * ringkasan pendapatan di Dashboard Management supaya angkanya selalu konsisten.
 */
class ProjectProfitController extends Controller
{
    use NormalizesDateRangeFilter;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $this->normalizeDateRange($request->validate([
            'scope' => ['nullable', Rule::in(['won', 'running'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $query = Project::query()
            ->whereHas('salesOrder', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->with(ProjectProfitCalculator::eagerLoads())
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when(($filters['scope'] ?? null) === 'won', fn ($q) => $q->whereHas('salesOrder', fn ($q2) => $q2->where('status', 'won')))
            ->when(($filters['scope'] ?? null) === 'running', fn ($q) => $q->whereHas('salesOrder', fn ($q2) => $q2->where('status', '!=', 'won')))
            ->latest('created_at');

        $rows = $query->get()->map(fn (Project $project) => ProjectProfitCalculator::rowFor($project));

        $summary = [
            'total_hpp' => round((float) $rows->sum('hpp'), 2),
            'total_harga_jual' => round((float) $rows->sum('harga_jual'), 2),
            'total_profit' => round((float) $rows->sum('profit'), 2),
            'count' => $rows->count(),
        ];

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 20;
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Management/ProjectProfit/Index', [
            'projects' => $paginated,
            'summary' => $summary,
            'filters' => [
                'scope' => $filters['scope'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }
}
