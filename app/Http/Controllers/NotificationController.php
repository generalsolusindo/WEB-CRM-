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
            default => '/dashboard',
        };
    }

    private function leadUrlForProcurementRequest(?int $procurementRequestId): string
    {
        $leadId = \App\Models\ProcurementRequest::whereKey($procurementRequestId)->value('lead_id');

        return $leadId ? "/sales/leads/{$leadId}" : '/dashboard';
    }
}
