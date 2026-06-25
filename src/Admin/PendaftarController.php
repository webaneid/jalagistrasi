<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Enum\StatusPendaftaran;
use Webane\Jalagistrasi\Repository\BerkasRepository;
use Webane\Jalagistrasi\Repository\FormJawabanRepository;
use Webane\Jalagistrasi\Repository\FormSchemaRepository;
use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\PembayaranRepository;
use Webane\Jalagistrasi\Repository\PendaftaranProdiRepository;
use Webane\Jalagistrasi\Repository\PendaftaranRepository;
use Webane\Jalagistrasi\Repository\RekeningBankRepository;
use Webane\Jalagistrasi\Repository\TipeBerkasRepository;
use Webane\Jalagistrasi\Service\KodeUnikPembayaranGenerator;

final class PendaftarController
{
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        // Detail pendaftar
        $detailId = (int) ($_GET['id'] ?? 0);
        if ($detailId > 0) {
            $this->renderDetail($detailId);
            return;
        }

        // List
        $pendaftaranRepo = new PendaftaranRepository();
        $gelombangRepo   = new GelombangRepository();

        $gelombangId = (int) ($_GET['gelombang_id'] ?? 0);
        $status      = sanitize_key($_GET['status'] ?? '');
        $search      = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $page        = max(1, (int) ($_GET['paged'] ?? 1));
        $perPage     = 20;

        $result      = $pendaftaranRepo->findAllWithFilter($gelombangId, $status, $search, $page, $perPage);
        $gelombangList = $gelombangRepo->findAll();

        // Notifikasi sukses update status
        $updated = sanitize_key($_GET['updated'] ?? '');

        $this->loadTemplate('admin/pendaftar/list', [
            'rows'         => $result['rows'],
            'total'        => $result['total'],
            'perPage'      => $perPage,
            'page'         => $page,
            'gelombangId'  => $gelombangId,
            'status'       => $status,
            'search'       => $search,
            'gelombangList' => $gelombangList,
            'updated'      => $updated,
        ]);
    }

    private function renderDetail(int $id): void
    {
        $pendaftaranRepo = new PendaftaranRepository();
        $pendaftaran     = $pendaftaranRepo->findById($id);

        if (!$pendaftaran) {
            wp_die(esc_html__('Pendaftaran tidak ditemukan.', 'jalagistrasi'), 404);
        }

        $gelombangRepo  = new GelombangRepository();
        $formSchemaRepo = new FormSchemaRepository();
        $jawabanRepo    = new FormJawabanRepository();
        $berkasRepo     = new BerkasRepository();
        $prodiPilRepo   = new PendaftaranProdiRepository();
        $tipeBerkasRepo = new TipeBerkasRepository();

        $pembayaranRepo    = new PembayaranRepository();
        $rekeningBankRepo  = new RekeningBankRepository();
        $statusHistoryRepo = new \Webane\Jalagistrasi\Repository\StatusHistoryRepository();

        $gelombang      = $gelombangRepo->findById((int) $pendaftaran->gelombang_id);
        $fields         = $formSchemaRepo->findByGelombang((int) $pendaftaran->gelombang_id);
        $rawJawaban     = $jawabanRepo->findByPendaftaran($id);
        $berkasList     = $berkasRepo->findByPendaftaran($id);
        $prodiPilihan   = $prodiPilRepo->findByPendaftaran($id);
        $tipeBerkasList = $tipeBerkasRepo->findByGelombang((int) $pendaftaran->gelombang_id);
        $wpUser         = get_userdata((int) $pendaftaran->user_id);
        $pembayaran     = $pembayaranRepo->findByPendaftaran($id);
        $rekeningBank   = $pembayaran ? $rekeningBankRepo->findById((int) $pembayaran->rekening_bank_id) : null;
        $statusHistory  = $statusHistoryRepo->findByPendaftaran($id);

        // Total yang seharusnya ditransfer — biaya gelombang + kode unik pendaftaran.
        $totalSeharusnya = $pendaftaran->kode_unik_pembayaran !== null
            ? (float) $gelombang->biaya_pendaftaran + (int) $pendaftaran->kode_unik_pembayaran
            : null;

        // Map jawaban by field_id
        $jawabanMap = [];
        foreach ($rawJawaban as $j) {
            $jawabanMap[(int) $j->field_id] = $j;
        }

        // Kelompokkan field per seksi
        $sections = [];
        foreach ($fields as $field) {
            $seksi              = $field->section_name ?: __('Lainnya', 'jalagistrasi');
            $sections[$seksi][] = $field;
        }

        // Map berkas by tipe
        $berkasMap = [];
        foreach ($berkasList as $b) {
            $berkasMap[$b->tipe_berkas] = $b;
        }

        $currentStatus  = StatusPendaftaran::tryFrom($pendaftaran->status);
        $nextTransitions = $currentStatus ? $currentStatus->allowedTransitions() : [];

        // Pengingat: semua dokumen WAJIB sudah "diterima" tapi status besar belum
        // dipindah ke berkas_diverifikasi — admin mungkin lupa update status manual
        // (lihat docs/arsitektur-verifikasi-berkas.md, keputusan tidak ada auto-transisi).
        $siapDiverifikasi = false;
        if ($currentStatus === StatusPendaftaran::BerkasDiupload) {
            $tipeWajib = array_filter($tipeBerkasList, static fn ($t) => (bool) $t->is_required);
            $siapDiverifikasi = !empty($tipeWajib) && array_reduce(
                $tipeWajib,
                static function (bool $carry, object $t) use ($berkasMap) {
                    $b = $berkasMap[$t->kode] ?? null;
                    return $carry && $b !== null && $b->status === 'diterima';
                },
                true
            );
        }

        $this->loadTemplate('admin/pendaftar/detail', [
            'pendaftaran'    => $pendaftaran,
            'gelombang'      => $gelombang,
            'wpUser'         => $wpUser,
            'sections'       => $sections,
            'jawabanMap'     => $jawabanMap,
            'berkasMap'      => $berkasMap,
            'tipeBerkasList' => $tipeBerkasList,
            'prodiPilihan'   => $prodiPilihan,
            'currentStatus'  => $currentStatus,
            'nextTransitions' => $nextTransitions,
            'pembayaran'      => $pembayaran,
            'rekeningBank'    => $rekeningBank,
            'totalSeharusnya' => $totalSeharusnya,
            'siapDiverifikasi' => $siapDiverifikasi,
            'statusHistory'   => $statusHistory,
        ]);
    }

    public function handleUpdateStatus(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_update_status_pendaftaran')) {
            wp_die(esc_html__('Nonce tidak valid.', 'jalagistrasi'), 403);
        }

        $id       = (int) ($_POST['pendaftaran_id'] ?? 0);
        $newStatus = sanitize_key($_POST['new_status'] ?? '');
        $catatan  = sanitize_textarea_field(wp_unslash($_POST['catatan_panitia'] ?? ''));

        if ($id <= 0 || $newStatus === '') {
            wp_die(esc_html__('Data tidak lengkap.', 'jalagistrasi'), 400);
        }

        $pendaftaranRepo = new PendaftaranRepository();
        $pendaftaran     = $pendaftaranRepo->findById($id);

        if (!$pendaftaran) {
            wp_die(esc_html__('Pendaftaran tidak ditemukan.', 'jalagistrasi'), 404);
        }

        $currentStatus = StatusPendaftaran::tryFrom($pendaftaran->status);
        $nextStatus    = StatusPendaftaran::tryFrom($newStatus);

        if (!$currentStatus || !$nextStatus || !$currentStatus->canTransitionTo($nextStatus)) {
            wp_die(
                esc_html(sprintf(
                    __('Transisi status dari "%s" ke "%s" tidak diizinkan.', 'jalagistrasi'),
                    $pendaftaran->status,
                    $newStatus
                )),
                400
            );
        }

        $pendaftaranRepo->updateStatusWithCatatan($id, $newStatus, $catatan);

        (new \Webane\Jalagistrasi\Repository\StatusHistoryRepository())->log(
            $id,
            $pendaftaran->status,
            $newStatus,
            get_current_user_id(),
            $catatan
        );

        // Kode unik pembayaran dibuat sekali, tepat saat dokumen+data dinyatakan valid —
        // lihat docs/arsitektur-pembayaran.md bagian "Kode Unik Pembayaran".
        if ($nextStatus === StatusPendaftaran::BerkasDiverifikasi) {
            (new KodeUnikPembayaranGenerator())->ensureForPendaftaran(
                $id,
                (int) $pendaftaran->gelombang_id,
                $pendaftaran->kode_unik_pembayaran !== null ? (int) $pendaftaran->kode_unik_pembayaran : null
            );
        }

        // Titik hook notifikasi — belum ada listener (lihat docs/arsitektur-pembayaran.md
        // bagian "Notifikasi"). Disiapkan sekarang supaya sistem email nanti tidak perlu
        // menyentuh file ini lagi.
        do_action('jg_pendaftaran_status_changed', $id, $pendaftaran->status, $newStatus);

        $backUrl = add_query_arg([
            'page'    => 'jg-pendaftar',
            'id'      => $id,
            'updated' => '1',
        ], admin_url('admin.php'));

        wp_safe_redirect($backUrl);
        exit;
    }

    /**
     * Terima / tolak satu dokumen berkas. Hook: admin_post_jg_verify_berkas
     * Status pendaftaran TIDAK ikut berubah otomatis — admin tetap mengubahnya
     * manual lewat form "Update Status" jika diperlukan.
     */
    public function handleVerifyBerkas(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_verify_berkas')) {
            wp_die(esc_html__('Nonce tidak valid.', 'jalagistrasi'), 403);
        }

        $berkasId      = (int) ($_POST['berkas_id'] ?? 0);
        $pendaftaranId = (int) ($_POST['pendaftaran_id'] ?? 0);
        $decision      = sanitize_key($_POST['decision'] ?? '');
        $catatan       = sanitize_textarea_field(wp_unslash($_POST['catatan'] ?? ''));

        $allowedDecisions = ['diterima', 'ditolak', 'pending'];

        if ($berkasId <= 0 || $pendaftaranId <= 0 || !in_array($decision, $allowedDecisions, true)) {
            wp_die(esc_html__('Data tidak lengkap.', 'jalagistrasi'), 400);
        }

        if ($decision === 'ditolak' && $catatan === '') {
            wp_die(esc_html__('Catatan wajib diisi saat menolak dokumen.', 'jalagistrasi'), 400);
        }

        (new BerkasRepository())->updateVerifikasi($berkasId, $decision, $catatan, get_current_user_id());

        $backUrl = add_query_arg(['page' => 'jg-pendaftar', 'id' => $pendaftaranId], admin_url('admin.php'));
        wp_safe_redirect($backUrl);
        exit;
    }

    /**
     * Export semua pendaftar (mengikuti filter yang sedang aktif di halaman list)
     * ke file .xlsx. Hook: admin_post_jg_export_pendaftar
     */
    public function handleExportExcel(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? $_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_export_pendaftar')) {
            wp_die(esc_html__('Permintaan tidak valid.', 'jalagistrasi'), 403);
        }

        $gelombangId = (int) ($_REQUEST['gelombang_id'] ?? 0);
        $status      = sanitize_key($_REQUEST['status'] ?? '');
        $search      = sanitize_text_field(wp_unslash($_REQUEST['s'] ?? ''));

        $spreadsheet = (new \Webane\Jalagistrasi\Service\PendaftarExportService())->build($gelombangId, $status, $search);

        $filename = 'pendaftar-' . current_time('Y-m-d-His') . '.xlsx';

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Serve dokumen (KTP/KK/ijazah/foto, dst) untuk link yang ditaruh di export Excel.
     * Hook: wp_ajax_jg_export_preview_berkas
     *
     * SENGAJA tidak pakai nonce per-resource (beda dari handlePreviewBerkas di
     * frontend) — link ini ditempel di file Excel yang bisa dibuka kapan saja,
     * nonce WP expired ~1-2 hari jadi tidak cocok. Proteksi murni dari login +
     * capability admin (lihat percakapan "export excel + link dokumen", keputusan:
     * wajib login admin, link tidak pernah expired).
     */
    public function handleExportPreviewBerkas(): void
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            auth_redirect();
            exit;
        }

        $berkasId = (int) ($_GET['berkas_id'] ?? 0);
        if ($berkasId <= 0) {
            wp_die('Not found', '', ['response' => 404]);
        }

        $berkas = (new BerkasRepository())->findById($berkasId);
        if (!$berkas) {
            wp_die('Not found', '', ['response' => 404]);
        }

        $this->streamBerkasFile($berkas->file_path, $berkas->file_name_original);
    }

    /**
     * Serve bukti pembayaran untuk link export Excel. Hook: wp_ajax_jg_export_preview_pembayaran
     * Lihat catatan keamanan di handleExportPreviewBerkas().
     */
    public function handleExportPreviewPembayaran(): void
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            auth_redirect();
            exit;
        }

        $pembayaranId = (int) ($_GET['pembayaran_id'] ?? 0);
        if ($pembayaranId <= 0) {
            wp_die('Not found', '', ['response' => 404]);
        }

        $pembayaran = (new PembayaranRepository())->findById($pembayaranId);
        if (!$pembayaran) {
            wp_die('Not found', '', ['response' => 404]);
        }

        $this->streamBerkasFile($pembayaran->file_path, $pembayaran->file_name_original);
    }

    /**
     * Validasi mime type dari file aktual (bukan dari DB) lalu kirim ke browser.
     * Dipakai bersama oleh handleExportPreviewBerkas() & handleExportPreviewPembayaran().
     */
    private function streamBerkasFile(string $relativeFilePath, string $originalFileName): void
    {
        $filePath = JG_UPLOAD_DIR . '/' . $relativeFilePath;

        if (!file_exists($filePath) || !is_file($filePath)) {
            wp_die('File not found', '', ['response' => 404]);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            wp_die('Forbidden', '', ['response' => 403]);
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . sanitize_file_name($originalFileName) . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

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
