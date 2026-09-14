<?php

namespace App\Services;

use App\Models\BastDraft;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Sow;
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
     * Angka mulai bisa disambung dari sistem manual/lama lewat .env
     * (QUOTATION_NUMBER_START_YEAR + QUOTATION_NUMBER_START_SEQUENCE), tanpa
     * perlu tabel/migration. Setelah nomor asli di database melewati angka
     * itu, setting-nya otomatis tidak berpengaruh lagi (self-correcting).
     */
    public function nextQuotationNumber(): string
    {
        return $this->nextSlashSequential(
            Quotation::query()->whereNull('parent_quotation_id'),
            'GS-PN',
            $this->configuredFloor('quotation'),
        );
    }

    public function nextSalesOrderNumber(): string
    {
        return $this->nextSequential(SalesOrder::query(), 'SO');
    }

    /**
     * Format: {urutan}/GS-INV/{MM}/{YYYY} — nomor urut reset tiap tahun.
     *
     * Angka mulai bisa disambung dari sistem manual/lama lewat .env
     * (INVOICE_NUMBER_START_YEAR + INVOICE_NUMBER_START_SEQUENCE), tanpa
     * perlu tabel/migration. Setelah nomor asli di database melewati angka
     * itu, setting-nya otomatis tidak berpengaruh lagi (self-correcting).
     */
    public function nextInvoiceNumber(): string
    {
        return $this->nextSlashSequential(Invoice::query(), 'GS-INV', $this->configuredFloor('invoice'));
    }

    public function nextSurveyInvoiceNumber(): string
    {
        return $this->nextSequential(Invoice::query(), 'SRV');
    }

    /** Format: {urutan}/GS-PP/{MM}/{YYYY} — pengajuan pembayaran pengadaan project. */
    public function nextProcurementPaymentNumber(): string
    {
        return $this->nextSlashSequential(\App\Models\ProcurementPayment::query(), 'GS-PP');
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

    /** Ambil next_sequence dari config/document_numbering.php, hanya kalau tahunnya cocok dengan tahun berjalan. */
    private function configuredFloor(string $documentType): int
    {
        $config = config("document_numbering.{$documentType}");

        if (! $config || (int) ($config['year'] ?? 0) !== now()->year) {
            return 0;
        }

        $nextSequence = (int) ($config['next_sequence'] ?? 0);

        return $nextSequence > 0 ? $nextSequence - 1 : 0;
    }

    private function nextSlashSequential(Builder $query, string $middle, int $floor = 0): string
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

        $sequence = max($lastSequence, $floor) + 1;

        return "{$sequence}/{$middle}/{$month}/{$year}";
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
