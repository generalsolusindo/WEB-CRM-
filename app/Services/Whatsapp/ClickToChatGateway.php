<?php

namespace App\Services\Whatsapp;

/**
 * Cara A: tidak mengirim apa pun dari server — hanya membangun tautan wa.me
 * yang dibuka Finance di WhatsApp Web/Desktop miliknya, dengan nomor customer
 * dan pesan sudah terisi. Gratis, tanpa API, tanpa persetujuan Meta.
 */
class ClickToChatGateway implements WhatsappGateway
{
    public function link(string $internationalNumber, string $message): string
    {
        return 'https://wa.me/'.$internationalNumber.'?text='.rawurlencode($message);
    }
}
