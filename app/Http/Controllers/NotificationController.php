<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Sow;
use App\Models\VendorServicePayment;
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
            'invoice.payment_cancelled' => request()->user()->role === 'management'
                ? "/management/projects/{$notification->related_id}"
                : "/operational/projects/{$notification->related_id}",
            'project_procurement.requested' => '/procurement/project-procurements',
            'vendor_service.needed' => "/procurement/project-procurements/{$notification->related_id}",
            'vendor_service.released',
            'vendor_service.payment_cancelled' => "/operational/projects/{$notification->related_id}",
            'vendor_service.dp_due',
            'vendor_service.final_due',
            'vendor_service.pay_after_bast' => $this->vendorServiceUrl($notification->related_id),
            'procurement_request.recost_requested' => "/procurement/procurement-requests/{$notification->related_id}",
            'procurement_request.ready' => $this->quotationOrLeadUrlForProcurementRequest($notification->related_id),
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

    private function vendorServiceUrl(?int $projectId): string
    {
        $id = VendorServicePayment::where('project_id', $projectId)->value('id');

        return $id ? "/finance/vendor-service-payments/{$id}" : '/finance/vendor-service-payments';
    }

    private function sowProjectUrl(?int $sowId): string
    {
        $projectId = Sow::whereKey($sowId)->value('project_id');

        return $projectId ? "/operational/projects/{$projectId}/sow" : '/dashboard';
    }

    /** PR yang sudah punya quotation (mis. setelah costing ulang) -> langsung ke quotation-nya, bukan ke lead. */
    private function quotationOrLeadUrlForProcurementRequest(?int $procurementRequestId): string
    {
        $quotationId = Quotation::where('procurement_request_id', $procurementRequestId)->latest('id')->value('id');

        return $quotationId ? "/sales/quotations/{$quotationId}" : $this->leadUrlForProcurementRequest($procurementRequestId);
    }

    private function leadUrlForProcurementRequest(?int $procurementRequestId): string
    {
        $leadId = ProcurementRequest::whereKey($procurementRequestId)->value('lead_id');

        return $leadId ? "/sales/leads/{$leadId}" : '/dashboard';
    }
}
