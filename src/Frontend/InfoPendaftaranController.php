<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Frontend;

use Webane\Jalagistrasi\Repository\GelombangRepository;

/**
 * Shortcode [jg_info_pendaftaran] — halaman publik, tidak butuh login.
 * Lihat docs/arsitektur-landing-publik.md.
 */
final class InfoPendaftaranController
{
    public function shortcodeInfoPendaftaran(): string
    {
        $gelombangRepo = new GelombangRepository();
        $gelombangAktif = $gelombangRepo->findAktifTerbuka();

        // "Tahun Akademik" yang ditampilkan diambil dari gelombang yang BENAR-BENAR
        // terbuka — bukan dari label jg_tahun_ajaran.status (itu cuma label tampilan,
        // tidak terikat ke ketersediaan gelombang, lihat docs/arsitektur-tahun-ajaran.md).
        // Kalau tidak ada gelombang terbuka, tidak ada "tahun akademik berjalan" yang
        // relevan untuk ditampilkan — otomatis konsisten dengan status gelombang.
        $tahunAjaranAktif = $gelombangAktif[0]->tahun_akademik ?? '';

        $registrasiId  = (int) get_option('jalagistrasi_page_registrasi', 0);
        $registrasiUrl = $registrasiId > 0 ? (string) get_permalink($registrasiId) : home_url('/daftar/');

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0 ? (string) get_permalink($dashboardId) : home_url('/dashboard-pmb/');

        $logoId  = (int) get_option('jalagistrasi_logo_id', 0);
        $logoUrl = $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'medium') : '';

        ob_start();
        $this->loadTemplate('frontend/info-pendaftaran/index', [
            'gelombangAktif'    => $gelombangAktif,
            'registrasiUrl'     => $registrasiUrl,
            'dashboardUrl'      => $dashboardUrl,
            'isLoggedIn'        => is_user_logged_in(),
            'namaInstitusi'     => (string) get_option('jalagistrasi_nama_institusi', ''),
            'logoUrl'           => $logoUrl,
            'tahunAjaranAktif'  => $tahunAjaranAktif,
            'alamatInstitusi'   => (string) get_option('jalagistrasi_alamat_institusi', ''),
            'telpInstitusi'     => (string) get_option('jalagistrasi_telp_institusi', ''),
            'emailInstitusi'    => (string) get_option('jalagistrasi_email_institusi', ''),
        ]);
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function loadTemplate(string $name, array $vars = []): void
    {
        $path = JG_PLUGIN_DIR . 'templates/' . $name . '.php';

        if (!file_exists($path)) {
            echo esc_html(sprintf('Template tidak ditemukan: %s', $name));
            return;
        }

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($vars, EXTR_SKIP);
        include $path;
    }
}
