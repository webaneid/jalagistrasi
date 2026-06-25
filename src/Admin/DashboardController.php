<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Enum\StatusPendaftaran;
use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\PendaftaranProdiRepository;
use Webane\Jalagistrasi\Repository\PendaftaranRepository;
use Webane\Jalagistrasi\Repository\TahunAjaranRepository;

/**
 * Dashboard admin dengan statistik pendaftaran. Lihat docs/arsitektur-dashboard-admin.md
 * dan docs/arsitektur-tahun-ajaran.md (filter per tahun ajaran).
 */
final class DashboardController
{
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $gelombangRepo     = new GelombangRepository();
        $pendaftaranRepo   = new PendaftaranRepository();
        $prodiPilihanRepo  = new PendaftaranProdiRepository();
        $tahunAjaranRepo   = new TahunAjaranRepository();

        $tahunAjaranId = (int) ($_GET['tahun_ajaran_id'] ?? 0);
        $gelombangId   = (int) ($_GET['gelombang_id'] ?? 0);

        // Pilih gelombang_id cuma berlaku kalau gelombang itu memang ada di tahun ajaran
        // yang dipilih — kalau tahun ajaran diganti, jangan ikut filter gelombang lama.
        $gelombangList   = $tahunAjaranId > 0 ? $gelombangRepo->findByTahunAjaran($tahunAjaranId) : $gelombangRepo->findAll();
        $gelombangIdList = array_map(static fn ($g) => (int) $g->id, $gelombangList);
        if ($gelombangId > 0 && !in_array($gelombangId, $gelombangIdList, true)) {
            $gelombangId = 0;
        }

        $tahunAjaranList = $tahunAjaranRepo->findAll();

        $total           = $pendaftaranRepo->countTotal($gelombangId, $tahunAjaranId);
        $statusGrouped   = $pendaftaranRepo->countByStatusGrouped($gelombangId, $tahunAjaranId);
        $prodiTerpopuler = $prodiPilihanRepo->findProdiTerpopuler($gelombangId, 10, $tahunAjaranId);

        $menungguDokumen = $statusGrouped[StatusPendaftaran::BerkasDiupload->value] ?? 0;
        $menungguBayar   = $statusGrouped[StatusPendaftaran::PembayaranDiupload->value] ?? 0;
        $lulusSeleksi    = ($statusGrouped[StatusPendaftaran::DiumumkanLulus->value] ?? 0)
            + ($statusGrouped[StatusPendaftaran::DaftarUlang->value] ?? 0)
            + ($statusGrouped[StatusPendaftaran::Selesai->value] ?? 0);

        // Urutan & label semua status (kecuali draft) untuk tabel breakdown.
        $semuaStatus = array_filter(StatusPendaftaran::cases(), static fn ($s) => $s !== StatusPendaftaran::Draft);

        // Breakdown per gelombang — hanya dihitung kalau Tahun Ajaran dipilih & gelombang = semua,
        // sesuai permintaan: "tahun ajaran sekian → berapa yang daftar di gelombang 1, gelombang 2".
        $breakdownGelombang = [];
        if ($tahunAjaranId > 0 && $gelombangId === 0) {
            foreach ($gelombangList as $g) {
                $breakdownGelombang[] = [
                    'nama'   => $g->nama,
                    'jumlah' => $pendaftaranRepo->countTotal((int) $g->id),
                ];
            }
        }

        $path = JG_PLUGIN_DIR . 'templates/admin/dashboard/index.php';
        extract([
            'tahunAjaranList'    => $tahunAjaranList,
            'tahunAjaranId'      => $tahunAjaranId,
            'gelombangList'      => $gelombangList,
            'gelombangId'        => $gelombangId,
            'total'              => $total,
            'menungguDokumen'    => $menungguDokumen,
            'menungguBayar'      => $menungguBayar,
            'lulusSeleksi'       => $lulusSeleksi,
            'statusGrouped'      => $statusGrouped,
            'semuaStatus'        => $semuaStatus,
            'prodiTerpopuler'    => $prodiTerpopuler,
            'breakdownGelombang' => $breakdownGelombang,
        ], EXTR_SKIP);
        include $path;
    }
}
