<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadSignatureRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SignatureController extends Controller
{
    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('Admin/Signature', [
            'signatureUrl' => $user->signature_path
                ? Storage::disk('local')->temporaryUrl($user->signature_path, now()->addDay())
                : null,
        ]);
    }

    public function update(UploadSignatureRequest $request): RedirectResponse
    {
        $user = $request->user();
        $old = $user->signature_path;

        $user->update([
            'signature_path' => $request->file('signature')->store('administrator-signatures'),
        ]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'Tanda tangan berhasil disimpan. Dipakai otomatis untuk semua SOW berikutnya.');
    }
}
