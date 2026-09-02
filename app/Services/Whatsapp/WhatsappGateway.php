<?php

namespace App\Services\Whatsapp;

interface WhatsappGateway
{
    /**
     * Bangun tautan "click to chat" wa.me untuk nomor + pesan.
     * Driver API resmi nanti bisa menambah method send() tanpa mengubah pemakai.
     */
    public function link(string $internationalNumber, string $message): string;
}
