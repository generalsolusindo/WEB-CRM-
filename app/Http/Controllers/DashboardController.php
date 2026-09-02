<?php

namespace App\Http\Controllers;

use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Models\Bast;
use App\Models\Invoice;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Services\Sales\SalesOrderSettlement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SalesOrderSettlement $settlement): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'salesActions' => $user->role === 'sales'
                ? $this->salesActions($user->id, $settlement)
                : null,
            'procurementActions' => $user->role === 'procurement'
                ? $this->procurementActions()
                : null,
            'financeActions' => $user->role === 'finance'
                ? $this->financeActions($settlement)
                : null,
            'operationalActions' => $user->role === 'operational'
                ? $this->operationalActions($settlement)
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalActions(SalesOrderSettlement $settlement): array
    {
        $projectRow = fn ($p) => [
            'label' => 'PRJ-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT).' · '.($p->salesOrder->contact->name ?? '—'),
            'href' => "/operational/projects/{$p->id}",
        ];

        $needsProject = SalesOrder::query()
            ->whereDoesntHave('projects')
            ->with('contact:id,name')
            ->latest()
            ->get()
            ->filter(fn ($so) => $settlement->upfrontInvoicePaid($so))
            ->map(fn ($so) => [
                'label' => "{$so->number} · ".($so->contact->name ?? '—'),
                'href' => "/operational/sales-orders/{$so->id}/projects/create",
            ])
            ->values();

        $projects = fn (array $statuses) => Project::query()
            ->whereIn('status', $statuses)
            ->with('salesOrder.contact:id,name')
            ->latest()
            ->get()
            ->map($projectRow);

        $bastToVerify = Bast::query()
            ->where('status', 'submitted')
            ->with('project.salesOrder.contact:id,name')
            ->latest()
            ->get()
            ->map(fn ($b) => [
                'label' => 'BAST PRJ-'.str_pad((string) $b->project_id, 6, '0', STR_PAD_LEFT)
                    .' · '.($b->project->salesOrder->contact->name ?? '—'),
                'href' => "/operational/projects/{$b->project_id}",
            ]);

        return [
            'needs_project' => $needsProject,
            'planning' => $projects(['draft', 'planning']),
            'waiting_resource' => $projects(['waiting_resource']),
            'bast_to_verify' => $bastToVerify,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financeActions(SalesOrderSettlement $settlement): array
    {
        $map = fn ($inv) => [
            'label' => "{$inv->number} · ".($inv->salesOrder->contact->name ?? '—'),
            'href' => "/finance/invoices/{$inv->id}",
        ];

        // Hanya invoice Sales Order (invoice survey dikelola di menu Survey, tak punya salesOrder).
        $saleInvoices = fn () => Invoice::query()->where('invoice_type', 'sale');
        $withRels = fn ($query) => $query->with('salesOrder.contact:id,name')->latest()->get();

        return [
            'needs_upfront_invoice' => SalesOrder::query()
                ->whereNotIn('status', ['cancelled'])
                ->whereDoesntHave('invoices', fn ($query) => $query
                    ->whereIn('invoice_phase', [InvoicePhase::Dp->value, InvoicePhase::Full->value])
                    ->where('status', '!=', InvoiceStatus::Cancelled->value))
                ->with('contact:id,name')
                ->latest()
                ->get(['id', 'number', 'contact_id'])
                ->map(fn ($so) => [
                    'label' => "{$so->number} · ".($so->contact->name ?? '—'),
                    'href' => '/finance/invoices',
                ]),
            'draft' => $withRels($saleInvoices()->where('status', 'draft'))->map($map),
            'unpaid_sent' => $withRels($saleInvoices()->whereIn('status', ['sent', 'partially_paid']))->map($map),
            'overdue' => $withRels($saleInvoices()
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()))->map($map),
            'ready_for_final' => SalesOrder::query()
                ->whereIn('order_type', [OrderType::ServiceOnly->value, OrderType::Mixed->value])
                ->with('contact:id,name')
                ->latest()
                ->get()
                ->filter(fn ($so) => $settlement->canCreateFinalInvoice($so))
                ->map(fn ($so) => [
                    'label' => "{$so->number} · ".($so->contact->name ?? '—'),
                    'href' => '/finance/invoices',
                ])
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function procurementActions(): array
    {
        $map = fn ($pr) => [
            'label' => 'PR-'.str_pad((string) $pr->id, 6, '0', STR_PAD_LEFT).' · '.($pr->lead->contact->name ?? '—'),
            'href' => "/procurement/procurement-requests/{$pr->id}",
        ];

        $query = fn (string $status) => ProcurementRequest::query()
            ->where('status', $status)
            ->with('lead.contact:id,name')
            ->latest()
            ->get()
            ->map($map);

        $projectProcurement = \App\Models\ActualProcurement::query()
            ->whereIn('status', ['pending', 'purchased'])
            ->whereHas('project', fn ($q) => $q->where('status', '!=', 'completed'))
            ->with('project.salesOrder.contact:id,name')
            ->orderBy('project_id')
            ->get()
            ->groupBy('project_id')
            ->map(fn ($items, $projectId) => [
                'label' => 'PRJ-'.str_pad((string) $projectId, 6, '0', STR_PAD_LEFT)
                    .' · '.($items->first()->project->salesOrder->contact->name ?? '—')
                    .' ('.$items->count().' item)',
                'href' => '/procurement/project-procurements',
            ])
            ->values();

        return [
            'submitted' => $query('submitted'),
            'searching' => $query('searching'),
            'project_procurement' => $projectProcurement,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesActions(int $salesId, SalesOrderSettlement $settlement): array
    {
        $readyWithoutQuotation = ProcurementRequest::query()
            ->where('status', 'ready')
            ->whereHas('lead', fn ($q) => $q->where('sales_id', $salesId))
            ->whereDoesntHave('quotations')
            ->with('lead.contact:id,name,company_name')
            ->latest()
            ->get()
            ->map(fn ($pr) => [
                'label' => $pr->lead->contact->name,
                'href' => "/sales/procurement-requests/{$pr->id}/quotations/create",
            ]);

        $draftQuotations = Quotation::query()
            ->where('sales_id', $salesId)
            ->where('status', 'draft')
            ->with('contact:id,name')
            ->latest()
            ->get()
            ->map(fn ($q) => [
                'label' => "{$q->number} · {$q->contact->name}",
                'href' => "/sales/quotations/{$q->id}",
            ]);

        $staleSentQuotations = Quotation::query()
            ->where('sales_id', $salesId)
            ->where('status', 'sent')
            ->where('updated_at', '<', now()->subDays(7))
            ->with('contact:id,name')
            ->latest()
            ->get()
            ->map(fn ($q) => [
                'label' => "{$q->number} · {$q->contact->name}",
                'href' => "/sales/quotations/{$q->id}",
            ]);

        $readyToWin = SalesOrder::query()
            ->whereHas('quotation', fn ($q) => $q->where('sales_id', $salesId))
            ->whereNotIn('status', ['won', 'completed', 'cancelled'])
            ->with('contact:id,name')
            ->latest()
            ->get()
            ->filter(fn ($so) => $settlement->canCloseAsWon($so))
            ->map(fn ($so) => [
                'label' => "{$so->number} · {$so->contact->name}",
                'href' => "/sales/sales-orders/{$so->id}",
            ])
            ->values();

        return [
            'ready_without_quotation' => $readyWithoutQuotation,
            'draft_quotations' => $draftQuotations,
            'stale_sent_quotations' => $staleSentQuotations,
            'ready_to_win' => $readyToWin,
        ];
    }
}
