<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSales($user) || $this->isManagement($user) || $this->isProjectManager($user);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        if ($this->owns($user, $quotation) || $this->isManagement($user)) {
            return true;
        }

        return $this->isProjectManager($user) && $quotation->lead?->delegated_to === $user->id;
    }

    public function create(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $this->isSales($user)
            && $procurementRequest->status === 'ready'
            && $procurementRequest->lead()->where('sales_id', $user->id)->exists();
    }

    /**
     * Bisa diedit di status apa saja (Draft/Sent/Rejected), selama belum jadi Sales Order
     * dan belum punya revisi yang lebih baru. Edit mereset approval PM/Manager dan status
     * kembali ke Draft karena angkanya berubah dan perlu direview & dikirim ulang.
     */
    public function update(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status !== QuotationStatus::Cancelled->value
            && $quotation->procurementRequest?->status === 'ready'
            && ! $quotation->salesOrder()->exists()
            && ! $quotation->revisions()->exists();
    }

    /**
     * Tambah / ubah / hapus item quotation lewat "Revisi Kebutuhan". Syaratnya sama dengan edit
     * biasa (belum jadi Sales Order, belum ada revisi lebih baru, bukan dibatalkan) — tidak
     * perlu menunggu ditolak PM/Manager dulu. Harga beli item baru/berubah tetap datang dari
     * Procurement (lihat RequestQuotationRecost), bukan diketik Sales.
     */
    public function reviseScope(User $user, Quotation $quotation): bool
    {
        return $this->update($user, $quotation);
    }

    /** Ubah nomor quotation secara manual (mis. menyambung dari sistem lama) — bisa di status apa saja. */
    public function updateNumber(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status !== QuotationStatus::Cancelled->value;
    }

    /** Batalkan rangkaian transaksi yang sudah berjalan tetapi belum punya realisasi Project/pembayaran. */
    public function cancel(User $user, Quotation $quotation): bool
    {
        if (! $this->owns($user, $quotation) || $quotation->status === QuotationStatus::Cancelled->value) {
            return false;
        }

        $rootId = $quotation->parent_quotation_id ?? $quotation->id;
        $chain = Quotation::query()
            ->where(fn ($query) => $query->where('id', $rootId)->orWhere('parent_quotation_id', $rootId));
        $chainIds = (clone $chain)->pluck('id');
        $orders = SalesOrder::query()->whereIn('quotation_id', $chainIds);

        $hasBusinessActivity = (clone $chain)
            ->where(function ($query) {
                $query->where('status', '!=', QuotationStatus::Draft->value)
                    ->orWhereNotNull('whatsapp_sent_at')
                    ->orWhereNotNull('parent_quotation_id');
            })->exists() || (clone $orders)->exists();

        return $hasBusinessActivity
            && ! (clone $orders)->whereIn('status', ['completed', 'won'])->exists()
            && ! (clone $orders)->whereHas('projects')->exists()
            && ! (clone $orders)->whereHas('invoices', fn ($query) => $query
                ->whereIn('status', ['partially_paid', 'paid'])
                ->orWhereHas('payments'))->exists();
    }

    /** Hapus permanen hanya untuk draft awal yang belum menghasilkan aktivitas bisnis. */
    public function delete(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status === QuotationStatus::Draft->value
            && $quotation->whatsapp_sent_at === null
            && $quotation->parent_quotation_id === null
            && ! $quotation->revisions()->exists()
            && ! $quotation->salesOrder()->exists();
    }

    /**
     * Cuma bisa ditandai terkirim selama masih Draft — controller sendiri menegakkan ini
     * (abort_unless status===draft), jadi syaratnya juga wajib dicek eksplisit di sini,
     * bukan cuma numpang lewat update() yang sekarang sudah lebih longgar dari Draft-only.
     * Tanpa ini, tombol "Tandai Terkirim" bisa masih tampil aktif walau quotation sudah
     * berstatus Sent (mis. sudah dikirim duluan lewat WhatsApp), lalu diklik akan gagal
     * dengan error 409 mentah, bukan tombolnya yang hilang dari tampilan.
     */
    public function send(User $user, Quotation $quotation): bool
    {
        return $this->update($user, $quotation)
            && $quotation->status === QuotationStatus::Draft->value
            && $quotation->isFullyApproved();
    }

    /** Kirim tautan PDF quotation ke WhatsApp customer — sekaligus menandai terkirim bila masih draft. */
    public function sendWhatsapp(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->isFullyApproved()
            && in_array($quotation->status, [QuotationStatus::Draft->value, QuotationStatus::Sent->value], true);
    }

    public function reviewAsPm(User $user, Quotation $quotation): bool
    {
        return $this->isProjectManager($user)
            && $quotation->lead?->delegated_to === $user->id
            && $quotation->procurementRequest?->status === 'ready'
            && $quotation->quoted_at !== null
            && $quotation->status === QuotationStatus::Draft->value
            && $quotation->pm_review_status === null;
    }

    public function reviewAsManager(User $user, Quotation $quotation): bool
    {
        return $this->isManagement($user)
            && $quotation->procurementRequest?->status === 'ready'
            && $quotation->quoted_at !== null
            && $quotation->status === QuotationStatus::Draft->value
            && $quotation->pm_review_status === 'approved'
            && $quotation->manager_review_status === null;
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && in_array($quotation->status, [
                QuotationStatus::Sent->value,
                QuotationStatus::Rejected->value,
            ], true)
            && ! $quotation->revisions()->exists()
            && ! $quotation->salesOrder()->exists();
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status === QuotationStatus::Sent->value
            && ! $quotation->salesOrder()->exists();
    }

    public function confirm(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status === QuotationStatus::Sent->value
            && ! $quotation->salesOrder()->exists();
    }

    private function owns(User $user, Quotation $quotation): bool
    {
        return $this->isSales($user) && $quotation->sales_id === $user->id;
    }

    private function isSales(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
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
