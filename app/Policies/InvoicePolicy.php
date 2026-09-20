<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinance($user) || $this->isManagement($user);
    }

    /** Management cuma boleh lihat (termasuk PDF), tidak ada tombol aksi apa pun untuk mereka. */
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user) || $this->isManagement($user);
    }

    public function create(User $user): bool
    {
        return $this->isFinance($user);
    }

    /** Ubah nomor invoice secara manual (mis. menyambung dari sistem lama) — bisa di status apa saja. */
    public function updateNumber(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && $invoice->status !== InvoiceStatus::Cancelled->value;
    }

    /**
     * Edit baris invoice (nama, kategori, qty, harga, diskon, pajak), jatuh tempo & catatan.
     * Phase (DP/Full/Final) tidak ikut berubah karena dipakai perhitungan invoice pelunasan lain.
     * Dikunci begitu ada pembayaran tercatat, supaya tidak mismatch dengan uang yang sudah masuk.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && ! $invoice->isSurvey()
            && $invoice->status !== InvoiceStatus::Cancelled->value
            && ! $invoice->payments()->exists();
    }

    /**
     * Ubah jatuh tempo & catatan saja — boleh kapan saja selama belum Cancelled, TERMASUK
     * setelah ada pembayaran tercatat. Dua field ini tidak memengaruhi nominal/pajak yang
     * sudah dibayar, jadi aman diedit; beda dengan update() yang mengunci rincian baris &
     * nominal begitu uang sudah masuk supaya tidak mismatch dengan pembayaran yang tercatat.
     */
    public function updateMeta(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && ! $invoice->isSurvey()
            && $invoice->status !== InvoiceStatus::Cancelled->value;
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && $invoice->status === InvoiceStatus::Draft->value;
    }

    /** Kirim / kirim ulang PDF invoice ke customer (WhatsApp). */
    public function sendWhatsapp(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && $invoice->status !== InvoiceStatus::Cancelled->value;
    }

    /** Ubah rate PPh 23 & catat bukti potong. */
    public function managePph23(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && ! $invoice->isSurvey()
            && $invoice->status !== InvoiceStatus::Cancelled->value;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        if (! $this->isFinance($user)) {
            return false;
        }

        // Invoice survey: single-line, tanpa logika project/settlement — Finance boleh void
        // walau ada pembayaran sebagian (refund uang ditangani manual di luar sistem).
        if ($invoice->isSurvey()) {
            return in_array($invoice->status, [
                InvoiceStatus::Draft->value,
                InvoiceStatus::Sent->value,
                InvoiceStatus::PartiallyPaid->value,
            ], true);
        }

        return in_array($invoice->status, [
            InvoiceStatus::Draft->value,
            InvoiceStatus::Sent->value,
        ], true)
            && ! $invoice->payments()->exists();
    }

    private function isFinance(User $user): bool
    {
        return $user->role === 'finance' && $user->is_active;
    }

    /** Management cuma boleh lihat daftar (monitoring read-only), tidak bisa buka detail/aksi. */
    private function isManagement(User $user): bool
    {
        return $user->role === 'management' && $user->is_active;
    }
}
