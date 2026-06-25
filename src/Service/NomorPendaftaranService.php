<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Webane\Jalagistrasi\Repository\PendaftaranRepository;

final class NomorPendaftaranService
{
    public function __construct(
        private readonly PendaftaranRepository $pendaftaranRepo
    ) {}

    /**
     * Generate nomor pendaftaran unik untuk gelombang yang diberikan.
     * Format: {PREFIX}-{TAHUN}-{SEQ}   contoh: PMB-2026-0001
     *
     * PREFIX dan panjang SEQ bisa dikonfigurasi via wp_options.
     */
    public function generate(int $gelombangId, string $tahunAkademik): string
    {
        $prefix    = strtoupper((string) get_option('jalagistrasi_nomor_prefix', 'PMB'));
        $seqLength = max(1, (int) get_option('jalagistrasi_nomor_seq_length', 4));

        // Ambil 4 digit tahun awal dari "2026/2027" → "2026"
        $tahun = substr(preg_replace('/[^0-9\/]/', '', $tahunAkademik), 0, 4);
        if (strlen($tahun) !== 4) {
            $tahun = (string) date('Y');
        }

        $seq     = $this->pendaftaranRepo->countByGelombang($gelombangId) + 1;
        $seqPad  = str_pad((string) $seq, $seqLength, '0', STR_PAD_LEFT);

        return "{$prefix}-{$tahun}-{$seqPad}";
    }
}
