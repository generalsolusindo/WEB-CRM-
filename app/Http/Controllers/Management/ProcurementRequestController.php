<?php

namespace App\Http\Controllers\Management;

use App\Enums\ProcurementRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ProcurementRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Monitoring read-only untuk Management — tidak ada aksi/detail, cuma daftar lengkap. */
class ProcurementRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProcurementRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ProcurementRequestStatus::class)],
        ]);

        $requests = ProcurementRequest::query()
            ->with(['lead.contact:id,name,company_name'])
            ->withCount('lines')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $requests->through(fn (ProcurementRequest $pr) => [
            'id' => $pr->id,
            'number' => 'PR-'.str_pad((string) $pr->id, 6, '0', STR_PAD_LEFT),
            'customer' => $pr->lead?->contact?->name,
            'company' => $pr->lead?->contact?->company_name,
            'lines_count' => $pr->lines_count,
            'status' => $pr->status,
            'created_at' => $pr->created_at?->format('Y-m-d'),
        ]);

        return Inertia::render('Management/ProcurementRequests/Index', [
            'requests' => $requests,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statusOptions' => ProcurementRequestStatus::options(),
        ]);
    }
}
