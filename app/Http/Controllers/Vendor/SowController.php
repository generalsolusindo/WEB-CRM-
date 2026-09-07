<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\Sow\SignSow;
use App\Http\Controllers\Concerns\BuildsSowReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\SignSowRequest;
use App\Models\Sow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SowController extends Controller
{
    use BuildsSowReview;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Sow::class);

        $sows = Sow::query()
            ->whereHas('project', fn ($q) => $q->where('vendor_id', $request->user()->vendor_id))
            ->whereIn('status', \App\Enums\SowStatus::visibleToVendorValues())
            ->with('project.salesOrder.contact:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sows->through(fn (Sow $sow) => $this->sowRow($sow));

        return Inertia::render('Vendor/Sows/Index', ['sows' => $sows]);
    }

    public function show(Sow $sow): Response
    {
        Gate::authorize('view', $sow);

        return Inertia::render('Sows/Sign/Show', [
            'sow' => $this->sowDetail($sow),
            'canSign' => request()->user()->can('signAsVendor', $sow),
            'signUrl' => "/vendor/sows/{$sow->id}/sign",
            'roleLabel' => 'PIC Vendor',
            'backHref' => '/vendor/sows',
        ]);
    }

    public function sign(SignSowRequest $request, Sow $sow, SignSow $action): RedirectResponse
    {
        $action->handle($sow, $request->user(), 'vendor', $request->validated('signature'));

        return redirect()->route('vendor.sows.index')->with('success', 'SOW berhasil ditanda tangani.');
    }
}
