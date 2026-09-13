<?php

namespace App\Http\Controllers\Sales;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $base = Lead::query()
            ->where('sales_id', $request->user()->id)
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        $byStage = (clone $base)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stageRows = collect(LeadStage::options())->map(fn ($stage) => [
            'label' => $stage['label'],
            'total' => (int) ($byStage[$stage['value']] ?? 0),
        ]);

        $sourceRows = (clone $base)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'Tanpa source') as source_label, count(*) as total")
            ->groupBy('source_label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => LeadSource::tryFrom($row->source_label)?->label() ?? $row->source_label,
                'total' => (int) $row->total,
            ]);

        $totalLeads = (clone $base)->count();
        $opportunities = (clone $base)->where('type', 'opportunity')->count();
        $won = (clone $base)->where('stage', LeadStage::Won->value)->count();
        $lost = (clone $base)->where('stage', LeadStage::Lost->value)->count();

        return Inertia::render('Sales/Reports/Leads', [
            'filters' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'byStage' => $stageRows,
            'bySource' => $sourceRows,
            'funnel' => [
                'leads' => $totalLeads,
                'opportunities' => $opportunities,
                'won' => $won,
                'lost' => $lost,
                'lead_to_opportunity' => $totalLeads > 0 ? round($opportunities / $totalLeads * 100, 1) : 0.0,
                'opportunity_to_won' => $opportunities > 0 ? round($won / $opportunities * 100, 1) : 0.0,
            ],
        ]);
    }
}
