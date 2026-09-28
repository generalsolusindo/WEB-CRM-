<?php

namespace App\Actions\Sales;

use App\Contracts\ConversionSheetWriter;
use App\Models\Quotation;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Kirim satu baris ke Google Sheet "Google Ads Conversion Tracker" — dibaca Google
 * Ads sebagai offline conversion yang dicocokkan lewat nomor telepon customer.
 * Sengaja bisa dipanggil dari status Quotation apa pun (termasuk Draft), karena yang
 * mau ditandai di sini adalah momen leads/quotation MASUK, bukan cuma closing.
 */
class PushQuotationToConversionSheet
{
    public function __construct(private ConversionSheetWriter $writer) {}

    public function handle(Quotation $quotation): void
    {
        $quotation->loadMissing(['contact', 'lines']);
        $contact = $quotation->contact;

        if (! $contact || ! trim((string) $contact->phone)) {
            throw ValidationException::withMessages([
                'conversion' => 'Kontak quotation ini belum punya nomor telepon, tidak bisa dikirim ke spreadsheet.',
            ]);
        }

        $conversionValue = (float) $quotation->lines->sum('subtotal');

        $row = [
            $this->normalizePhone($contact->phone),
            $contact->company_name ?: "{$contact->name} (perorangan)",
            now()->format('Y-m-d H:i:sP'),
            $conversionValue,
            'IDR',
        ];

        try {
            $this->writer->appendRow($row);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'conversion' => 'Gagal mengirim ke spreadsheet: '.$e->getMessage(),
            ]);
        }

        $quotation->update(['pushed_to_conversion_sheet_at' => now()]);
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            throw new RuntimeException('Nomor telepon tidak valid.');
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return '+'.$digits;
    }
}
