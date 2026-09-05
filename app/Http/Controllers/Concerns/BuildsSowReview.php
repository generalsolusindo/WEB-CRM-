<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SowStatus;
use App\Models\Sow;

/**
 * Payload read-only SOW untuk layar review HR (dan nantinya penanda-tanganan).
 */
trait BuildsSowReview
{
    /** @return array<string, mixed> */
    private function sowRow(Sow $sow): array
    {
        return [
            'id' => $sow->id,
            'number' => $sow->number,
            'project_name' => $sow->project_name,
            'customer' => $sow->project->salesOrder?->contact?->name,
            'status' => $sow->status,
            'status_label' => SowStatus::from($sow->status)->label(),
            'submitted_at' => $sow->submitted_at,
        ];
    }

    /** @return array<string, mixed> */
    private function sowDetail(Sow $sow): array
    {
        $sow->loadMissing([
            'project.salesOrder.contact:id,name,company_name',
            'project.vendor:id,name,contact_person,phone',
            'project.actualProcurements',
            'technician:id,name,phone',
            'creator:id,name',
            'hrContentReviewedBy:id,name',
            'vendorSignedBy:id,name',
            'hrSignatureReviewedBy:id,name',
            'adminSignedBy:id,name',
            'directorSignedBy:id,name',
        ]);

        return [
            'id' => $sow->id,
            'status' => $sow->status,
            'status_label' => SowStatus::from($sow->status)->label(),
            'number' => $sow->number,
            'project_name' => $sow->project_name,
            'site_location' => $sow->site_location,
            'client_name' => $sow->client_name,
            'execution_date' => $sow->execution_date,
            'background' => $sow->background,
            'scope_pre_work' => $sow->scope_pre_work,
            'scope_other' => $sow->scope_other,
            'responsibilities' => $sow->responsibilities,
            'schedule' => $sow->schedule,
            'safety' => $sow->safety,
            'payment_terms' => $sow->payment_terms,
            'output' => $sow->output,
            'warranty' => $sow->warranty,
            'notes' => $sow->notes,
            'closing' => $sow->closing,
            'technician' => $sow->technician ? ['id' => $sow->technician->id, 'name' => $sow->technician->name, 'phone' => $sow->technician->phone] : null,
            'technician_team_note' => $sow->technician_team_note,
            'client_pic_name' => $sow->client_pic_name,
            'client_pic_phone' => $sow->client_pic_phone,
            'vendor' => $sow->project->vendor ? [
                'name' => $sow->project->vendor->name,
                'contact_person' => $sow->project->vendor->contact_person,
                'phone' => $sow->project->vendor->phone,
            ] : null,
            'customer' => $sow->project->salesOrder?->contact?->name,
            'company' => $sow->project->salesOrder?->contact?->company_name,
            'materials' => $sow->project->actualProcurements->map(fn ($m) => [
                'item_name' => $m->item_name, 'qty' => $m->qty, 'unit' => $m->unit,
            ]),
            'created_by' => $sow->creator?->name,
            'hr_content_reviewed_by' => $sow->hrContentReviewedBy?->name,
            'hr_content_reviewed_at' => $sow->hr_content_reviewed_at,
            'hr_content_review_notes' => $sow->hr_content_review_notes,
            'signatures' => [
                'technician' => $sow->technician_signature,
                'technician_signed_at' => $sow->technician_signed_at,
                'vendor' => $sow->vendor_signature,
                'vendor_signed_at' => $sow->vendor_signed_at,
                'vendor_signed_by' => $sow->vendorSignedBy?->name,
                'admin' => $sow->admin_signature,
                'admin_signed_at' => $sow->admin_signed_at,
                'admin_signed_by' => $sow->adminSignedBy?->name,
                'director' => $sow->director_signature,
                'director_signed_at' => $sow->director_signed_at,
                'director_signed_by' => $sow->directorSignedBy?->name,
            ],
            'hr_signature_reviewed_by' => $sow->hrSignatureReviewedBy?->name,
            'hr_signature_reviewed_at' => $sow->hr_signature_reviewed_at,
            'hr_signature_review_notes' => $sow->hr_signature_review_notes,
            'images' => $sow->attachments()->where('category', 'sow_background')->get()->map(fn ($a) => [
                'id' => $a->id,
                'url' => \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
            ]),
        ];
    }
}
