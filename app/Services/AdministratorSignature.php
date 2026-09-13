<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Tanda tangan resmi "pihak kita" (CV General Solusindo) — diupload sekali oleh
 * akun Administrator, lalu dipakai otomatis untuk setiap slot TTD internal
 * (Operasional & Project Manager) pada dokumen SOW, tanpa perlu digambar ulang.
 */
class AdministratorSignature
{
    /** @return string data URL base64 siap ditaruh di kolom signature (mis. "data:image/png;base64,..."). */
    public function dataUrl(): string
    {
        $admin = User::query()
            ->where('role', 'administrator')
            ->where('is_active', true)
            ->whereNotNull('signature_path')
            ->first();

        if (! $admin || ! Storage::disk('local')->exists($admin->signature_path)) {
            throw ValidationException::withMessages([
                'signature' => 'Belum ada tanda tangan Administrator yang diupload. Hubungi Administrator untuk mengupload tanda tangan terlebih dahulu.',
            ]);
        }

        $contents = Storage::disk('local')->get($admin->signature_path);
        $mime = Storage::disk('local')->mimeType($admin->signature_path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode($contents);
    }
}
