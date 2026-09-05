<?php

namespace App\Http\Controllers\Technician;

use App\Actions\Sow\SignSow;
use App\Http\Controllers\Concerns\BuildsSowReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\SignSowRequest;
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
            ->where('technician_id', $request->user()->id)
            ->with('project.salesOrder.contact:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sows->through(fn (Sow $sow) => $this->sowRow($sow));

        return Inertia::render('Technician/Sows/Index', ['sows' => $sows]);
    }

    public function show(Sow $sow): Response
    {
        Gate::authorize('view', $sow);

        return Inertia::render('Sows/Sign/Show', [
            'sow' => $this->sowDetail($sow),
            'canSign' => request()->user()->can('signAsTechnician', $sow),
            'signUrl' => "/technician/sows/{$sow->id}/sign",
            'roleLabel' => 'Teknisi',
            'backHref' => '/technician/sows',
        ]);
    }

    public function sign(SignSowRequest $request, Sow $sow, SignSow $action): RedirectResponse
    {
        $action->handle($sow, $request->user(), 'technician', $request->validated('signature'));

        return redirect()->route('technician.sows.index')->with('success', 'SOW berhasil ditanda tangani.');
    }
}
