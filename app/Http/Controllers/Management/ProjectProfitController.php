<?php

namespace App\Http\Controllers\Management;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Concerns\NormalizesDateRangeFilter;
use App\Http\Controllers\Controller;
use App\Models\Project;
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
 * HPP dihitung dari:
 * - Baris material: ActualProcurement.cost_price — field ini otomatis "aktual kalau sudah
 *   ada, estimasi kalau belum", karena diisi = estimasi saat Project dibuat, lalu ditimpa
 *   Procurement dengan harga beli sungguhan begitu barang sudah disourcing.
 * - Baris jasa: cost_price dari Sales Order Line — jasa tidak melalui pembelian vendor,
 *   jadi tidak pernah punya data ActualProcurement.
 *
 * Harga jual = subtotal (DPP) Sales Order yang dikonfirmasi customer — nilai deal yang
 * disepakati, tidak berubah walau Invoice-nya diedit belakangan.
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
            ->with([
                'salesOrder:id,number,contact_id,status',
                'salesOrder.contact:id,name,company_name',
                'salesOrder.lines:id,sales_order_id,category,qty,cost_price,subtotal',
                'actualProcurements:id,project_id,qty,cost_price',
            ])
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when(($filters['scope'] ?? null) === 'won', fn ($q) => $q->whereHas('salesOrder', fn ($q2) => $q2->where('status', 'won')))
            ->when(($filters['scope'] ?? null) === 'running', fn ($q) => $q->whereHas('salesOrder', fn ($q2) => $q2->where('status', '!=', 'won')))
            ->latest('created_at');

        $rows = $query->get()->map(function (Project $project) {
            $lines = $project->salesOrder->lines;

            $sellingTotal = round((float) $lines->sum('subtotal'), 2);
            $materialCost = round($project->actualProcurements->sum(
                fn ($ap) => (float) $ap->qty * (float) $ap->cost_price
            ), 2);
            $serviceCost = round($lines->where('category', 'service')->sum(
                fn ($l) => (float) $l->qty * (float) $l->cost_price
            ), 2);
            $hpp = round($materialCost + $serviceCost, 2);
            $profit = round($sellingTotal - $hpp, 2);

            return [
                'id' => $project->id,
                'number' => $project->salesOrder->number,
                'customer' => $project->salesOrder->contact?->name,
                'company' => $project->salesOrder->contact?->company_name,
                'project_status' => $project->status,
                'project_status_label' => ProjectStatus::from($project->status)->label(),
                'is_won' => $project->salesOrder->status === 'won',
                'created_at' => $project->created_at->format('Y-m-d'),
                'hpp' => $hpp,
                'harga_jual' => $sellingTotal,
                'profit' => $profit,
                'margin_percent' => $hpp > 0 ? round($profit / $hpp * 100, 2) : null,
            ];
        });

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
