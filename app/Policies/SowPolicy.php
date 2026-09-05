<?php

namespace App\Policies;

use App\Enums\SowStatus;
use App\Models\Sow;
use App\Models\User;

class SowPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperational($user) || $this->isHr($user)
            || $this->isTechnician($user) || $this->isVendor($user) || $this->isManagement($user);
    }

    public function view(User $user, Sow $sow): bool
    {
        if ($this->isOperational($user) || $this->isHr($user) || $this->isManagement($user)) {
            return true;
        }

        if ($this->isTechnician($user)) {
            return $sow->technician_id === $user->id;
        }

        if ($this->isVendor($user)) {
            return $sow->project->vendor_id === $user->vendor_id;
        }

        return false;
    }

    /** Kirim SOW ke HR untuk direview. */
    public function submit(User $user, Sow $sow): bool
    {
        return $this->isOperational($user)
            && in_array($sow->status, [SowStatus::Draft->value, SowStatus::RejectedByHr->value], true);
    }

    /** HR menyetujui/menolak isi SOW sebelum diteruskan ke Teknisi. */
    public function reviewContentAsHr(User $user, Sow $sow): bool
    {
        return $this->isHr($user) && $sow->status === SowStatus::PendingHrReview->value;
    }

    /** Teknisi yang ditunjuk menandatangani SOW. */
    public function signAsTechnician(User $user, Sow $sow): bool
    {
        return $this->isTechnician($user)
            && $sow->technician_id === $user->id
            && $sow->status === SowStatus::PendingTechnicianSignature->value;
    }

    /** PIC vendor (akun vendor yang terhubung ke project ini) menandatangani SOW. */
    public function signAsVendor(User $user, Sow $sow): bool
    {
        return $this->isVendor($user)
            && $sow->project->vendor_id === $user->vendor_id
            && $sow->status === SowStatus::PendingVendorSignature->value;
    }

    /** HR memverifikasi tanda tangan Teknisi & PIC Vendor sebelum lanjut ke Admin Project. */
    public function verifySignatureAsHr(User $user, Sow $sow): bool
    {
        return $this->isHr($user) && $sow->status === SowStatus::PendingHrVerification->value;
    }

    /** Admin Project (Operasional) menandatangani SOW. */
    public function signAsAdmin(User $user, Sow $sow): bool
    {
        return $this->isOperational($user) && $sow->status === SowStatus::PendingAdminSignature->value;
    }

    /** Direktur (Manager) menandatangani SOW — tahap akhir. */
    public function signAsDirector(User $user, Sow $sow): bool
    {
        return $this->isManagement($user) && $sow->status === SowStatus::PendingDirectorSignature->value;
    }

    /** Operasional mengulang proses tanda tangan Teknisi & PIC Vendor setelah ditolak HR. */
    public function restartSignatures(User $user, Sow $sow): bool
    {
        return $this->isOperational($user) && $sow->status === SowStatus::RejectedSignature->value;
    }

    private function isOperational(User $user): bool
    {
        return $user->role === 'operational' && $user->is_active;
    }

    private function isHr(User $user): bool
    {
        return $user->role === 'hr' && $user->is_active;
    }

    private function isTechnician(User $user): bool
    {
        return $user->role === 'technician' && $user->is_active;
    }

    private function isVendor(User $user): bool
    {
        return $user->role === 'vendor' && $user->is_active;
    }

    private function isManagement(User $user): bool
    {
        return $user->role === 'management' && $user->is_active;
    }
}
