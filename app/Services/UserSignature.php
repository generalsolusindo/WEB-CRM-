<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Tanda tangan digital milik satu user tertentu (Operasional / Project Manager / Management)
 * untuk slot TTD internal pada dokumen SOW. Gambarnya diupload oleh Administrator atas nama
 * orang yang bersangkutan — biasanya diterima dalam bentuk foto/scan lewat WhatsApp — lalu
 * dipakai otomatis saat orang itu sendiri yang menandatangani SOW, tanpa perlu digambar ulang
 * setiap kali. Beda dengan Teknisi dan PIC Vendor, yang tanda tangannya digambar langsung di
 * layar tiap kali menandatangani (lihat SignaturePad).
 */
class UserSignature
{
    /**
     * Role yang berhak punya tanda tangan tersimpan untuk slot TTD internal SOW
     * ("Operasional" dan "Project Manager"/Management yang mengambil alih tanpa PM).
     *
     * @var list<string>
     */
    public const ELIGIBLE_ROLES = ['operational', 'project_manager', 'management'];

    /** @return string data URL base64 siap ditaruh di kolom signature (mis. "data:image/png;base64,..."). */
    public function dataUrl(User $user): string
    {
        if (! $user->signature_path || ! Storage::disk('local')->exists($user->signature_path)) {
            throw ValidationException::withMessages([
                'signature' => 'Tanda tangan Anda belum diupload. Hubungi Administrator untuk mengirimkan tanda tangan Anda terlebih dahulu.',
            ]);
        }

        $contents = Storage::disk('local')->get($user->signature_path);
        $mime = Storage::disk('local')->mimeType($user->signature_path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode($contents);
    }
}
