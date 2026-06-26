<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

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
        $result = (new Builder(
            writer: new SvgWriter(),
            data: $data,
            size: $size,
            margin: 8
        ))->build();

        return $result->getDataUri();
    }
}
