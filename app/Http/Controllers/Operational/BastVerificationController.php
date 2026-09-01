<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\VerifyBast;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\VerifyBastRequest;
use App\Models\Bast;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BastVerificationController extends Controller
{
    public function update(
        VerifyBastRequest $request,
        Project $project,
        Bast $bast,
        VerifyBast $action,
    ): RedirectResponse {
        Gate::authorize('verifyBast', $project);
        abort_unless($bast->project_id === $project->id, 404);

        if ($request->validated('decision') === 'approve') {
            $action->approve($bast, $request->user());
            $message = 'BAST diverifikasi. Project selesai.';
        } else {
            $action->reject($bast, $request->user(), $request->validated('notes'));
            $message = 'BAST ditolak. Project kembali berjalan untuk perbaikan.';
        }

        return back()->with('success', $message);
    }
}
