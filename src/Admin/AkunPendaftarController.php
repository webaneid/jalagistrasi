<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Repository\AkunPendaftarRepository;
use Webane\Jalagistrasi\Service\AkunPendaftarExportService;

/**
 * Halaman admin "Role Pendaftar" — daftar SEMUA akun ber-role 'pendaftar',
 * terlepas pernah submit formulir atau belum. Beda dari halaman "Pendaftar"
 * (PendaftarController) yang sumbernya tabel jg_pendaftaran (transaksional
 * per gelombang). Lihat docs/arsitektur-overview.md.
 */
final class AkunPendaftarController
{
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $statusFilter = sanitize_key($_GET['status'] ?? '');
        $search       = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $page         = max(1, (int) ($_GET['paged'] ?? 1));
        $perPage      = 20;

        $result = (new AkunPendaftarRepository())->findAllWithFilter($statusFilter, $search, $page, $perPage);

        $this->loadTemplate('admin/akun-pendaftar/list', [
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'perPage'     => $perPage,
            'page'        => $page,
            'statusFilter' => $statusFilter,
            'search'      => $search,
        ]);
    }

    /**
     * Export semua akun (mengikuti filter aktif) ke .xlsx. Hook: admin_post_jg_export_akun_pendaftar
     */
    public function handleExportExcel(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? $_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_export_akun_pendaftar')) {
            wp_die(esc_html__('Permintaan tidak valid.', 'jalagistrasi'), 403);
        }

        $statusFilter = sanitize_key($_REQUEST['status'] ?? '');
        $search       = sanitize_text_field(wp_unslash($_REQUEST['s'] ?? ''));

        $spreadsheet = (new AkunPendaftarExportService())->build($statusFilter, $search);

        $filename = 'akun-pendaftar-' . current_time('Y-m-d-His') . '.xlsx';

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * @param array<string,mixed> $vars
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
