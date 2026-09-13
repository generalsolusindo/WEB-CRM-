<?php

namespace App\Services\Dashboard;

use App\Enums\ActualProcurementStatus;
use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Enums\ProcurementPaymentStatus;
use App\Enums\ProcurementRequestStatus;
use App\Enums\SowStatus;
use App\Models\ActualProcurement;
use App\Models\Bast;
use App\Models\Invoice;
use App\Models\ProcurementPayment;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Sow;
use App\Models\User;
use App\Services\Sales\SalesOrderSettlement;

/**
 * Angka kecil "butuh aksi kamu" per item menu Sidebar — dihitung dari data
 * asli (bukan status baca notifikasi), supaya penanda di menu langsung
 * hilang begitu tugasnya benar-benar selesai, di halaman manapun user
 * sedang berada (bukan cuma saat buka Dashboard).
 *
 * @return array<string, int>
 */
class MenuBadges
{
    public function __construct(private SalesOrderSettlement $settlement) {}

    public function for(User $user): array
    {
        return match ($user->role) {
            'sales' => $this->sales($user),
            'procurement' => $this->procurement(),
            'finance' => $this->finance(),
            'operational' => $this->operational(),
            'management' => $this->management(),
            'technician' => $this->technician($user),
            'hr' => $this->hr(),
            'project_manager' => $this->projectManager($user),
            'vendor' => $this->vendor($user),
            'warehouse' => $this->warehouse(),
            default => [],
        };
    }

    /** @return array<string, int> */
    private function sales(User $user): array
    {
        $readyWithoutQuotation = ProcurementRequest::query()
            ->where('status', ProcurementRequestStatus::Ready->value)
            ->whereHas('lead', fn ($q) => $q->where('sales_id', $user->id))
            ->whereDoesntHave('quotations')
            ->count();

        $draftOrStale = Quotation::query()
            ->where('sales_id', $user->id)
            ->where(fn ($q) => $q
                ->where('status', 'draft')
                ->orWhere(fn ($q2) => $q2->where('status', 'sent')->where('updated_at', '<', now()->subDays(7))))
            ->count();

        $readyToWin = SalesOrder::query()
            ->whereHas('quotation', fn ($q) => $q->where('sales_id', $user->id))
            ->whereNotIn('status', ['won', 'completed', 'cancelled'])
            ->get()
            ->filter(fn ($so) => $this->settlement->canCloseAsWon($so))
            ->count();

        return [
            '/sales/leads' => $readyWithoutQuotation,
            '/sales/quotations' => $draftOrStale,
            '/sales/sales-orders' => $readyToWin,
        ];
    }

    /** @return array<string, int> */
    private function procurement(): array
    {
        $inbox = ProcurementRequest::query()
            ->whereIn('status', [ProcurementRequestStatus::Submitted->value, ProcurementRequestStatus::Searching->value])
            ->count();

        $projectProcurement = ActualProcurement::query()
            ->whereIn('status', [ActualProcurementStatus::Pending->value, ActualProcurementStatus::Purchased->value])
            ->whereHas('project', fn ($q) => $q->where('status', '!=', 'completed'))
            ->distinct('project_id')
            ->count('project_id');

        return [
            '/procurement/procurement-requests' => $inbox,
            '/procurement/project-procurements' => $projectProcurement,
        ];
    }

    /** @return array<string, int> */
    private function finance(): array
    {
        $saleInvoices = fn () => Invoice::query()->where('invoice_type', 'sale');

        $needsUpfrontInvoice = SalesOrder::query()
            ->whereNotIn('status', ['cancelled'])
            ->whereDoesntHave('invoices', fn ($q) => $q
                ->whereIn('invoice_phase', [InvoicePhase::Dp->value, InvoicePhase::Full->value])
                ->where('status', '!=', InvoiceStatus::Cancelled->value))
            ->count();

        $draft = $saleInvoices()->where('status', 'draft')->count();
        $unpaidSent = $saleInvoices()->whereIn('status', ['sent', 'partially_paid'])->count();
        $overdue = $saleInvoices()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();

        $readyForFinal = SalesOrder::query()
            ->whereIn('order_type', [OrderType::ServiceOnly->value, OrderType::Mixed->value])
            ->get()
            ->filter(fn ($so) => $this->settlement->canCreateFinalInvoice($so))
            ->count();

        return [
            '/finance/invoices' => $needsUpfrontInvoice + $draft + $unpaidSent + $overdue + $readyForFinal,
        ];
    }

    /** @return array<string, int> */
    private function operational(): array
    {
        $needsProject = SalesOrder::query()
            ->whereDoesntHave('projects')
            ->get()
            ->filter(fn ($so) => $this->settlement->upfrontInvoicePaid($so))
            ->count();

        $planning = Project::query()->whereIn('status', ['draft', 'planning'])->count();
        $waitingResource = Project::query()->where('status', 'waiting_resource')->count();
        $bastToVerify = Bast::query()->where('status', 'submitted')->count();

        return [
            '/operational/projects' => $needsProject + $planning,
            '/operational/projects?status=waiting_resource' => $waitingResource,
            '/operational/projects?status=verification' => $bastToVerify,
        ];
    }

    /** @return array<string, int> */
    private function management(): array
    {
        return [
            '/management/quotations' => Quotation::query()
                ->where('pm_review_status', 'approved')
                ->whereNull('manager_review_status')
                ->count(),
            '/management/sows' => Sow::query()
                ->where('status', SowStatus::PendingDirectorSignature->value)
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function technician(User $user): array
    {
        return [
            '/technician/tasks' => \App\Models\ProjectTask::query()
                ->where('status', '!=', 'done')
                ->whereHas('project', fn ($q) => $q
                    ->where('status', \App\Enums\ProjectStatus::InProgress->value)
                    ->whereHas('technicians', fn ($t) => $t->where('technician_id', $user->id)))
                ->count(),
            '/technician/surveys' => \App\Models\Survey::query()
                ->where('status', \App\Enums\SurveyStatus::InProgress->value)
                ->whereHas('surveyorAssignments', fn ($q) => $q->where('technician_id', $user->id))
                ->count(),
            '/technician/sows' => Sow::query()
                ->where('technician_id', $user->id)
                ->where('status', SowStatus::PendingTechnicianSignature->value)
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function hr(): array
    {
        return [
            '/hr/sows' => Sow::query()
                ->whereIn('status', [SowStatus::PendingHrReview->value, SowStatus::PendingHrVerification->value])
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function projectManager(User $user): array
    {
        return [
            '/project-manager/quotations' => Quotation::query()
                ->whereHas('lead', fn ($q) => $q->where('delegated_to', $user->id))
                ->where('status', 'draft')
                ->count(),
            '/project-manager/procurement-payments' => ProcurementPayment::query()
                ->whereHas('project', fn ($q) => $q->where('delegated_to', $user->id))
                ->where('status', ProcurementPaymentStatus::PendingPm->value)
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function vendor(User $user): array
    {
        return [
            '/vendor/sows' => Sow::query()
                ->whereHas('project', fn ($q) => $q->where('vendor_id', $user->vendor_id))
                ->where('status', SowStatus::PendingVendorSignature->value)
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function warehouse(): array
    {
        return [
            '/warehouse/items' => \App\Models\WarehouseItem::query()->where('qty_on_hand', 0)->count(),
        ];
    }
}
