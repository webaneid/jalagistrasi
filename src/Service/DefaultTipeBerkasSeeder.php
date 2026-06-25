<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Webane\Jalagistrasi\Repository\TipeBerkasRepository;

/**
 * Pas Foto selalu wajib ada di setiap gelombang — tidak perlu dikonfigurasi
 * manual oleh admin lewat halaman Tipe Berkas. Dokumen lain (KTP, KK, Ijazah, dll)
 * tetap custom dan dikonfigurasi manual per gelombang.
 *
 * Dipanggil otomatis saat gelombang baru dibuat, dan secara lazy setiap kali
 * daftar tipe berkas suatu gelombang diakses (admin maupun frontend) — agar
 * gelombang lama yang dibuat sebelum fitur ini ada tetap terisi otomatis.
 */
class DefaultTipeBerkasSeeder
{
    private TipeBerkasRepository $repo;

    public function __construct()
    {
        $this->repo = new TipeBerkasRepository();
    }

    public function ensureDefault(int $gelombangId): void
    {
        if ($this->repo->kodeExists($gelombangId, 'foto')) {
            return;
        }

        $this->repo->insert([
            'gelombang_id' => $gelombangId,
            'kode'         => 'foto',
            'label'        => 'Pas Foto',
            'keterangan'   => 'Foto terbaru berwarna, latar belakang merah atau biru.',
            'is_required'  => 1,
            'max_size_kb'  => 2048,
            'urutan'       => 0,
        ]);
    }
}
