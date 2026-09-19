<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\DeleteLead;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\LeadTemperature;
use App\Enums\LeadType;
use App\Enums\SurveyDeliveryMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreLeadRequest;
use App\Http\Requests\Sales\UpdateLeadRequest;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Requirement;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(LeadType::class)],
            'stage' => ['nullable', Rule::enum(LeadStage::class)],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));

        $leads = Lead::query()
            ->where('sales_id', $request->user()->id)
            ->with('contact:id,name,company_name,email,phone')
            ->withCount('requirements')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('contact', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['stage'] ?? null, fn ($query, $stage) => $query->where('stage', $stage))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Sales/Leads/Index', [
            'leads' => $leads,
            'filters' => [
                'search' => $search,
                'type' => $filters['type'] ?? '',
                'stage' => $filters['stage'] ?? '',
                'source' => $filters['source'] ?? '',
            ],
            'stageOptions' => LeadStage::options(),
            'sourceOptions' => LeadSource::options(),
            'temperatureOptions' => LeadTemperature::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Lead::class);

        return Inertia::render('Sales/Leads/Form', [
            'contacts' => $this->contactOptions($request),
            'stageOptions' => $this->stageOptions([LeadStage::New]),
            'sourceOptions' => LeadSource::options(),
            'temperatureOptions' => LeadTemperature::options(),
            'selectedContactId' => $request->integer('contact_id') ?: null,
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $lead = Lead::create([
            ...$request->validated(),
            'sales_id' => $request->user()->id,
            'type' => LeadType::Lead->value,
            'stage' => LeadStage::New->value,
        ]);

        return redirect()->route('sales.leads.show', $lead)
            ->with('success', 'Lead berhasil dibuat.');
    }

    public function show(Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $lead->load([
            'contact:id,name,company_name,phone,email,address,npwp',
            'requirements:id,lead_id,item_name,category,description,qty,unit,notes,created_at,submitted_at',
            'meetings' => fn ($query) => $query
                ->select('id', 'lead_id', 'title', 'meeting_date', 'location', 'attendees', 'notes', 'created_at')
                ->orderByDesc('meeting_date'),
            'surveys' => fn ($query) => $query
                ->with([
                    'surveyors:id,name', 'vendor:id,name', 'invoice:id,survey_id,number,status',
                    'report.items' => fn ($q) => $q->orderBy('id'),
                    'report.attachments:id,attachable_type,attachable_id,category,file_path',
                ])
                ->latest(),
            'latestProcurementRequest',
        ]);

        $requirementsLocked = $lead->requirementsLocked();

        $surveys = $lead->surveys->map(function ($survey) use ($requirementsLocked) {
            $report = $survey->report;

            return [
                'id' => $survey->id,
                'code' => $survey->code,
                'site_region' => $survey->site_region,
                'site_address' => $survey->site_address,
                'delivery_mode' => SurveyDeliveryMode::from($survey->delivery_mode)->label(),
                'billable' => $survey->billable,
                'status' => $survey->status,
                'status_label' => SurveyStatus::from($survey->status)->label(),
                'cost' => (float) $survey->cost,
                'surveyor' => $survey->leaderUser()?->name ?? $survey->surveyors->pluck('name')->join(', ') ?: null,
                'vendor' => $survey->vendor?->name,
                'notes' => $survey->notes,
                'invoice_number' => $survey->invoice?->number,
                'invoice_status' => $survey->invoice?->status,
                'can_finalize' => request()->user()->can('finalize', $survey),
                'can_cancel' => request()->user()->can('cancel', $survey),
                'cancel_reason' => $survey->cancel_reason,
                'requirements_locked' => $requirementsLocked,
                'report' => $report && in_array($survey->status, ['verified', 'closed'], true) ? [
                    'summary' => $report->summary,
                    'revision' => $report->revision,
                    'items' => $report->items->map(fn ($i) => [
                        'item_name' => $i->item_name, 'qty' => $i->qty, 'unit' => $i->unit, 'notes' => $i->notes,
                    ]),
                    'attachments' => $report->attachments->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => basename($a->file_path),
                        'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
                    ]),
                ] : null,
                'created_at' => $survey->created_at,
            ];
        });

        $procurementRequest = $lead->procurementRequests()
            ->with('lines:id,procurement_request_id,requirement_id,item_name,description,qty,unit,cost_price,availability_status')
            ->latest()
            ->first();
        $existingQuotation = $procurementRequest?->quotations()
            ->where('sales_id', request()->user()->id)
            ->latest('revision_number')
            ->first(['id', 'status', 'revision_number']);

        $procurementData = $procurementRequest ? [
            'id' => $procurementRequest->id,
            'status' => $procurementRequest->status,
            'rejection_reason' => $procurementRequest->rejection_reason,
            'created_at' => $procurementRequest->created_at,
            'quotation' => $existingQuotation,
            'lines' => $procurementRequest->lines->map(fn ($line) => [
                'id' => $line->id,
                'requirement_id' => $line->requirement_id,
                'item_name' => $line->item_name,
                'description' => $line->description,
                'qty' => $line->qty,
                'unit' => $line->unit,
                'cost_price' => $procurementRequest->status === 'ready' ? $line->cost_price : null,
                'availability_status' => $line->availability_status,
            ]),
        ] : null;

        $hasActiveSalesOrder = $lead->activeSalesOrderForAddendum() !== null;
        $hasNewRequirements = $lead->requirements->whereNull('submitted_at')->isNotEmpty();

        return Inertia::render('Sales/Leads/Show', [
            'lead' => $lead,
            'stageOptions' => LeadStage::options(),
            'sourceOptions' => LeadSource::options(),
            'temperatureOptions' => LeadTemperature::options(),
            'procurementRequest' => $procurementData,
            'requirementsEditable' => $lead->type === LeadType::Opportunity->value
                && (! $lead->requirementsLocked() || $hasActiveSalesOrder),
            'leadEditable' => ! $lead->requirementsLocked(),
            'canSubmitAddendum' => $hasActiveSalesOrder && $hasNewRequirements
                && request()->user()->can('submitAddendum', $lead),
            'hasActiveSalesOrderForAddendum' => $hasActiveSalesOrder,
            'canDelete' => request()->user()->can('delete', $lead),
            'canMarkLost' => request()->user()->can('markLost', $lead),
            'convertBlockReason' => $lead->type === LeadType::Lead->value
                ? $this->convertBlocker($lead)
                : null,
            'meetings' => $lead->meetings,
            'meetingsEditable' => request()->user()->can('create', [Meeting::class, $lead]),
            'surveys' => $surveys,
            'surveyRequestable' => request()->user()->can('create', [Survey::class, $lead]),
            'surveyDeliveryOptions' => SurveyDeliveryMode::options(),
            'unitOptions' => Requirement::UNITS,
        ]);
    }

    public function edit(Request $request, Lead $lead): Response
    {
        Gate::authorize('update', $lead);

        return Inertia::render('Sales/Leads/Form', [
            'lead' => $lead,
            'contacts' => $this->contactOptions($request),
            'stageOptions' => $this->stageOptions(
                $lead->type === LeadType::Opportunity->value
                    ? [LeadStage::Qualified, LeadStage::Requirement]
                    : [LeadStage::New, LeadStage::Qualified],
            ),
            'sourceOptions' => LeadSource::options(),
            'temperatureOptions' => LeadTemperature::options(),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->validated());

        return redirect()->route('sales.leads.show', $lead)
            ->with('success', 'Lead berhasil diperbarui.');
    }

    public function updateTemperature(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('updateTemperature', $lead);

        $data = $request->validate([
            'temperature' => ['required', Rule::enum(LeadTemperature::class)],
        ]);

        $lead->update($data);

        return back()->with('success', 'Status lead berhasil diperbarui.');
    }

    public function markLost(Lead $lead): RedirectResponse
    {
        Gate::authorize('markLost', $lead);

        $lead->update(['stage' => LeadStage::Lost->value]);

        return back()->with('success', 'Lead ditandai gagal dan pipeline telah diperbarui.');
    }

    public function destroy(Lead $lead, DeleteLead $action): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $action->handle($lead);

        return redirect()->route('sales.leads.index')
            ->with('success', 'Lead berhasil dihapus.');
    }

    /**
     * Tandai lead Terkualifikasi sekaligus konversi jadi opportunity dalam satu langkah —
     * sebelumnya Sales harus ubah stage ke Qualified dulu secara terpisah baru bisa convert.
     */
    public function convert(Lead $lead): RedirectResponse
    {
        Gate::authorize('convert', $lead);

        if ($blocker = $this->convertBlocker($lead->loadMissing('contact'))) {
            return back()->with('error', $blocker);
        }

        $lead->update([
            'type' => LeadType::Opportunity->value,
            'stage' => LeadStage::Qualified->value,
        ]);

        return redirect()->route('sales.leads.show', $lead)
            ->with('success', 'Lead berhasil ditandai Terkualifikasi & dikonversi menjadi opportunity.');
    }

    /**
     * Alasan kenapa lead belum boleh dikonversi jadi opportunity,
     * atau null bila sudah memenuhi syarat.
     */
    private function convertBlocker(Lead $lead): ?string
    {
        if ($lead->type === LeadType::Opportunity->value) {
            return 'Lead ini sudah menjadi opportunity.';
        }

        $contact = $lead->contact;

        if (blank($contact?->name) || (blank($contact?->phone) && blank($contact?->email))) {
            return 'Lengkapi kontak: butuh nama dan minimal salah satu dari telepon atau email.';
        }

        return null;
    }

    private function contactOptions(Request $request): array
    {
        return Contact::query()
            ->where('created_by', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'company_name'])
            ->all();
    }

    /**
     * @param  array<int, LeadStage>  $stages
     * @return array<int, array{value: string, label: string}>
     */
    private function stageOptions(array $stages): array
    {
        return array_map(
            fn (LeadStage $stage) => ['value' => $stage->value, 'label' => $stage->label()],
            $stages,
        );
    }
}
