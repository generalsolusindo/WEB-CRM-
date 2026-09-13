<?php

namespace App\Http\Controllers\ProjectManager;

use App\Actions\Sow\SignSow;
use App\Enums\SowStatus;
use App\Http\Controllers\Concerns\BuildsSowReview;
use App\Http\Controllers\Controller;
use App\Models\Sow;
use App\Services\AdministratorSignature;
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
            ->where('status', SowStatus::PendingDirectorSignature->value)
            ->whereHas('project', fn ($q) => $q->where('delegated_to', request()->user()->id))
            ->with('project.salesOrder.contact:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $sows->through(fn (Sow $sow) => $this->sowRow($sow));

        return Inertia::render('ProjectManager/Sows/Index', ['sows' => $sows]);
    }

    public function show(Sow $sow): Response
    {
        Gate::authorize('view', $sow);

        return Inertia::render('Sows/Sign/Show', [
            'sow' => $this->sowDetail($sow),
            'canSign' => request()->user()->can('signAsDirector', $sow),
            'autoSign' => true,
            'signUrl' => "/project-manager/sows/{$sow->id}/sign",
            'roleLabel' => 'Project Manager',
            'backHref' => '/project-manager/sows',
        ]);
    }

    public function sign(Sow $sow, SignSow $action, AdministratorSignature $administratorSignature): RedirectResponse
    {
        Gate::authorize('signAsDirector', $sow);

        $action->handle($sow, request()->user(), 'director', $administratorSignature->dataUrl());

        return redirect()->route('project-manager.sows.index')->with('success', 'SOW berhasil ditanda tangani — dokumen selesai.');
    }
}
