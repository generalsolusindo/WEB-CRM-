<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Semua upload di test diarahkan ke disk palsu.
        Storage::fake('local');
    }

    /**
     * Payload standar untuk konfirmasi quotation (butuh bukti dokumen quotation).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function confirmPayload(string $orderType, array $extra = []): array
    {
        return array_merge([
            'order_type' => $orderType,
            'signed_quotation' => UploadedFile::fake()->create('quotation-ttd.pdf', 60, 'application/pdf'),
        ], $extra);
    }
}
