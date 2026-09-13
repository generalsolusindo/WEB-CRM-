<?php

namespace App\Support;

use App\Models\Project;

/**
 * Teks default untuk bagian-bagian SOW yang bersifat baku (boilerplate) — dipakai
 * mengisi field kosong saat SOW pertama kali dibuat untuk sebuah project. Semua
 * tetap bisa diedit bebas oleh Operational di form SOW. Disusun berdasarkan format
 * dokumen SOW yang sudah dipakai perusahaan (contoh: SOW Project PLTS Bajo).
 */
class SowDefaults
{
    public static function responsibilities(): string
    {
        return <<<'TEXT'
        Vendor:
        • Menyediakan teknisi & tools
        • Melaksanakan pekerjaan sesuai standar
        • Menjaga keselamatan kerja

        Client:
        • Memberikan akses lokasi
        • Menyediakan sumber listrik (jika diperlukan)
        • Koordinasi internal site
        TEXT;
    }

    public static function safety(): string
    {
        return <<<'TEXT'
        • Menggunakan APD (helm, sepatu safety, dll)
        • Memastikan area kerja aman
        • Mengikuti prosedur K3 yang berlaku
        TEXT;
    }

    public static function paymentTerms(): string
    {
        return <<<'TEXT'
        • DP dibayarkan H-1 sebelum pekerjaan
        • Pembayaran DP akan dibayarkan setelah SOW telah tertanda tangani menggunakan privy (electronic sign)
        • Pelunasan dibayarkan H+1 dan maksimal H+7 setelah pekerjaan
        • Pelunasan dibayarkan apabila dokumen Report pekerjaan (foto pekerjaan Before After, hasil test, dll) & BAST sudah diserahkan lengkap dengan tanda tangan
        TEXT;
    }

    public static function output(): string
    {
        return <<<'TEXT'
        Teknisi wajib menyerahkan output pekerjaan berupa:
        • Dokumentasi foto (before/after pekerjaan)
        • Perangkat terpasang rapi, kuat, dan terlabeli dengan jelas
        • Hasil pekerjaan teruji sesuai standar yang berlaku
        • Dokumen As-Built Drawing dan Laporan Hasil Ukur (bila berlaku)
        • Dokumentasi foto fisik pekerjaan (dengan timestamp/GPS geotag)
        • Dokumen BAST dan Timesheet kerja yang ditandatangani oleh Para Pihak
        • Report pekerjaan berupa dokumen PDF
        TEXT;
    }

    public static function warranty(): string
    {
        return <<<'TEXT'
        Penyedia jasa/teknisi subcon memberikan garansi layanan pekerjaan selama 1 (satu) bulan sejak pekerjaan dinyatakan selesai dan diterima oleh pihak pengguna jasa. Garansi mencakup perbaikan atau penanganan ulang terhadap kendala yang disebabkan oleh hasil pekerjaan teknisi tanpa dikenakan biaya tambahan layanan. Garansi tidak berlaku untuk kerusakan akibat faktor eksternal, kelalaian pengguna (client), bencana alam, atau modifikasi oleh pihak lain. Garansi ini tidak mencakup biaya transportasi teknisi ke lokasi pengguna jasa, yang sepenuhnya menjadi tanggung jawab pihak pengguna jasa.
        TEXT;
    }

    public static function notes(): string
    {
        return <<<'TEXT'
        • Pekerjaan harus selesai sesuai target
        • Wajib mengikuti standar instalasi jaringan
        • Jaga kerapihan dan keselamatan kerja
        • Seluruh teknisi wajib melakukan absensi sebelum dan sesudah pekerjaan menggunakan aplikasi kamera yang memiliki fitur GPS Map Camera (timestamp lokasi). Aplikasi dapat diunduh melalui Playstore.
        TEXT;
    }

    /** Kalimat penutup — merujuk judul pekerjaan dari BAST bila sudah ada, atau nama project SOW sendiri. */
    public static function closing(Project $project, ?string $sowProjectName): string
    {
        $title = $project->bastDraft?->job_title ?: $sowProjectName;
        $title = $title ?: 'yang tercantum pada dokumen ini';
        $projectNumber = 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT);

        return "Dokumen Scope of Work (SOW) ini disusun sebagai acuan teknis pelaksanaan pekerjaan {$title} pada Project {$projectNumber}.";
    }

    /** Isi default sub-bab pertama "Pengadaan Material", diambil dari data actual procurement project. */
    public static function materialScopeContent(Project $project): string
    {
        $lines = $project->actualProcurements->map(
            fn ($item) => "• {$item->qty} {$item->unit} — {$item->item_name}",
        );

        return $lines->isEmpty()
            ? 'Belum ada data material — akan diperbarui sesuai kebutuhan procurement project ini.'
            : $lines->implode("\n");
    }
}
