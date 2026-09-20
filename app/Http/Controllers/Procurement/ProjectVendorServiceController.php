<?php

namespace App\Http\Controllers\Procurement;

use App\Actions\Procurement\SaveVendorServicePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\SaveVendorServicePaymentRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectVendorServiceController extends Controller
{
    public function save(SaveVendorServicePaymentRequest $request, Project $project, SaveVendorServicePayment $action): RedirectResponse
    {
        $payment = $action->handle($project, $request->user(), $request->validated());

        return back()->with('success', $payment->hasDp()
            ? 'Deal vendor tersimpan. Tugas bayar DP diteruskan ke Finance.'
            : 'Deal vendor tersimpan. Project dilepas ke Operasional, pelunasan dibayar setelah BAST.');
    }
}
