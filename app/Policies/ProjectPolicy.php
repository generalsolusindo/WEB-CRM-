<?php

namespace App\Policies;

use App\Enums\ActualProcurementStatus;
use App\Enums\ProjectStatus;
use App\Enums\SowStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\Operational\MaterialDeliveryStatus;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperational($user)
            || $this->isManagement($user)
            || $this->isProjectManager($user);
    }

    /** Operational lihat semua; Management lihat semua (monitoring); PM cuma project yang didelegasikan ke dia. */
    public function view(User $user, Project $project): bool
    {
        if ($this->isOperational($user) || $this->isManagement($user)) {
            return true;
        }

        return $this->isProjectManager($user) && $project->delegated_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isOperational($user);
    }

    /**
     * Planning (set jadwal) hanya selama project belum diproses lebih jauh.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && in_array($project->status, [
                ProjectStatus::Draft->value,
                ProjectStatus::Planning->value,
            ], true);
    }

    /**
     * Kelola sumber daya (actual procurement, penugasan technician) — sebelum project berjalan.
     */
    public function manageResources(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && in_array($project->status, [
                ProjectStatus::Draft->value,
                ProjectStatus::Planning->value,
                ProjectStatus::WaitingResource->value,
            ], true);
    }

    /**
     * Tambah/hapus item pengadaan ekstra — dibuka lebih lebar dari manageResources
     * (yang mengatur penugasan teknisi) karena kebutuhan tambahan material
     * seringkali baru ketahuan justru saat project sudah berjalan di lapangan.
     * Ditutup hanya setelah project benar-benar Selesai.
     */
    public function manageExtraProcurement(User $user, Project $project): bool
    {
        return $this->isOperational($user) && $project->status !== ProjectStatus::Completed->value;
    }

    /**
     * Assign/ubah tim technician — dipisah dari manageResources supaya bisa
     * ditutup untuk project Material Only tanpa mengganggu pengelolaan
     * actual_procurements (yang tetap perlu Operational untuk kedua tipe order).
     */
    public function manageTechnicianTeam(User $user, Project $project): bool
    {
        return $this->manageResources($user, $project)
            && $project->salesOrder?->order_type !== 'material_only';
    }

    /**
     * Kelola daftar task — selama project belum selesai (termasuk saat rework).
     * Tidak berlaku untuk Material Only — tidak ada pekerjaan on-site untuk order jenis ini.
     */
    public function manageTasks(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && $project->status !== ProjectStatus::Completed->value
            && $project->salesOrder?->order_type !== 'material_only';
    }

    public function markReady(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && $project->salesOrder?->order_type !== 'material_only'
            && $this->vendorReleased($project)
            && in_array($project->status, [
                ProjectStatus::Planning->value,
                ProjectStatus::WaitingResource->value,
            ], true);
    }

    public function start(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && $project->salesOrder?->order_type !== 'material_only'
            && $this->vendorReleased($project)
            && $project->status === ProjectStatus::Ready->value;
    }

    /**
     * Verifikasi BAST (approve/reject) — HANYA Operational, hanya saat project menunggu verifikasi.
     */
    public function verifyBast(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && $project->status === ProjectStatus::Verification->value;
    }

    /**
     * Selesaikan project Material Only — tanpa teknisi/task/BAST sama sekali.
     * Cukup begitu semua barang procurement sudah diterima DAN semua baris
     * material Sales Order sudah terkirim penuh (Delivery Note).
     */
    public function completeDirect(User $user, Project $project): bool
    {
        if (! $this->isOperational($user) || $project->salesOrder?->order_type !== 'material_only') {
            return false;
        }

        if (! in_array($project->status, [
            ProjectStatus::WaitingResource->value,
            ProjectStatus::Ready->value,
            ProjectStatus::InProgress->value,
        ], true)) {
            return false;
        }

        if ($project->actualProcurements()->where('status', '!=', ActualProcurementStatus::Received->value)->exists()) {
            return false;
        }

        return MaterialDeliveryStatus::of($project->salesOrder)['is_complete'];
    }

    public function manageChangeRequests(User $user, Project $project): bool
    {
        return $this->isOperational($user);
    }

    /** Absen kehadiran (selfie) — anggota tim teknisi, hanya selama project berjalan. */
    public function checkIn(User $user, Project $project): bool
    {
        return $user->canWorkAsTechnician()
            && $project->status === ProjectStatus::InProgress->value
            && $project->technicians()->where('technician_id', $user->id)->exists();
    }

    /** Absen pulang (selfie) — hanya setelah absen kedatangan dan belum absen pulang. */
    public function checkOut(User $user, Project $project): bool
    {
        return $this->checkIn($user, $project)
            && $project->hasCheckedIn($user)
            && ! $project->hasCheckedOut($user);
    }

    /** Manager mendelegasikan project ke Project Manager (atau menarik delegasinya kembali). */
    public function delegate(User $user, Project $project): bool
    {
        return $this->isManagement($user);
    }

    /** Generate/edit draft cetakan BAST — hanya setelah tim technician ditugaskan. */
    public function manageBastDraft(User $user, Project $project): bool
    {
        return $this->isOperational($user) && $project->technicians()->exists();
    }

    /**
     * Gerbang vendor luar: project yang butuh vendor baru boleh dilanjutkan Operasional
     * (SOW, siap, mulai) setelah dilepas — yaitu DP dibayar Finance, atau termin bayar
     * di akhir. Project yang ditandai butuh vendor tapi deal-nya belum diisi Procurement
     * juga masih tertahan. Project tanpa penanda & tanpa deal (termasuk data lama) tidak terkena.
     */
    public function vendorReleased(Project $project): bool
    {
        $deal = $project->vendorServicePayment;

        return $deal ? $deal->released_at !== null : ! $project->needs_outside_vendor;
    }

    /**
     * Sudah boleh mulai proses vendor (bikin SOW) begitu Procurement mengisi deal —
     * TIDAK perlu menunggu DP dibayar (itu urusan vendorReleased(), dipakai untuk
     * markReady/start: eksekusi teknisi di lapangan). Project yang ditandai butuh
     * vendor tapi deal-nya belum/tidak ada (mis. Procurement belum sempat mengisi,
     * atau kasus data janggal) masih tertahan.
     */
    private function vendorDealFilled(Project $project): bool
    {
        return ! $project->needs_outside_vendor || $project->vendorServicePayment !== null;
    }

    /**
     * Kebalikan dari CancelOutsideVendorNeed: Operasional menandai project yang tadinya
     * disangka cukup dikerjakan tim sendiri ternyata butuh vendor luar. Boleh dipakai
     * kapan pun selama project belum selesai dan belum ada vendor terpasang — TERMASUK
     * kalau tim internal sudah sempat ditugaskan/mulai kerja (sengaja tidak dicegah,
     * project boleh dialihkan ke vendor luar kapan saja sebelum selesai).
     */
    public function flagOutsideVendorNeed(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && $project->status !== ProjectStatus::Completed->value
            && ! $project->needs_outside_vendor
            && $project->vendor_id === null;
    }

    /**
     * Lihat SOW — Operational, kapan saja selama project sudah ditandai pakai vendor.
     * Sengaja TIDAK menunggu DP dibayar (vendorReleased()) — urutannya justru terbalik:
     * SOW yang harus dibuat & ditandatangani lengkap dulu, baru jadi dasar Finance
     * membayar DP (lihat VendorServicePayment::canPayDp()).
     */
    public function viewSow(User $user, Project $project): bool
    {
        return $this->isOperational($user) && $project->vendor_id !== null && $this->vendorDealFilled($project);
    }

    /**
     * Buat/edit SOW — Operational, project harus sudah ditandai pakai vendor.
     * Boleh diedit kapan saja selama belum Completed — termasuk setelah dikirim
     * dan/atau sudah ada tanda tangan yang masuk, supaya kesalahan isi masih bisa
     * diperbaiki. Mengedit di luar status Draft/RejectedByHr me-reset seluruh
     * proses review & tanda tangan (lihat SaveSowDraft) karena tanda tangan yang
     * sudah dikumpulkan hanya sah untuk isi SOW yang mereka tanda tangani.
     * Sama seperti viewSow(), sengaja tidak menunggu DP dibayar.
     */
    public function manageSow(User $user, Project $project): bool
    {
        if (! $this->isOperational($user) || $project->vendor_id === null || ! $this->vendorDealFilled($project)) {
            return false;
        }

        $sow = $project->sow;

        return $sow === null || $sow->status !== SowStatus::Completed->value;
    }

    /** Perubahan langsung yang tidak melewati Simpan Draft hanya aman sebelum proses review dimulai. */
    public function manageSowAssets(User $user, Project $project): bool
    {
        if (! $this->manageSow($user, $project)) {
            return false;
        }

        $status = $project->sow?->status;

        return $status === null || in_array($status, [SowStatus::Draft->value, SowStatus::RejectedByHr->value], true);
    }

    private function isOperational(User $user): bool
    {
        return $user->role === 'operational' && $user->is_active;
    }

    private function isManagement(User $user): bool
    {
        return $user->role === 'management' && $user->is_active;
    }

    private function isProjectManager(User $user): bool
    {
        return $user->role === 'project_manager' && $user->is_active;
    }
}
