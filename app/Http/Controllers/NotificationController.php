<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function read(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === request()->user()->id, 403);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return redirect($this->targetUrl($notification));
    }

    private function targetUrl(Notification $notification): string
    {
        return match ($notification->type) {
            'sales_order.ready_to_win' => "/sales/sales-orders/{$notification->related_id}",
            'sales_order.created' => "/finance/sales-orders/{$notification->related_id}/invoices/create",
            'invoice.upfront_paid',
            'project_procurement.ready' => "/operational/projects/{$notification->related_id}",
            'project_procurement.requested' => '/procurement/project-procurements',
            'procurement_request.ready',
            'procurement_request.rejected' => $this->leadUrlForProcurementRequest($notification->related_id),
            'quotation.pending_pm_review' => "/project-manager/quotations/{$notification->related_id}",
            'quotation.pending_manager_review' => "/management/quotations/{$notification->related_id}",
            'quotation.review_rejected',
            'quotation.fully_approved' => "/sales/quotations/{$notification->related_id}",
            'sow.pending_hr_review',
            'sow.pending_hr_verification' => "/hr/sows/{$notification->related_id}",
            'sow.rejected_by_hr',
            'sow.rejected_signature',
            'sow.pending_admin_signature',
            'sow.completed' => $this->sowProjectUrl($notification->related_id),
            'sow.pending_technician_signature' => "/technician/sows/{$notification->related_id}",
            'sow.pending_vendor_signature' => "/vendor/sows/{$notification->related_id}",
            'sow.pending_director_signature' => "/management/sows/{$notification->related_id}",
            default => '/dashboard',
        };
    }

    private function sowProjectUrl(?int $sowId): string
    {
        $projectId = \App\Models\Sow::whereKey($sowId)->value('project_id');

        return $projectId ? "/operational/projects/{$projectId}/sow" : '/dashboard';
    }

    private function leadUrlForProcurementRequest(?int $procurementRequestId): string
    {
        $leadId = \App\Models\ProcurementRequest::whereKey($procurementRequestId)->value('lead_id');

        return $leadId ? "/sales/leads/{$leadId}" : '/dashboard';
    }
}
