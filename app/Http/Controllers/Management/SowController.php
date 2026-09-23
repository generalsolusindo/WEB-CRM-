<?php

namespace App\Http\Controllers\Management;

use App\Actions\Sow\SignSow;
use App\Enums\SowStatus;
use App\Http\Controllers\Concerns\BuildsSowReview;
use App\Http\Controllers\Concerns\NormalizesDateRangeFilter;
use App\Http\Controllers\Controller;
use App\Models\Sow;
use App\Services\UserSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SowController extends Controller
{
    use BuildsSowReview;
    use NormalizesDateRangeFilter;

    /** Antrean tanda tangan Management — hanya project yang belum didelegasikan ke Project Manager mana pun. */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Sow::class);

        $filters = $this->normalizeDateRange($request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $sows = Sow::query()
            ->where('status', SowStatus::PendingDirectorSignature->value)
            ->whereHas('project', fn ($q) => $q->whereNull('delegated_to'))
            ->with('project.salesOrder.contact:id,name')
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sows->through(fn (Sow $sow) => $this->sowRow($sow));

        return Inertia::render('Management/Sows/Index', [
            'sows' => $sows,
            'filters' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
        ]);
    }

    public function show(Sow $sow): Response
    {
        Gate::authorize('view', $sow);

        return Inertia::render('Sows/Sign/Show', [
            'sow' => $this->sowDetail($sow),
            'canSign' => request()->user()->can('signAsDirector', $sow),
            'autoSign' => true,
            'signUrl' => "/management/sows/{$sow->id}/sign",
            'roleLabel' => 'Project Manager',
            'backHref' => '/management/sows',
        ]);
    }

    public function sign(Sow $sow, SignSow $action, UserSignature $userSignature): RedirectResponse
    {
        Gate::authorize('signAsDirector', $sow);

        $action->handle($sow, request()->user(), 'director', $userSignature->dataUrl(request()->user()));

        return redirect()->route('management.sows.index')->with('success', 'SOW berhasil ditanda tangani — dokumen selesai.');
    }
}
