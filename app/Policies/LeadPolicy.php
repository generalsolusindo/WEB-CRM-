<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->role === 'sales' && $user->is_active)
            || $this->isManagement($user)
            || $this->isProjectManager($user);
    }

    /** Sales pemilik; Management lihat semua (monitoring); PM cuma opportunity yang didelegasikan ke dia. */
    public function view(User $user, Lead $lead): bool
    {
        if ($this->owns($user, $lead) || $this->isManagement($user)) {
            return true;
        }

        return $this->isProjectManager($user) && $lead->delegated_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead)
            && ! $lead->procurementRequests()->exists();
    }

    /**
     * Bisa dihapus di tahap apa pun — Requirement, Procurement Request, dan Quotation
     * di bawahnya ikut terhapus sekaligus (cascade) — SELAMA belum ada data transaksi
     * nyata di baliknya: quotation yang sudah jadi Sales Order dengan Invoice/Project,
     * atau survey yang sudah punya invoice sendiri. Begitu salah satunya ada, Lead
     * terkunci permanen dari hapus supaya jejak transaksi/pembayaran tidak hilang.
     */
    public function delete(User $user, Lead $lead): bool
    {
        if (! $this->owns($user, $lead)) {
            return false;
        }

        foreach ($lead->quotations as $quotation) {
            $salesOrder = $quotation->salesOrder;

            if ($salesOrder && ($salesOrder->invoices()->exists() || $salesOrder->projects()->exists())) {
                return false;
            }
        }

        return ! $lead->surveys()->whereHas('invoice')->exists();
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    public function submitToProcurement(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    /** Ajukan tambahan (addendum) — Sales pemilik opportunity, kapan saja setelah Lead ini pernah Won. */
    public function submitAddendum(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    /** Manager menunjuk/mengganti Project Manager untuk sebuah opportunity. */
    public function delegate(User $user, Lead $lead): bool
    {
        return $this->isManagement($user) && $lead->type === 'opportunity';
    }

    private function owns(User $user, Lead $lead): bool
    {
        return $user->role === 'sales'
            && $user->is_active
            && $lead->sales_id === $user->id;
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
