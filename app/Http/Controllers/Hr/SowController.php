<?php

namespace App\Http\Controllers\Hr;

use App\Actions\Hr\ReviewSowContent;
use App\Actions\Hr\VerifySowSignatures;
use App\Enums\SowStatus;
use App\Http\Controllers\Concerns\BuildsSowReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ReviewSowContentRequest;
use App\Http\Requests\Hr\VerifySowSignatureRequest;
use App\Models\Sow;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SowController extends Controller
{
    use BuildsSowReview;

    public function index(): Response
    {
        Gate::authorize('viewAny', Sow::class);

        $sows = Sow::query()
            ->whereIn('status', [SowStatus::PendingHrReview->value, SowStatus::PendingHrVerification->value])
            ->with('project.salesOrder.contact:id,name')
            ->latest('submitted_at')
            ->paginate(15)
            ->withQueryString();

        $sows->through(fn (Sow $sow) => $this->sowRow($sow));

        return Inertia::render('Hr/Sows/Index', ['sows' => $sows]);
    }

    public function show(Sow $sow): Response
    {
        Gate::authorize('view', $sow);

        return Inertia::render('Hr/Sows/Show', [
            'sow' => $this->sowDetail($sow),
            'technicianKtp' => $this->technicianKtp($sow),
            'canReview' => request()->user()->can('reviewContentAsHr', $sow),
            'canVerifySignatures' => request()->user()->can('verifySignatureAsHr', $sow),
        ]);
    }

    /** NIK + tautan KTP teknisi pelaksana — khusus HR (data sensitif, tidak ikut payload SOW yang dipakai role lain). */
    private function technicianKtp(Sow $sow): ?array
    {
        // Relasi $sow->technician di payload umum hanya memuat id/nama/telepon (tanpa NIK), jadi ambil sendiri.
        $technician = $sow->technician_id ? User::find($sow->technician_id) : null;
        if (! $technician) {
            return null;
        }

        $document = $technician->ktpDocument();

        return [
            'nik' => $technician->nik,
            'url' => $document ? Storage::disk('local')->temporaryUrl($document->file_path, now()->addHour()) : null,
        ];
    }

    public function review(ReviewSowContentRequest $request, Sow $sow, ReviewSowContent $action): RedirectResponse
    {
        $action->handle($sow, $request->user(), $request->boolean('approved'), $request->input('notes'));

        return redirect()->route('hr.sows.index')
            ->with('success', $request->boolean('approved') ? 'SOW disetujui, diteruskan ke Teknisi.' : 'SOW dikembalikan ke Operasional.');
    }

    public function verifySignatures(VerifySowSignatureRequest $request, Sow $sow, VerifySowSignatures $action): RedirectResponse
    {
        $action->handle($sow, $request->user(), $request->boolean('approved'), $request->input('notes'));

        return redirect()->route('hr.sows.index')
            ->with('success', $request->boolean('approved') ? 'Tanda tangan disetujui, diteruskan ke Admin Project.' : 'Tanda tangan ditolak, dikembalikan ke Operasional.');
    }
}
