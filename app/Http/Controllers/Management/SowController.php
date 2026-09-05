<?php

namespace App\Http\Controllers\Management;

use App\Actions\Sow\SignSow;
use App\Http\Controllers\Concerns\BuildsSowReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\SignSowRequest;
use App\Models\Sow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SowController extends Controller
{
    use BuildsSowReview;

    public function index(): Response
    {
        Gate::authorize('viewAny', Sow::class);

        $sows = Sow::query()
            ->where('status', \App\Enums\SowStatus::PendingDirectorSignature->value)
            ->with('project.salesOrder.contact:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sows->through(fn (Sow $sow) => $this->sowRow($sow));

        return Inertia::render('Management/Sows/Index', ['sows' => $sows]);
    }

    public function show(Sow $sow): Response
    {
        Gate::authorize('view', $sow);

        return Inertia::render('Sows/Sign/Show', [
            'sow' => $this->sowDetail($sow),
            'canSign' => request()->user()->can('signAsDirector', $sow),
            'signUrl' => "/management/sows/{$sow->id}/sign",
            'roleLabel' => 'Direktur',
            'backHref' => '/management/sows',
        ]);
    }

    public function sign(SignSowRequest $request, Sow $sow, SignSow $action): RedirectResponse
    {
        $action->handle($sow, $request->user(), 'director', $request->validated('signature'));

        return redirect()->route('management.sows.index')->with('success', 'SOW berhasil ditanda tangani — dokumen selesai.');
    }
}
