<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Attachment;
use App\Models\Project;
use App\Services\Operational\MaterialDeliveryStatus;
use Illuminate\Support\Facades\Storage;

/**
 * Payload read-only project untuk layar monitoring (Management & Project
 * Manager) — beda dari Operational\ProjectController yang penuh form aksi.
 */
trait BuildsProjectOverview
{
    /** @return array<string, mixed> */
    private function projectOverviewRow(Project $p): array
    {
        return [
            'id' => $p->id,
            'number' => 'PRJ-'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT),
            'status' => $p->status,
            'status_label' => ProjectStatus::from($p->status)->label(),
            'customer' => $p->salesOrder?->contact?->name,
            'company' => $p->salesOrder?->contact?->company_name,
            'delegated_to' => $p->delegatedTo?->name,
            'material_status' => $p->salesOrder ? MaterialDeliveryStatus::of($p->salesOrder) : null,
            'is_won' => $p->salesOrder?->status === SalesOrderStatus::Won->value,
        ];
    }

    /** @return array<string, mixed> */
    private function projectOverviewDetail(Project $project): array
    {
        $project->loadMissing([
            'salesOrder:id,number,contact_id,order_type,status',
            'salesOrder.contact:id,name,company_name',
            'salesOrder.lines:id,sales_order_id,item_name,category,qty,unit,selling_price,subtotal,discount_amount,tax_rate',
            'salesOrder.invoices' => fn ($q) => $q->oldest('id'),
            'salesOrder.invoices.attachments' => fn ($q) => $q->where('category', 'pph23_slip'),
            'salesOrder.invoices.payments' => fn ($q) => $q->oldest('paid_at'),
            'salesOrder.invoices.payments.attachments' => fn ($q) => $q->where('category', 'payment_proof'),
            'salesOrder.deliveryNotes.attachments.uploader:id,name',
            'technicians.technician:id,name',
            'delegatedTo:id,name',
            'delegatedBy:id,name',
            'tasks' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('id'),
            'tasks.attachments.uploader:id,name',
            'bastRecords' => fn ($q) => $q->latest(),
            'bastRecords.submitter:id,name',
            'bastRecords.attachments.uploader:id,name',
            'attachments' => fn ($q) => $q->where('category', 'checkin_selfie')->latest(),
            'attachments.uploader:id,name',
            'statusHistories.changedBy:id,name',
            'sow:id,project_id,status',
        ]);

        $lastTransitionAt = $project->statusHistories->last()?->created_at ?? $project->created_at;
        $isOverdue = $project->planned_end
            && $project->planned_end->isPast()
            && $project->status !== ProjectStatus::Completed->value;

        $documents = collect()
            ->concat($project->bastRecords->flatMap(fn ($b) => $b->attachments->map(fn ($a) => $this->documentEntry($a, 'Dokumen BAST'))))
            ->concat($project->tasks->flatMap(fn ($t) => $t->attachments->map(fn ($a) => $this->documentEntry(
                $a,
                $a->category === 'task_after' ? "Foto Sesudah · {$t->title}" : "Foto Sebelum · {$t->title}"
            ))))
            ->concat(($project->salesOrder?->deliveryNotes ?? collect())->flatMap(fn ($dn) => $dn->attachments->map(fn ($a) => $this->documentEntry(
                $a,
                $a->category === 'delivery_received_proof' ? "Bukti Diterima · {$dn->number}" : "Bukti Kirim · {$dn->number}"
            ))))
            ->sortByDesc('at')
            ->values();

        return [
            'id' => $project->id,
            'number' => 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
            'status' => $project->status,
            'status_label' => ProjectStatus::from($project->status)->label(),
            'customer' => $project->salesOrder?->contact?->name,
            'company' => $project->salesOrder?->contact?->company_name,
            'order_type' => $project->salesOrder?->order_type,
            'sales_order' => $project->salesOrder?->number,
            'is_won' => $project->salesOrder?->status === SalesOrderStatus::Won->value,
            'stage_options' => ProjectStatus::options(),
            'planned_start' => $project->planned_start?->format('Y-m-d'),
            'planned_end' => $project->planned_end?->format('Y-m-d'),
            'is_overdue' => $isOverdue,
            'current_stage_since' => $lastTransitionAt,
            'delegated_to' => $project->delegatedTo ? ['id' => $project->delegatedTo->id, 'name' => $project->delegatedTo->name] : null,
            'delegated_by' => $project->delegatedBy?->name,
            'delegated_at' => $project->delegated_at,
            'material_status' => $project->salesOrder ? MaterialDeliveryStatus::of($project->salesOrder) : null,
            'status_histories' => $project->statusHistories->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'from_label' => $h->from_status ? ProjectStatus::from($h->from_status)->label() : null,
                'to_status' => $h->to_status,
                'to_label' => ProjectStatus::from($h->to_status)->label(),
                'changed_by' => $h->changedBy?->name,
                'at' => $h->created_at,
            ]),
            'invoices' => $project->salesOrder?->invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'phase_label' => $inv->invoice_phase ? InvoicePhase::from($inv->invoice_phase)->label() : null,
                'status' => $inv->status,
                'status_label' => InvoiceStatus::from($inv->status)->label(),
                'grand_total' => $inv->grandTotal(),
                'paid_amount' => $inv->settledAmount(),
                'due_date' => $inv->due_date?->format('Y-m-d'),
                'pph23_slip_url' => $this->attachmentUrl($inv->attachments->firstWhere('category', 'pph23_slip')),
                'payments' => $inv->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount_paid' => $p->amount_paid,
                    'paid_at' => $p->paid_at,
                    'proofs' => $p->attachments->map(fn ($a) => $this->documentEntry($a, 'Bukti Bayar'))->values(),
                ]),
            ])->all() ?? [],
            'items' => ($project->salesOrder?->lines ?? collect())->map(fn ($l) => [
                'id' => $l->id,
                'item_name' => $l->item_name,
                'category' => $l->category,
                'qty' => $l->qty,
                'unit' => $l->unit,
                'selling_price' => $l->selling_price,
                'line_total' => $l->line_total,
            ]),
            'sow' => $project->sow ? [
                'id' => $project->sow->id,
                'status' => $project->sow->status,
                'url' => request()->user()->role === 'project_manager'
                    ? "/project-manager/sows/{$project->sow->id}"
                    : "/management/sows/{$project->sow->id}",
            ] : null,
            'documents' => $documents,
            'technicians' => $project->technicians->map(fn ($pt) => [
                'name' => $pt->technician?->name,
                'is_leader' => (bool) $pt->is_leader,
            ]),
            'tasks' => $project->tasks->map(fn ($t) => [
                'id' => $t->id, 'title' => $t->title, 'status' => $t->status, 'scheduled_date' => $t->scheduled_date,
            ]),
            'bast_records' => $project->bastRecords->map(fn ($b) => [
                'id' => $b->id, 'status' => $b->status, 'submitted_at' => $b->submitted_at, 'submitter' => $b->submitter?->name,
            ]),
            'check_ins' => $project->attachments->map(fn ($a) => [
                'id' => $a->id,
                'technician' => $a->uploader?->name,
                'at' => $a->created_at,
                'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function documentEntry(Attachment $attachment, string $label): array
    {
        return [
            'id' => $attachment->id,
            'label' => $label,
            'uploader' => $attachment->uploader?->name,
            'at' => $attachment->created_at,
            'url' => $this->attachmentUrl($attachment),
        ];
    }

    private function attachmentUrl(?Attachment $attachment): ?string
    {
        return $attachment ? Storage::disk('local')->temporaryUrl($attachment->file_path, now()->addDay()) : null;
    }
}
