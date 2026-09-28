<?php

namespace App\Services\GoogleAds;

use App\Contracts\ConversionSheetWriter;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use RuntimeException;

/**
 * Nulis satu baris ke Google Sheet "Google Ads Conversion Tracker" lewat Service
 * Account (bukan login OAuth pengguna) — dibaca Google Ads sebagai offline conversion
 * import yang dicocokkan lewat nomor telepon (bukan gclid).
 */
class GoogleSheetsConversionWriter implements ConversionSheetWriter
{
    public function appendRow(array $row): void
    {
        $credentialsPath = config('services.google_sheets.credentials_path');
        $spreadsheetId = config('services.google_sheets.conversion_spreadsheet_id');
        $sheetName = config('services.google_sheets.conversion_sheet_name');

        // Path relatif dihitung dari folder proyek, bukan folder kerja PHP (di web server beda).
        if ($credentialsPath && ! str_starts_with($credentialsPath, '/')) {
            $credentialsPath = base_path($credentialsPath);
        }

        if (! $credentialsPath || ! is_file($credentialsPath)) {
            throw new RuntimeException('File kredensial Google Sheets belum diatur/ditemukan.');
        }

        if (! $spreadsheetId) {
            throw new RuntimeException('ID spreadsheet Google Ads Conversion belum diatur.');
        }

        $client = new Client;
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Sheets::SPREADSHEETS);

        $service = new Sheets($client);
        $service->spreadsheets_values->append(
            $spreadsheetId,
            "{$sheetName}!A:E",
            new ValueRange(['values' => [$row]]),
            ['valueInputOption' => 'USER_ENTERED'],
        );
    }
}
