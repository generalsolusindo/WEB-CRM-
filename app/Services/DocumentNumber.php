<?php

namespace App\Services;

use App\Models\BastDraft;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\ProcurementPayment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Sow;
use App\Models\VendorServicePayment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Generates human-readable document numbers.
 *
 *   Quotation   : {urutan}/GS-PN/{MM}/{YYYY}   (sequence resets every year)
 *   Sales Order : SO-{YYYY}-{0001}
 *   Revision    : {parent number}-R{revision_number}   (revision 1 keeps the parent number)
 *
 * The sequence lookup uses lockForUpdate() so it must run inside the
 * surrounding transaction of the calling Action to stay race-safe.
 */
class DocumentNumber
{
    /**
     * Format: {urutan}/GS-PN/{MM}/{YYYY} — nomor urut reset tiap tahun.
     *
     * Selalu lanjut dari nomor tertinggi yang ada di database. Kalau perlu
     * menyambung dari sistem manual/lama, sales tinggal edit nomor di
     * quotation manapun (lewat tombol "Ubah Nomor") — nomor berikutnya
     * otomatis lanjut dari situ karena dihitung dari MAX(nomor) + 1.
     */
    public function nextQuotationNumber(): string
    {
        return $this->nextSlashSequential(
            Quotation::query()->whereNull('parent_quotation_id'),
            'GS-PN',
        );
    }

    public function nextSalesOrderNumber(): string
    {
        return $this->nextSequential(SalesOrder::query(), 'SO');
    }

    /**
     * Format: {urutan}/GS-INV/{MM}/{YYYY} — nomor urut reset tiap tahun.
     *
     * Selalu lanjut dari nomor tertinggi yang ada di database. Kalau perlu
     * menyambung dari sistem manual/lama, finance tinggal edit nomor di
     * invoice manapun (lewat tombol "Ubah Nomor") — nomor berikutnya
     * otomatis lanjut dari situ karena dihitung dari MAX(nomor) + 1.
     */
    public function nextInvoiceNumber(): string
    {
        return $this->nextSlashSequential(Invoice::query(), 'GS-INV');
    }

    public function nextSurveyInvoiceNumber(): string
    {
        return $this->nextSequential(Invoice::query(), 'SRV');
    }

    /** Format: {urutan}/GS-PP/{MM}/{YYYY} — pengajuan pembayaran pengadaan project. */
    public function nextProcurementPaymentNumber(): string
    {
        return $this->nextSlashSequential(ProcurementPayment::query(), 'GS-PP');
    }

    /** Format: {urutan}/GS-VP/{MM}/{YYYY} — pembayaran jasa vendor luar per project. */
    public function nextVendorServicePaymentNumber(): string
    {
        return $this->nextSlashSequential(VendorServicePayment::query(), 'GS-VP');
    }

    /** Format: {urutan}/GS-DO/{MM}/{YYYY} — nomor urut reset tiap tahun. */
    public function nextDeliveryNoteNumber(): string
    {
        return $this->nextSlashSequential(DeliveryNote::query(), 'GS-DO');
    }

    /** Format: {urutan}/GS-SOW/{MM}/{YYYY} — nomor urut reset tiap tahun. */
    public function nextSowNumber(): string
    {
        return $this->nextSlashSequential(Sow::query(), 'GS-SOW');
    }

    /** Format: {urutan}/GS-BAST/{MM}/{YYYY} — contoh: 777/GS-BAST/06/2026, nomor urut reset tiap tahun. */
    public function nextBastNumber(): string
    {
        return $this->nextSlashSequential(BastDraft::query(), 'GS-BAST');
    }

    public function revisionNumber(string $parentNumber, int $revisionNumber): string
    {
        return $revisionNumber <= 1
            ? $parentNumber
            : "{$parentNumber}-R{$revisionNumber}";
    }

    private function nextSlashSequential(Builder $query, string $middle): string
    {
        $now = now();
        $year = $now->year;
        $month = $now->format('m');

        $lastSequence = $query
            ->where('number', 'like', "%/{$middle}/%/{$year}")
            ->lockForUpdate()
            ->pluck('number')
            ->map(fn (string $number) => (int) explode('/', $number)[0])
            ->max() ?? 0;

        return ($lastSequence + 1)."/{$middle}/{$month}/{$year}";
    }

    private function nextSequential(Builder $query, string $prefix): string
    {
        $year = now()->year;
        $fullPrefix = "{$prefix}-{$year}-";

        $lastSequence = $query
            ->where('number', 'like', "{$fullPrefix}%")
            ->lockForUpdate()
            ->pluck('number')
            ->map(fn (string $number) => (int) substr($number, strlen($fullPrefix), 4))
            ->max() ?? 0;

        return $fullPrefix.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
