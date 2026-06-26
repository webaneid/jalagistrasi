<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

// Catatan versi: endroid/qr-code SENGAJA dipin ke 5.1.0 (lihat composer.json),
// bukan versi 6.x terbaru — 6.x butuh PHP 8.2/8.4+, sementara plugin ini target
// minimum PHP 8.1 (sama kasusnya dengan maennchen/zipstream-php, lihat riwayat
// commit). API 5.1.0 pakai fluent builder (Builder::create()->writer(...)...),
// BUKAN named constructor arguments seperti contoh README versi 6.x — jangan
// "upgrade" balik ke style constructor tanpa cek ulang requirement PHP-nya.

/**
 * Generate QR code sebagai SVG data URI — dipakai halaman sukses pendaftaran
 * & halaman verifikasi (/verifikasi/<nomor>/<token>/). Lihat docs/arsitektur-verifikasi-qr.md.
 *
 * SVG dipilih (bukan PNG) supaya TIDAK butuh extension PHP tambahan (GD/Imagick)
 * di server — banyak hosting shared tidak punya itu terpasang, sementara SVG
 * murni teks/XML, selalu jalan di environment manapun.
 */
class QrCodeService
{
    public function generateSvgDataUri(string $data, int $size = 220): string
    {
        $result = Builder::create()
            ->writer(new SvgWriter())
            ->data($data)
            ->size($size)
            ->margin(8)
            ->build();

        return $result->getDataUri();
    }
}
