<?php

namespace App\Http\Controllers\Procurement;

use App\Actions\Procurement\CancelOutsideVendorNeed;
use App\Actions\Procurement\SaveVendorServicePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\SaveVendorServicePaymentRequest;
use App\Models\Project;
use App\Models\VendorServicePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ProjectVendorServiceController extends Controller
{
    public function save(SaveVendorServicePaymentRequest $request, Project $project, SaveVendorServicePayment $action): RedirectResponse
    {
        $payment = $action->handle($project, $request->user(), $request->validated());

        return back()->with('success', $payment->hasDp()
            ? 'Deal vendor tersimpan. Tugas bayar DP diteruskan ke Finance.'
            : 'Deal vendor tersimpan. Project dilepas ke Operasional, pelunasan dibayar setelah BAST.');
    }

    public function cancelNeed(Project $project, CancelOutsideVendorNeed $action): RedirectResponse
    {
        Gate::authorize('manage', [VendorServicePayment::class, $project]);

        $action->handle($project, request()->user());

        return back()->with('success', 'Ditandai tidak jadi butuh vendor luar. Operasional bisa langsung menyusun tim sendiri.');
    }
}
