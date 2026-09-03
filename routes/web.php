<?php

use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\SurveyController as FinanceSurveyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Operational\ActualProcurementController;
use App\Http\Controllers\Operational\BastVerificationController;
use App\Http\Controllers\Operational\ProjectChangeRequestController;
use App\Http\Controllers\Operational\ProjectController;
use App\Http\Controllers\Operational\ProjectTaskController;
use App\Http\Controllers\Operational\ProjectTechnicianController;
use App\Http\Controllers\Operational\SurveyController as OperationalSurveyController;
use App\Http\Controllers\Procurement\ProcurementRequestController;
use App\Http\Controllers\Procurement\ProjectProcurementController;
use App\Http\Controllers\Procurement\SurveyController as ProcurementSurveyController;
use App\Http\Controllers\Procurement\TechnicianAccountController;
use App\Http\Controllers\Technician\BastController as TechnicianBastController;
use App\Http\Controllers\Technician\SurveyController as TechnicianSurveyController;
use App\Http\Controllers\Technician\TaskController as TechnicianTaskController;
use App\Http\Controllers\Procurement\VendorController;
use App\Http\Controllers\Procurement\VendorProductController;
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
        Route::resource('taxes', TaxController::class)->except('show');
    });

    Route::prefix('procurement')->name('procurement.')->middleware('role:procurement')->group(function () {
        Route::resource('vendors', VendorController::class);
        Route::resource('technicians', TechnicianAccountController::class)
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
        Route::put('project-procurements/{actualProcurement}', [ProjectProcurementController::class, 'update'])
            ->name('project-procurements.update');
    });

    Route::prefix('finance')->name('finance.')->middleware('role:finance')->group(function () {
        Route::get('sales-orders/{salesOrder}/invoices/create', [InvoiceController::class, 'create'])
            ->name('sales-orders.invoices.create');
        Route::post('sales-orders/{salesOrder}/final-invoice', [InvoiceController::class, 'storeFinal'])
            ->name('sales-orders.final-invoice');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/send-whatsapp', [InvoiceController::class, 'sendWhatsapp'])->name('invoices.send-whatsapp');
        Route::post('invoices/{invoice}/pph23', [InvoiceController::class, 'updatePph23'])->name('invoices.pph23');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('surveys', [FinanceSurveyController::class, 'index'])->name('surveys.index');
        Route::get('surveys/{survey}', [FinanceSurveyController::class, 'show'])->name('surveys.show');
        Route::post('surveys/{survey}/invoice', [FinanceSurveyController::class, 'issueInvoice'])->name('surveys.invoice');
        Route::post('surveys/{survey}/clear', [FinanceSurveyController::class, 'clear'])->name('surveys.clear');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::resource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
    });

    Route::prefix('technician')->name('technician.')->middleware('role:technician')->group(function () {
        Route::get('surveys', [TechnicianSurveyController::class, 'index'])->name('surveys.index');
        Route::get('surveys/{survey}', [TechnicianSurveyController::class, 'show'])->name('surveys.show');
        Route::put('surveys/{survey}/report', [TechnicianSurveyController::class, 'saveReport'])->name('surveys.report.save');
        Route::post('surveys/{survey}/report/submit', [TechnicianSurveyController::class, 'submitReport'])->name('surveys.report.submit');
        Route::post('surveys/{survey}/report/attachments', [TechnicianSurveyController::class, 'uploadAttachment'])->name('surveys.report.attachments');
        Route::delete('surveys/{survey}/report/attachments/{attachment}', [TechnicianSurveyController::class, 'deleteAttachment'])->name('surveys.report.attachments.destroy');
        Route::get('tasks', [TechnicianTaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{task}', [TechnicianTaskController::class, 'show'])->name('tasks.show');
        Route::post('tasks/{task}/status', [TechnicianTaskController::class, 'updateStatus'])->name('tasks.status');
        Route::post('tasks/{task}/photos', [TechnicianTaskController::class, 'uploadPhoto'])->name('tasks.photos');
        Route::get('projects/{project}/bast/create', [TechnicianBastController::class, 'create'])->name('projects.bast.create');
        Route::post('projects/{project}/bast', [TechnicianBastController::class, 'store'])->name('projects.bast.store');
    });

    Route::prefix('operational')->name('operational.')->middleware('role:operational')->group(function () {
        Route::put('projects/{project}/planning', [ProjectController::class, 'planning'])
            ->name('projects.planning');
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
        Route::resource('projects', ProjectController::class)->only(['index', 'show']);
    });

    Route::prefix('sales')->name('sales.')->middleware('role:sales')->group(function () {
        Route::resource('contacts', ContactController::class);
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::post('leads/{lead}/submit-procurement', SubmitProcurementRequestController::class)
            ->name('leads.submit-procurement');
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
        Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])
            ->name('quotations.print');
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
});

// Unduhan invoice untuk customer via tautan bertanda tangan (tanpa login).
Route::get('invoice/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
    ->name('invoices.pdf.public')
    ->middleware('signed');
