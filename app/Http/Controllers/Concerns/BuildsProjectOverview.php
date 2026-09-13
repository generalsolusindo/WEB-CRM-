<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ProjectStatus;
use App\Enums\SalesOrderStatus;
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
            'salesOrder.lines:id,sales_order_id,category,qty',
            'technicians.technician:id,name',
            'delegatedTo:id,name',
            'delegatedBy:id,name',
            'tasks' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('id'),
            'bastRecords' => fn ($q) => $q->latest(),
            'bastRecords.submitter:id,name',
            'attachments' => fn ($q) => $q->where('category', 'checkin_selfie')->latest(),
            'attachments.uploader:id,name',
        ]);

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
            'delegated_to' => $project->delegatedTo ? ['id' => $project->delegatedTo->id, 'name' => $project->delegatedTo->name] : null,
            'delegated_by' => $project->delegatedBy?->name,
            'delegated_at' => $project->delegated_at,
            'material_status' => $project->salesOrder ? MaterialDeliveryStatus::of($project->salesOrder) : null,
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
}
