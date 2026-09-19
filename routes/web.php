<?php

use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\ProcurementPaymentController as FinanceProcurementPaymentController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\SurveyController as FinanceSurveyController;
use App\Http\Controllers\Hr\SowController as HrSowController;
use App\Http\Controllers\Management\OpportunityController as ManagementOpportunityController;
use App\Http\Controllers\Management\ProjectController as ManagementProjectController;
use App\Http\Controllers\Management\ProjectManagerAccountController;
use App\Http\Controllers\Management\ProjectProfitController;
use App\Http\Controllers\Management\QuotationController as ManagementQuotationController;
use App\Http\Controllers\Management\SowController as ManagementSowController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectManager\OpportunityController as ProjectManagerOpportunityController;
use App\Http\Controllers\ProjectManager\ProjectController as ProjectManagerProjectController;
use App\Http\Controllers\ProjectManager\ProcurementPaymentController as ProjectManagerProcurementPaymentController;
use App\Http\Controllers\ProjectManager\QuotationController as ProjectManagerQuotationController;
use App\Http\Controllers\ProjectManager\SowController as ProjectManagerSowController;
use App\Http\Controllers\Operational\ActualProcurementController;
use App\Http\Controllers\Operational\BastDraftController;
use App\Http\Controllers\Operational\BastVerificationController;
use App\Http\Controllers\Operational\DeliveryNoteController;
use App\Http\Controllers\Operational\ProjectChangeRequestController;
use App\Http\Controllers\Operational\ProjectController;
use App\Http\Controllers\Operational\ProjectTaskController;
use App\Http\Controllers\Operational\ProjectTechnicianController;
use App\Http\Controllers\Operational\SowController;
use App\Http\Controllers\Operational\SurveyController as OperationalSurveyController;
use App\Http\Controllers\Procurement\ProcurementRequestController;
use App\Http\Controllers\Procurement\ProjectProcurementController;
use App\Http\Controllers\Procurement\SurveyController as ProcurementSurveyController;
use App\Http\Controllers\Procurement\TechnicianAccountController;
use App\Http\Controllers\Procurement\VendorAccountController;
use App\Http\Controllers\Technician\BastController as TechnicianBastController;
use App\Http\Controllers\Technician\DeliveryNoteController as TechnicianDeliveryNoteController;
use App\Http\Controllers\Technician\SowController as TechnicianSowController;
use App\Http\Controllers\Technician\SurveyController as TechnicianSurveyController;
use App\Http\Controllers\Technician\TaskController as TechnicianTaskController;
use App\Http\Controllers\Procurement\VendorController;
use App\Http\Controllers\Procurement\VendorProductController;
use App\Http\Controllers\Vendor\SowController as VendorSowController;
use App\Http\Controllers\Warehouse\WarehouseItemController;
use App\Http\Controllers\Sales\ContactController;
use App\Http\Controllers\Sales\LeadController;
use App\Http\Controllers\Sales\LeadReportController;
use App\Http\Controllers\Sales\MeetingController;
use App\Http\Controllers\Sales\QuotationConfirmationController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\QuotationRevisionController;
use App\Http\Controllers\Sales\RequirementController;
use App\Http\Controllers\Sales\LeadSurveyController;
use App\Http\Controllers\Sales\SalesOrderController;
use App\Http\Controllers\Sales\SubmitAddendumController;
use App\Http\Controllers\Sales\SubmitProcurementRequestController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::prefix('admin')->name('admin.')->middleware('role:administrator')->group(function () {
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class)
            ->except(['show', 'destroy']);
        Route::resource('taxes', TaxController::class)->except('show');
        Route::get('signature', [\App\Http\Controllers\Admin\SignatureController::class, 'edit'])->name('signature.edit');
        Route::post('signature', [\App\Http\Controllers\Admin\SignatureController::class, 'update'])->name('signature.update');
    });

    Route::prefix('management')->name('management.')->middleware('role:management')->group(function () {
        Route::get('project-managers', [ProjectManagerAccountController::class, 'index'])
            ->name('project-managers.index');
        Route::get('project-managers/create', [ProjectManagerAccountController::class, 'create'])
            ->name('project-managers.create');
        Route::post('project-managers', [ProjectManagerAccountController::class, 'store'])
            ->name('project-managers.store');
        Route::get('project-managers/{projectManager}/edit', [ProjectManagerAccountController::class, 'edit'])
            ->name('project-managers.edit');
        Route::put('project-managers/{projectManager}', [ProjectManagerAccountController::class, 'update'])
            ->name('project-managers.update');
        Route::get('projects', [ManagementProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [ManagementProjectController::class, 'show'])->name('projects.show');
        Route::put('projects/{project}/delegate', [ManagementProjectController::class, 'delegate'])->name('projects.delegate');
        Route::get('opportunities', [ManagementOpportunityController::class, 'index'])->name('opportunities.index');
        Route::get('opportunities/{lead}', [ManagementOpportunityController::class, 'show'])->name('opportunities.show');
        Route::put('opportunities/{lead}/delegate', [ManagementOpportunityController::class, 'delegate'])->name('opportunities.delegate');
        Route::get('quotations', [ManagementQuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations-overview', [ManagementQuotationController::class, 'all'])->name('quotations.all');
        // Alias URL untuk baris di "Semua Quotation" — controller show() sama persis, cuma
        // beda prefix supaya sidebar tetap highlight "Semua Quotation", bukan "Verifikasi
        // Quotation" (yang juga hidup di prefix /management/quotations).
        Route::get('quotations-overview/{quotation}', [ManagementQuotationController::class, 'show'])->name('quotations.overview-show');
        Route::get('quotations/{quotation}', [ManagementQuotationController::class, 'show'])->name('quotations.show');
        Route::post('quotations/{quotation}/review', [ManagementQuotationController::class, 'review'])->name('quotations.review');
        Route::get('sows', [ManagementSowController::class, 'index'])->name('sows.index');
        Route::get('sows/{sow}', [ManagementSowController::class, 'show'])->name('sows.show');
        Route::post('sows/{sow}/sign', [ManagementSowController::class, 'sign'])->name('sows.sign');
        Route::get('procurement-requests', [\App\Http\Controllers\Management\ProcurementRequestController::class, 'index'])
            ->name('procurement-requests.index');
        Route::get('invoices', [\App\Http\Controllers\Management\InvoiceController::class, 'index'])
            ->name('invoices.index');
        Route::get('surveys', [\App\Http\Controllers\Management\SurveyController::class, 'index'])
            ->name('surveys.index');
        Route::get('project-profit', [ProjectProfitController::class, 'index'])
            ->name('project-profit.index');
    });

    Route::prefix('project-manager')->name('project-manager.')->middleware('role:project_manager')->group(function () {
        Route::get('opportunities', [ProjectManagerOpportunityController::class, 'index'])->name('opportunities.index');
        Route::get('opportunities/{lead}', [ProjectManagerOpportunityController::class, 'show'])->name('opportunities.show');
        Route::get('projects', [ProjectManagerProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [ProjectManagerProjectController::class, 'show'])->name('projects.show');
        Route::get('quotations', [ProjectManagerQuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/{quotation}', [ProjectManagerQuotationController::class, 'show'])->name('quotations.show');
        Route::post('quotations/{quotation}/review', [ProjectManagerQuotationController::class, 'review'])->name('quotations.review');
        Route::get('procurement-payments', [ProjectManagerProcurementPaymentController::class, 'index'])->name('procurement-payments.index');
        Route::get('procurement-payments/{procurementPayment}', [ProjectManagerProcurementPaymentController::class, 'show'])->name('procurement-payments.show');
        Route::post('procurement-payments/{procurementPayment}/review', [ProjectManagerProcurementPaymentController::class, 'review'])->name('procurement-payments.review');
        Route::get('sows', [ProjectManagerSowController::class, 'index'])->name('sows.index');
        Route::get('sows/{sow}', [ProjectManagerSowController::class, 'show'])->name('sows.show');
        Route::post('sows/{sow}/sign', [ProjectManagerSowController::class, 'sign'])->name('sows.sign');
    });

    Route::prefix('hr')->name('hr.')->middleware('role:hr')->group(function () {
        Route::get('sows', [HrSowController::class, 'index'])->name('sows.index');
        Route::get('sows/{sow}', [HrSowController::class, 'show'])->name('sows.show');
        Route::post('sows/{sow}/review', [HrSowController::class, 'review'])->name('sows.review');
        Route::post('sows/{sow}/verify-signatures', [HrSowController::class, 'verifySignatures'])->name('sows.verify-signatures');
    });

    Route::prefix('vendor')->name('vendor.')->middleware('role:vendor')->group(function () {
        Route::get('sows', [VendorSowController::class, 'index'])->name('sows.index');
        Route::get('sows/{sow}', [VendorSowController::class, 'show'])->name('sows.show');
        Route::post('sows/{sow}/sign', [VendorSowController::class, 'sign'])->name('sows.sign');
    });

    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        // Procurement juga boleh lihat stok (read-only) supaya bisa cek ketersediaan saat sourcing.
        Route::get('items', [WarehouseItemController::class, 'index'])
            ->name('items.index')
            ->middleware('role:warehouse,procurement');

        Route::middleware('role:warehouse')->group(function () {
            Route::resource('items', WarehouseItemController::class)->except('index', 'show');
            Route::post('items/{item}/adjust', [WarehouseItemController::class, 'adjust'])->name('items.adjust');
        });
    });

    Route::prefix('procurement')->name('procurement.')->middleware('role:procurement')->group(function () {
        Route::resource('vendors', VendorController::class);
        Route::resource('technicians', TechnicianAccountController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('vendor-accounts', VendorAccountController::class)
            ->parameters(['vendor-accounts' => 'vendorAccount'])
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::get('surveys', [ProcurementSurveyController::class, 'index'])->name('surveys.index');
        Route::get('surveys/{survey}', [ProcurementSurveyController::class, 'show'])->name('surveys.show');
        Route::post('surveys/{survey}/source', [ProcurementSurveyController::class, 'source'])->name('surveys.source');
        Route::resource('vendor-products', VendorProductController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::post('procurement-requests/{procurementRequest}/start', [ProcurementRequestController::class, 'start'])
            ->name('procurement-requests.start');
        Route::put('procurement-requests/{procurementRequest}/lines', [ProcurementRequestController::class, 'saveLines'])
            ->name('procurement-requests.lines');
        Route::post('procurement-requests/{procurementRequest}/ready', [ProcurementRequestController::class, 'ready'])
            ->name('procurement-requests.ready');
        Route::post('procurement-requests/{procurementRequest}/reject', [ProcurementRequestController::class, 'reject'])
            ->name('procurement-requests.reject');
        Route::resource('procurement-requests', ProcurementRequestController::class)->only(['index', 'show']);
        Route::get('project-procurements', [ProjectProcurementController::class, 'index'])
            ->name('project-procurements.index');
        Route::get('project-procurements/{project}', [ProjectProcurementController::class, 'show'])
            ->name('project-procurements.show');
        Route::put('project-procurements/{project}/sourcing', [ProjectProcurementController::class, 'saveSourcing'])
            ->name('project-procurements.sourcing');
        Route::post('project-procurements/{project}/submit', [ProjectProcurementController::class, 'submit'])
            ->name('project-procurements.submit');
        Route::post('project-procurements/{project}/confirm', [ProjectProcurementController::class, 'confirm'])
            ->name('project-procurements.confirm');
        Route::post('project-procurements/items/{actualProcurement}/receive', [ProjectProcurementController::class, 'receiveItem'])
            ->name('project-procurements.receive');
        Route::post('project-procurements/{project}/receive-all', [ProjectProcurementController::class, 'receiveAll'])
            ->name('project-procurements.receive-all');
        Route::get('procurement-payments/{procurementPayment}', [ProjectProcurementController::class, 'showPayment'])
            ->name('procurement-payments.show');
    });

    Route::prefix('finance')->name('finance.')->middleware('role:finance')->group(function () {
        Route::get('sales-orders/{salesOrder}/invoices/create', [InvoiceController::class, 'create'])
            ->name('sales-orders.invoices.create');
        Route::post('sales-orders/{salesOrder}/final-invoice', [InvoiceController::class, 'storeFinal'])
            ->name('sales-orders.final-invoice');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/send-whatsapp', [InvoiceController::class, 'sendWhatsapp'])->name('invoices.send-whatsapp');
        Route::patch('invoices/{invoice}/number', [InvoiceController::class, 'updateNumber'])->name('invoices.number.update');
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('invoices/{invoice}/pph23', [InvoiceController::class, 'updatePph23'])->name('invoices.pph23');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('surveys', [FinanceSurveyController::class, 'index'])->name('surveys.index');
        Route::get('surveys/{survey}', [FinanceSurveyController::class, 'show'])->name('surveys.show');
        Route::post('surveys/{survey}/invoice', [FinanceSurveyController::class, 'issueInvoice'])->name('surveys.invoice');
        Route::post('surveys/{survey}/clear', [FinanceSurveyController::class, 'clear'])->name('surveys.clear');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::post('invoices/{invoice}/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('invoices.payments.cancel');
        Route::get('procurement-payments', [FinanceProcurementPaymentController::class, 'index'])->name('procurement-payments.index');
        Route::get('procurement-payments/{procurementPayment}', [FinanceProcurementPaymentController::class, 'show'])->name('procurement-payments.show');
        Route::post('procurement-payments/{procurementPayment}/pay', [FinanceProcurementPaymentController::class, 'pay'])->name('procurement-payments.pay');
        Route::resource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
    });

    // Lihat PDF invoice (bukan cuma buat/kelola) — Management juga boleh, buat tracking read-only.
    Route::prefix('finance')->name('finance.')->middleware('role:finance,management')->group(function () {
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    });

    Route::prefix('technician')->name('technician.')->middleware('role:technician')->group(function () {
        Route::get('surveys', [TechnicianSurveyController::class, 'index'])->name('surveys.index');
        Route::get('surveys/{survey}', [TechnicianSurveyController::class, 'show'])->name('surveys.show');
        Route::put('surveys/{survey}/report', [TechnicianSurveyController::class, 'saveReport'])->name('surveys.report.save');
        Route::post('surveys/{survey}/report/submit', [TechnicianSurveyController::class, 'submitReport'])->name('surveys.report.submit');
        Route::post('surveys/{survey}/report/attachments', [TechnicianSurveyController::class, 'uploadAttachment'])->name('surveys.report.attachments');
        Route::delete('surveys/{survey}/report/attachments/{attachment}', [TechnicianSurveyController::class, 'deleteAttachment'])->name('surveys.report.attachments.destroy');
        Route::post('surveys/{survey}/checkin', [TechnicianSurveyController::class, 'checkIn'])->name('surveys.checkin');
        Route::post('surveys/{survey}/checkout', [TechnicianSurveyController::class, 'checkOut'])->name('surveys.checkout');
        Route::get('tasks', [TechnicianTaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{task}', [TechnicianTaskController::class, 'show'])->name('tasks.show');
        Route::post('tasks/{task}/status', [TechnicianTaskController::class, 'updateStatus'])->name('tasks.status');
        Route::post('tasks/{task}/photos', [TechnicianTaskController::class, 'uploadPhoto'])->name('tasks.photos');
        Route::post('projects/{project}/checkin', [TechnicianTaskController::class, 'checkIn'])->name('projects.checkin');
        Route::post('projects/{project}/checkout', [TechnicianTaskController::class, 'checkOut'])->name('projects.checkout');
        Route::get('projects/{project}/bast/create', [TechnicianBastController::class, 'create'])->name('projects.bast.create');
        Route::post('projects/{project}/bast', [TechnicianBastController::class, 'store'])->name('projects.bast.store');
        Route::get('projects/{project}/delivery-notes', [TechnicianDeliveryNoteController::class, 'index'])
            ->name('projects.delivery-notes.index');
        Route::get('delivery-notes/{deliveryNote}', [TechnicianDeliveryNoteController::class, 'show'])
            ->name('delivery-notes.show');
        Route::post('delivery-notes/{deliveryNote}/receive', [TechnicianDeliveryNoteController::class, 'receive'])
            ->name('delivery-notes.receive');
        Route::get('sows', [TechnicianSowController::class, 'index'])->name('sows.index');
        Route::get('sows/{sow}', [TechnicianSowController::class, 'show'])->name('sows.show');
        Route::post('sows/{sow}/sign', [TechnicianSowController::class, 'sign'])->name('sows.sign');
    });

    Route::prefix('operational')->name('operational.')->middleware('role:operational')->group(function () {
        Route::put('projects/{project}/planning', [ProjectController::class, 'planning'])
            ->name('projects.planning');
        Route::put('projects/{project}/vendor', [ProjectController::class, 'assignVendor'])
            ->name('projects.vendor');
        Route::post('projects/{project}/ready', [ProjectController::class, 'markReady'])
            ->name('projects.ready');
        Route::post('projects/{project}/start', [ProjectController::class, 'start'])
            ->name('projects.start');
        Route::post('projects/{project}/complete', [ProjectController::class, 'complete'])
            ->name('projects.complete');
        Route::put('projects/{project}/bast/{bast}', [BastVerificationController::class, 'update'])
            ->name('projects.bast.verify');
        Route::post('projects/{project}/change-requests', [ProjectChangeRequestController::class, 'store'])
            ->name('projects.change-requests.store');
        Route::put('projects/{project}/change-requests/{changeRequest}', [ProjectChangeRequestController::class, 'update'])
            ->name('projects.change-requests.update');
        Route::put('projects/{project}/technicians', [ProjectTechnicianController::class, 'update'])
            ->name('projects.technicians');
        Route::get('projects/{project}/bast-draft', [BastDraftController::class, 'edit'])
            ->name('projects.bast-draft.edit');
        Route::put('projects/{project}/bast-draft', [BastDraftController::class, 'update'])
            ->name('projects.bast-draft.update');
        Route::get('projects/{project}/bast-draft/print', [BastDraftController::class, 'print'])
            ->name('projects.bast-draft.print');
        Route::get('projects/{project}/sow', [SowController::class, 'edit'])
            ->name('projects.sow.edit');
        Route::put('projects/{project}/sow', [SowController::class, 'update'])
            ->name('projects.sow.update');
        Route::post('projects/{project}/sow/images', [SowController::class, 'storeImage'])
            ->name('projects.sow.images.store');
        Route::delete('projects/{project}/sow/images/{image}', [SowController::class, 'destroyImage'])
            ->name('projects.sow.images.destroy');
        Route::post('projects/{project}/sow/submit', [SowController::class, 'submit'])
            ->name('projects.sow.submit');
        Route::get('projects/{project}/sow/print', [SowController::class, 'print'])
            ->name('projects.sow.print');
        Route::post('projects/{project}/sow/scope-sections', [SowController::class, 'storeScopeSection'])
            ->name('projects.sow.scope-sections.store');
        Route::put('projects/{project}/sow/scope-sections/{scopeSection}', [SowController::class, 'updateScopeSection'])
            ->name('projects.sow.scope-sections.update');
        Route::delete('projects/{project}/sow/scope-sections/{scopeSection}', [SowController::class, 'destroyScopeSection'])
            ->name('projects.sow.scope-sections.destroy');
        Route::post('projects/{project}/sow/scope-sections/{scopeSection}/move', [SowController::class, 'moveScopeSection'])
            ->name('projects.sow.scope-sections.move');
        Route::post('projects/{project}/sow/scope-sections/{scopeSection}/images', [SowController::class, 'storeScopeImage'])
            ->name('projects.sow.scope-sections.images.store');
        Route::delete('projects/{project}/sow/scope-sections/{scopeSection}/images/{image}', [SowController::class, 'destroyScopeImage'])
            ->name('projects.sow.scope-sections.images.destroy');
        Route::post('sows/{sow}/sign-operational', [SowController::class, 'signOperational'])
            ->name('sows.sign-operational');
        Route::post('sows/{sow}/restart-signatures', [SowController::class, 'restartSignatures'])
            ->name('sows.restart-signatures');
        Route::post('projects/{project}/actual-procurements', [ActualProcurementController::class, 'store'])
            ->name('projects.actual-procurements.store');
        Route::delete('projects/{project}/actual-procurements/{actualProcurement}', [ActualProcurementController::class, 'destroy'])
            ->name('projects.actual-procurements.destroy');
        Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])
            ->name('projects.tasks.store');
        Route::put('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])
            ->name('projects.tasks.update');
        Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])
            ->name('projects.tasks.destroy');
        Route::get('surveys', [OperationalSurveyController::class, 'index'])->name('surveys.index');
        Route::get('surveys/{survey}', [OperationalSurveyController::class, 'show'])->name('surveys.show');
        Route::post('surveys/{survey}/brief', [OperationalSurveyController::class, 'brief'])->name('surveys.brief');
        Route::patch('surveys/{survey}/team', [OperationalSurveyController::class, 'updateTeam'])->name('surveys.team');
        Route::post('surveys/{survey}/verify', [OperationalSurveyController::class, 'verify'])->name('surveys.verify');
        Route::post('surveys/{survey}/cancel', [OperationalSurveyController::class, 'cancel'])->name('surveys.cancel');
        Route::get('sales-orders/{salesOrder}/delivery-notes', [DeliveryNoteController::class, 'index'])
            ->name('sales-orders.delivery-notes.index');
        Route::get('sales-orders/{salesOrder}/delivery-notes/create', [DeliveryNoteController::class, 'create'])
            ->name('sales-orders.delivery-notes.create');
        Route::post('sales-orders/{salesOrder}/delivery-notes', [DeliveryNoteController::class, 'store'])
            ->name('sales-orders.delivery-notes.store');
        Route::get('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show'])
            ->name('delivery-notes.show');
        Route::get('delivery-notes/{deliveryNote}/pdf', [DeliveryNoteController::class, 'pdf'])
            ->name('delivery-notes.pdf');
        Route::post('delivery-notes/{deliveryNote}/received-proof', [DeliveryNoteController::class, 'uploadReceivedProof'])
            ->name('delivery-notes.received-proof');
        Route::resource('projects', ProjectController::class)->only(['index', 'show']);
    });

    Route::prefix('sales')->name('sales.')->middleware('role:sales')->group(function () {
        Route::resource('contacts', ContactController::class);
        Route::post('contacts/{contact}/merge', [ContactController::class, 'merge'])->name('contacts.merge');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::post('leads/{lead}/submit-procurement', SubmitProcurementRequestController::class)
            ->name('leads.submit-procurement');
        Route::post('leads/{lead}/submit-addendum', SubmitAddendumController::class)
            ->name('leads.submit-addendum');
        Route::post('leads/{lead}/requirements', [RequirementController::class, 'store'])
            ->name('leads.requirements.store');
        Route::put('leads/{lead}/requirements/{requirement}', [RequirementController::class, 'update'])
            ->name('leads.requirements.update');
        Route::delete('leads/{lead}/requirements/{requirement}', [RequirementController::class, 'destroy'])
            ->name('leads.requirements.destroy');
        Route::post('leads/{lead}/surveys', [LeadSurveyController::class, 'store'])
            ->name('leads.surveys.store');
        Route::post('leads/{lead}/surveys/{survey}/finalize', [LeadSurveyController::class, 'finalize'])
            ->name('leads.surveys.finalize');
        Route::post('leads/{lead}/surveys/{survey}/cancel', [LeadSurveyController::class, 'cancel'])
            ->name('leads.surveys.cancel');
        Route::post('leads/{lead}/meetings', [MeetingController::class, 'store'])
            ->name('leads.meetings.store');
        Route::put('leads/{lead}/meetings/{meeting}', [MeetingController::class, 'update'])
            ->name('leads.meetings.update');
        Route::delete('leads/{lead}/meetings/{meeting}', [MeetingController::class, 'destroy'])
            ->name('leads.meetings.destroy');
        Route::get('reports/leads', LeadReportController::class)->name('reports.leads');
        Route::post('quotations/{quotation}/send-whatsapp', [QuotationController::class, 'sendWhatsapp'])
            ->name('quotations.send-whatsapp');
        Route::patch('quotations/{quotation}/number', [QuotationController::class, 'updateNumber'])
            ->name('quotations.number.update');
        Route::get('procurement-requests/{procurementRequest}/quotations/create', [QuotationController::class, 'create'])
            ->name('procurement-requests.quotations.create');
        Route::post('procurement-requests/{procurementRequest}/quotations', [QuotationController::class, 'store'])
            ->name('procurement-requests.quotations.store');
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])
            ->name('quotations.send');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])
            ->name('quotations.reject');
        Route::post('quotations/{quotation}/revisions', QuotationRevisionController::class)
            ->name('quotations.revisions.store');
        Route::get('quotations/{quotation}/confirm', [QuotationConfirmationController::class, 'create'])
            ->name('quotations.confirm.create');
        Route::post('quotations/{quotation}/confirm', [QuotationConfirmationController::class, 'store'])
            ->name('quotations.confirm.store');
        Route::resource('quotations', QuotationController::class)->except(['create', 'store']);
        Route::post('sales-orders/{salesOrder}/close-won', [SalesOrderController::class, 'closeAsWon'])
            ->name('sales-orders.close-won');
        Route::post('sales-orders/{salesOrder}/documents', [SalesOrderController::class, 'saveApprovalDocuments'])
            ->name('sales-orders.documents');
        Route::resource('sales-orders', SalesOrderController::class)->only(['index', 'show']);
        Route::resource('leads', LeadController::class);
    });

   
    Route::prefix('sales')->name('sales.')->middleware('role:sales,management,project_manager')->group(function () {
        Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])
            ->name('quotations.print');
        Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])
            ->name('quotations.pdf');
    });
});

// Unduhan invoice untuk customer via tautan bertanda tangan (tanpa login).
Route::get('invoice/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
    ->name('invoices.pdf.public')
    ->middleware('signed');

// Unduhan quotation untuk customer via tautan bertanda tangan (tanpa login).
Route::get('quotation/{quotation}/pdf', [QuotationController::class, 'downloadPdf'])
    ->name('quotations.pdf.public')
    ->middleware('signed');
