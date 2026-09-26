<?php

namespace App\Services\Dashboard;

use App\Enums\ActualProcurementStatus;
use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Enums\ProcurementPaymentStatus;
use App\Enums\ProcurementRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\SowStatus;
use App\Enums\SurveyStatus;
use App\Models\ActualProcurement;
use App\Models\Bast;
use App\Models\Invoice;
use App\Models\ProcurementPayment;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Sow;
use App\Models\Survey;
use App\Models\User;
use App\Models\VendorServicePayment;
use App\Models\WarehouseItem;
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

        // Baru boleh disourcing (assign vendor/surveyor) selagi masih 'requested' — lihat
        // SourceSurvey::handle(). Sama dengan yang dianggap "baru masuk" di halaman index.
        $surveyInbox = Survey::query()->where('status', SurveyStatus::Requested->value)->count();

        return [
            '/procurement/procurement-requests' => $inbox,
            '/procurement/project-procurements' => $projectProcurement,
            '/procurement/surveys' => $surveyInbox,
        ];
    }

    /** @return array<string, int> */
    private function finance(): array
    {
        // Sama persis dengan filter Finance\SurveyController::index() — supaya badge tidak
        // pernah menunjukkan angka sementara daftarnya kosong.
        $surveyInbox = Survey::query()
            ->whereIn('status', [SurveyStatus::FinanceReview->value, SurveyStatus::AwaitingPayment->value])
            ->count();

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

        // Tugas bayar vendor jasa: DP yang sudah diajukan, atau pelunasan yang BAST-nya sudah terverifikasi.
        $vendorService = VendorServicePayment::query()->with('project')->whereIn('status', ['awaiting_dp', 'in_progress'])->get()
            ->filter(fn ($p) => $p->canPayDp() || $p->canPayFinal())
            ->count();

        return [
            '/finance/invoices' => $needsUpfrontInvoice + $draft + $unpaidSent + $overdue + $readyForFinal,
            '/finance/surveys' => $surveyInbox,
            '/finance/vendor-service-payments' => $vendorService,
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

        // Sama persis dengan filter Operational\SurveyController::index() — Operational
        // baru punya "tugas" begitu perlu kasih arahan, survey sedang jalan, atau
        // laporannya perlu diverifikasi.
        $surveyInbox = Survey::query()
            ->whereIn('status', [
                SurveyStatus::AwaitingBriefing->value,
                SurveyStatus::InProgress->value,
                SurveyStatus::ReportReview->value,
            ])
            ->count();

        return [
            '/operational/projects' => $needsProject + $planning,
            '/operational/projects?status=waiting_resource' => $waitingResource,
            '/operational/projects?status=verification' => $bastToVerify,
            '/operational/surveys' => $surveyInbox,
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
            // Sama seperti Management\SowController::index() — SOW yang project-nya sudah
            // didelegasikan ke Project Manager bukan lagi jatah Management, jangan ikut dihitung
            // (sebelumnya badge menghitung semua PendingDirectorSignature tanpa syarat ini, jadi
            // bisa menampilkan angka padahal daftarnya kosong).
            '/management/sows' => Sow::query()
                ->where('status', SowStatus::PendingDirectorSignature->value)
                ->whereHas('project', fn ($q) => $q->whereNull('delegated_to'))
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function technician(User $user): array
    {
        return [
            '/technician/tasks' => $this->technicianTaskCount($user),
            '/technician/surveys' => $this->technicianSurveyCount($user),
            '/technician/sows' => Sow::query()
                ->where('technician_id', $user->id)
                ->where('status', SowStatus::PendingTechnicianSignature->value)
                ->count(),
        ];
    }

    private function technicianTaskCount(User $user): int
    {
        return ProjectTask::query()
            ->where('status', '!=', 'done')
            ->whereHas('project', fn ($q) => $q
                ->where('status', ProjectStatus::InProgress->value)
                ->whereHas('technicians', fn ($t) => $t->where('technician_id', $user->id)))
            ->count();
    }

    private function technicianSurveyCount(User $user): int
    {
        return Survey::query()
            ->where('status', SurveyStatus::InProgress->value)
            ->whereHas('surveyorAssignments', fn ($q) => $q->where('technician_id', $user->id))
            ->count();
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
            // Sama persis dengan filter ProjectManager\SowController::index().
            '/project-manager/sows' => Sow::query()
                ->where('status', SowStatus::PendingDirectorSignature->value)
                ->whereHas('project', fn ($q) => $q->where('delegated_to', $user->id))
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function vendor(User $user): array
    {
        $badges = [
            '/vendor/sows' => Sow::query()
                ->whereHas('project', fn ($q) => $q->where('vendor_id', $user->vendor_id))
                ->where('status', SowStatus::PendingVendorSignature->value)
                ->count(),
        ];

        // Akun vendor yang PIC-nya juga merangkap teknisi/surveyor (lihat getMenuForUser()
        // di menuConfig.js) dapat menu tambahan "Tugas Teknisi"/"Survey" — badge-nya harus
        // ikut muncul juga, bukan cuma untuk role 'technician' murni.
        if ($user->canWorkAsTechnician()) {
            $badges['/technician/tasks'] = $this->technicianTaskCount($user);
        }
        if ($user->canWorkAsSurveyor()) {
            $badges['/technician/surveys'] = $this->technicianSurveyCount($user);
        }

        return $badges;
    }

    /** @return array<string, int> */
    private function warehouse(): array
    {
        return [
            '/warehouse/items' => WarehouseItem::query()->where('qty_on_hand', 0)->count(),
        ];
    }
}
