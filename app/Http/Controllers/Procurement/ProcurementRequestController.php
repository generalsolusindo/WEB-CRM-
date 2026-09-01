<?php

namespace App\Http\Controllers\Procurement;

use App\Actions\Procurement\MarkProcurementRequestReady;
use App\Actions\Procurement\RejectProcurementRequest;
use App\Actions\Procurement\SaveProcurementRequestLines;
use App\Enums\AvailabilityStatus;
use App\Enums\ProcurementRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\RejectProcurementRequestRequest;
use App\Http\Requests\Procurement\SaveProcurementRequestLinesRequest;
use App\Models\ProcurementRequest;
use App\Models\Tax;
use App\Models\VendorProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProcurementRequest::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProcurementRequestStatus::class)],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        $requests = ProcurementRequest::query()
            ->where('status', '!=', ProcurementRequestStatus::Draft->value)
            ->with(['lead.contact:id,name,company_name'])
            ->withCount('lines')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('id', $search)
                    ->orWhereHas('lead.contact', fn ($contact) => $contact
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("FIELD(status, 'submitted', 'searching', 'ready', 'rejected')")
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Procurement/Requests/Index', [
            'requests' => $requests,
            'filters' => ['search' => $search, 'status' => $filters['status'] ?? ''],
            'statusOptions' => ProcurementRequestStatus::options(),
        ]);
    }

    public function show(ProcurementRequest $procurementRequest): Response
    {
        Gate::authorize('view', $procurementRequest);

        $procurementRequest->load([
            'lead.contact:id,name,company_name,email,phone',
            'requestedBy:id,name',
            'lines' => fn ($query) => $query->orderBy('id'),
            'lines.requirement:id,notes',
            'lines.vendorProduct:id,item_name,price,unit',
        ]);

        return Inertia::render('Procurement/Requests/Show', [
            'procurementRequest' => $procurementRequest,
            'editable' => request()->user()->can('update', $procurementRequest),
            'canStart' => request()->user()->can('start', $procurementRequest),
            'canFinalize' => request()->user()->can('finalize', $procurementRequest),
            'availabilityOptions' => AvailabilityStatus::options(),
            'taxes' => Tax::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate']),
            'catalog' => VendorProduct::query()
                ->where('is_active', true)
                ->with('vendor:id,name')
                ->orderBy('item_name')
                ->get(['id', 'vendor_id', 'item_name', 'price', 'unit', 'category']),
        ]);
    }

    public function start(ProcurementRequest $procurementRequest): RedirectResponse
    {
        Gate::authorize('start', $procurementRequest);

        DB::transaction(function () use ($procurementRequest) {
            $locked = ProcurementRequest::query()->whereKey($procurementRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === ProcurementRequestStatus::Submitted->value, 409);
            $locked->update(['status' => ProcurementRequestStatus::Searching->value]);
        });

        return back()->with('success', 'Procurement Request ditandai sedang dikerjakan.');
    }

    public function saveLines(
        SaveProcurementRequestLinesRequest $request,
        ProcurementRequest $procurementRequest,
        SaveProcurementRequestLines $action,
    ): RedirectResponse {
        Gate::authorize('update', $procurementRequest);
        $action->handle($procurementRequest, $request->validated('lines'));

        return back()->with('success', 'Data sourcing tersimpan.');
    }

    public function ready(
        ProcurementRequest $procurementRequest,
        MarkProcurementRequestReady $action,
    ): RedirectResponse {
        Gate::authorize('finalize', $procurementRequest);
        $action->handle($procurementRequest);

        return back()->with('success', 'Procurement Request ditandai Ready. Sales sudah dinotifikasi.');
    }

    public function reject(
        RejectProcurementRequestRequest $request,
        ProcurementRequest $procurementRequest,
        RejectProcurementRequest $action,
    ): RedirectResponse {
        Gate::authorize('finalize', $procurementRequest);
        $action->handle($procurementRequest, $request->validated('rejection_reason'));

        return redirect()->route('procurement.procurement-requests.index')
            ->with('success', 'Procurement Request ditolak dan dikembalikan ke Sales.');
    }
}
