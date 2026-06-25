<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Webane\Jalagistrasi\Repository\PendaftaranRepository;

/**
 * Generate kode unik 3 digit (1-999) per pendaftaran untuk membantu admin
 * mencocokkan mutasi rekening — lihat docs/arsitektur-pembayaran.md.
 *
 * Unik dalam lingkup satu gelombang (bukan global), karena nominal biaya
 * pendaftaran yang berpotensi sama hanya antar pendaftar di gelombang yang sama.
 */
final class KodeUnikPembayaranGenerator
{
    private const MAX_KODE    = 999;
    private const MAX_RETRY   = 50;

    private PendaftaranRepository $repo;

    public function __construct()
    {
        $this->repo = new PendaftaranRepository();
    }

    /**
     * Generate & simpan kode unik untuk satu pendaftaran. Tidak melakukan apa-apa
     * jika pendaftaran sudah punya kode (tidak pernah digenerate ulang).
     */
    public function ensureForPendaftaran(int $pendaftaranId, int $gelombangId, ?int $kodeSaatIni): void
    {
        if ($kodeSaatIni !== null) {
            return;
        }

        $terpakai = $this->repo->findKodeUnikTerpakai($gelombangId);

        for ($i = 0; $i < self::MAX_RETRY; $i++) {
            $kandidat = random_int(1, self::MAX_KODE);
            if (!in_array($kandidat, $terpakai, true)) {
                $this->repo->updateKodeUnikPembayaran($pendaftaranId, $kandidat);
                return;
            }
        }

        // Sangat tidak mungkin tercapai (lihat docs/arsitektur-pembayaran.md) — log saja.
        error_log(sprintf(
            'Jalagistrasi: gagal generate kode unik pembayaran untuk pendaftaran #%d setelah %d percobaan.',
            $pendaftaranId,
            self::MAX_RETRY
        ));
    }
}
