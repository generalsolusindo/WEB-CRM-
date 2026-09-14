<?php

/**
 * Titik mulai nomor urut Invoice/Quotation, dipakai untuk menyambung dari
 * sistem manual/lama tanpa perlu tabel database — cukup diatur lewat .env,
 * lalu jalankan `php artisan config:clear` (atau restart php-fpm) di server.
 *
 * Contoh isi .env:
 *   QUOTATION_NUMBER_START_YEAR=2026
 *   QUOTATION_NUMBER_START_SEQUENCE=1255
 *
 * Kalau tahun di .env tidak sama dengan tahun berjalan, setting ini
 * diabaikan (supaya tidak nyangkut ke tahun berikutnya tanpa sengaja).
 */
return [
    'invoice' => [
        'year' => env('INVOICE_NUMBER_START_YEAR'),
        'next_sequence' => env('INVOICE_NUMBER_START_SEQUENCE'),
    ],

    'quotation' => [
        'year' => env('QUOTATION_NUMBER_START_YEAR'),
        'next_sequence' => env('QUOTATION_NUMBER_START_SEQUENCE'),
    ],
];
