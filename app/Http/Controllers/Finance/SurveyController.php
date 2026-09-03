<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\AdvanceSurveyToOperational;
use App\Actions\Finance\CreateSurveyInvoice;
use App\Enums\SurveyDeliveryMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ClearSurveyFinanceRequest;
use App\Http\Requests\Finance\IssueSurveyInvoiceRequest;
use App\Models\Survey;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAnyFinance', Survey::class);

        $surveys = Survey::query()
            ->whereIn('status', [
                SurveyStatus::FinanceReview->value,
                SurveyStatus::AwaitingPayment->value,
            ])
            ->with(['lead.contact:id,name,company_name', 'invoice:id,survey_id,number,status'])
            ->latest()
            ->paginate(12)
            ->through(fn (Survey $survey) => [
                'id' => $survey->id,
                'code' => $survey->code,
                'customer' => $survey->lead->contact?->name,
                'site_region' => $survey->site_region,
                'delivery_mode' => SurveyDeliveryMode::from($survey->delivery_mode)->label(),
                'billable' => $survey->billable,
                'cost' => (float) $survey->cost,
                'status' => $survey->status,
                'status_label' => SurveyStatus::from($survey->status)->label(),
                'invoice_number' => $survey->invoice?->number,
            ]);

        return Inertia::render('Finance/Surveys/Index', ['surveys' => $surveys]);
    }

    public function show(Survey $survey): Response
    {
        Gate::authorize('viewFinance', $survey);

        $survey->load([
            'lead.contact:id,name,company_name,email,phone,address,npwp',
            'requestedBy:id,name',
            'surveyors:id,name',
            'vendor:id,name',
            'invoice.lines',
            'invoice.payments' => fn ($query) => $query
                ->with('attachments:id,attachable_type,attachable_id,category,file_path')
                ->orderByDesc('paid_at'),
        ]);

        $invoice = $survey->invoice;
        $payments = $invoice
            ? $invoice->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount_paid' => $payment->amount_paid,
                'paid_at' => $payment->paid_at,
                'notes' => $payment->notes,
                'proofs' => $payment->attachments
                    ->where('category', 'payment_proof')
                    ->map(fn ($a) => ['id' => $a->id, 'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay())])
                    ->values(),
            ])
            : [];

        return Inertia::render('Finance/Surveys/Show', [
            'survey' => [
                ...$survey->makeHidden(['invoice'])->toArray(),
                'status_label' => SurveyStatus::from($survey->status)->label(),
                'delivery_mode_label' => SurveyDeliveryMode::from($survey->delivery_mode)->label(),
                'needs_finance' => $survey->needsFinance(),
            ],
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'amount' => (float) $invoice->amount,
                'tax_amount' => (float) $invoice->tax_amount,
                'grand_total' => $invoice->grandTotal(),
                'tax_label' => $invoice->lines->first()?->tax_rate > 0
                    ? 'PPN '.rtrim(rtrim(number_format((float) $invoice->lines->first()->tax_rate, 2), '0'), '.').'%'
                    : 'Tanpa PPN',
                'due_date' => $invoice->due_date,
                'total_paid' => (float) $invoice->payments->sum('amount_paid'),
            ] : null,
            'payments' => $payments,
            'taxes' => Tax::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate']),
            'canHandle' => request()->user()->can('handleFinance', $survey),
            'canVoidInvoice' => $invoice ? request()->user()->can('cancel', $invoice) : false,
        ]);
    }

    public function issueInvoice(IssueSurveyInvoiceRequest $request, Survey $survey, CreateSurveyInvoice $action): RedirectResponse
    {
        $action->handle(
            $survey,
            $request->user(),
            $request->validated('due_date'),
            $request->validated('tax_id'),
            $request->validated('finance_note'),
        );

        return redirect()->route('finance.surveys.show', $survey)
            ->with('success', 'Invoice survey diterbitkan. Menunggu pembayaran customer.');
    }

    public function clear(ClearSurveyFinanceRequest $request, Survey $survey, AdvanceSurveyToOperational $action): RedirectResponse
    {
        abort_if($survey->billable, 422, 'Survey billable harus lewat invoice.');

        $action->handle($survey, $request->user(), $request->validated('finance_note'));

        return redirect()->route('finance.surveys.index')
            ->with('success', 'Biaya survey dicatat. Survey diteruskan ke Operasional.');
    }
}
