<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Frontend;

use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\TahunAjaranRepository;

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

        $tahunAjaranAktifRow = (new TahunAjaranRepository())->findAktif();

        $registrasiId  = (int) get_option('jalagistrasi_page_registrasi', 0);
        $registrasiUrl = $registrasiId > 0 ? (string) get_permalink($registrasiId) : home_url('/daftar/');

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0 ? (string) get_permalink($dashboardId) : home_url('/dashboard-pmb/');

        ob_start();
        $this->loadTemplate('frontend/info-pendaftaran/index', [
            'gelombangAktif'    => $gelombangAktif,
            'registrasiUrl'     => $registrasiUrl,
            'dashboardUrl'      => $dashboardUrl,
            'isLoggedIn'        => is_user_logged_in(),
            'namaInstitusi'     => (string) get_option('jalagistrasi_nama_institusi', ''),
            'tahunAjaranAktif'  => $tahunAjaranAktifRow?->nama ?? '',
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
