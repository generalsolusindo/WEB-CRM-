<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

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
     * Kelola daftar task — selama project belum selesai (termasuk saat rework).
     */
    public function manageTasks(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && $project->status !== ProjectStatus::Completed->value;
    }

    public function markReady(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && in_array($project->status, [
                ProjectStatus::Planning->value,
                ProjectStatus::WaitingResource->value,
            ], true);
    }

    public function start(User $user, Project $project): bool
    {
        return $this->isOperational($user)
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
     * Selesaikan langsung tanpa BAST — sementara hanya untuk order Material Only.
     */
    public function completeDirect(User $user, Project $project): bool
    {
        return $this->isOperational($user)
            && $project->status === ProjectStatus::InProgress->value
            && $project->salesOrder?->order_type === 'material_only';
    }

    public function manageChangeRequests(User $user, Project $project): bool
    {
        return $this->isOperational($user);
    }

    /** Absen kehadiran (selfie) — anggota tim teknisi, hanya selama project berjalan. */
    public function checkIn(User $user, Project $project): bool
    {
        return $user->role === 'technician'
            && $user->is_active
            && $project->status === ProjectStatus::InProgress->value
            && $project->technicians()->where('technician_id', $user->id)->exists();
    }

    /** Manager mendelegasikan project ke Project Manager (atau menarik delegasinya kembali). */
    public function delegate(User $user, Project $project): bool
    {
        return $this->isManagement($user);
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
