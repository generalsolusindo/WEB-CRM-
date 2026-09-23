<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadUserSignatureRequest;
use App\Models\User;
use App\Services\UserSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administrator mengelola tanda tangan digital milik staf Operasional dan Project
 * Manager/Management — dipakai otomatis untuk slot TTD internal pada dokumen SOW.
 * Lihat App\Services\UserSignature untuk kenapa ini diupload oleh Administrator,
 * bukan self-service oleh pemiliknya sendiri.
 */
class UserSignatureController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->whereIn('role', UserSignature::ELIGIBLE_ROLES)
            ->where('is_active', true)
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'signature_path'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'signature_url' => $user->signature_path
                    ? Storage::disk('local')->temporaryUrl($user->signature_path, now()->addDay())
                    : null,
            ])
            ->values();

        return Inertia::render('Admin/UserSignatures', ['users' => $users]);
    }

    public function update(User $user, UploadUserSignatureRequest $request): RedirectResponse
    {
        $old = $user->signature_path;

        $user->update([
            'signature_path' => $request->file('signature')->store('user-signatures'),
        ]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', "Tanda tangan {$user->name} berhasil disimpan.");
    }
}
