<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Frontend;

use Webane\Jalagistrasi\Enum\StatusPendaftaran;
use Webane\Jalagistrasi\Repository\BerkasRepository;
use Webane\Jalagistrasi\Repository\FormJawabanRepository;
use Webane\Jalagistrasi\Repository\PembayaranRepository;
use Webane\Jalagistrasi\Repository\RekeningBankRepository;
use Webane\Jalagistrasi\Repository\StatusHistoryRepository;
use Webane\Jalagistrasi\Repository\TipeBerkasRepository;
use Webane\Jalagistrasi\Repository\WilayahRepository;
use Webane\Jalagistrasi\Repository\FormSchemaRepository;
use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\PendaftaranProdiRepository;
use Webane\Jalagistrasi\Repository\PendaftaranRepository;
use Webane\Jalagistrasi\Repository\PendaftarRepository;
use Webane\Jalagistrasi\Repository\ProgramStudiRepository;
use Webane\Jalagistrasi\Service\FileUploadService;
use Webane\Jalagistrasi\Service\NomorPendaftaranService;
use Webane\Jalagistrasi\Service\PendaftaranService;

/**
 * Menangani submit formulir pendaftaran via admin_post_.
 * Hook: admin_post_jg_submit_pendaftaran
 */
final class PendaftaranController
{
    private PendaftaranService $service;

    public function __construct()
    {
        $pendaftaranRepo = new PendaftaranRepository();

        $this->service = new PendaftaranService(
            pendaftaranRepo:  $pendaftaranRepo,
            jawabanRepo:      new FormJawabanRepository(),
            prodiRepo:        new PendaftaranProdiRepository(),
            berkasRepo:       new BerkasRepository(),
            formSchemaRepo:   new FormSchemaRepository(),
            gelombangRepo:    new GelombangRepository(),
            prodiStudiRepo:   new ProgramStudiRepository(),
            pendaftarRepo:    new PendaftarRepository(),
            fileService:      new FileUploadService(),
            nomorService:     new NomorPendaftaranService($pendaftaranRepo),
        );
    }

    public function handleSubmit(): void
    {
        // Harus login
        if (!is_user_logged_in()) {
            wp_safe_redirect($this->loginUrl());
            exit;
        }

        // Verifikasi nonce
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'jg_submit_pendaftaran')) {
            $this->redirectWithError(__('Permintaan tidak valid. Silakan coba lagi.', 'jalagistrasi'));
            return;
        }

        $userId      = get_current_user_id();
        $gelombangId = (int) ($this->safePost('gelombang_id'));

        if ($gelombangId <= 0) {
            $this->redirectWithError(__('Gelombang tidak dipilih.', 'jalagistrasi'));
            return;
        }

        // Sanitize seluruh POST (kecuali prodi_pilihan dan checkbox yang perlu handling khusus)
        $postData  = $this->sanitizePost();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- file handling
        $filesData = $_FILES;

        $result = $this->service->submit($userId, $gelombangId, $postData, $filesData);

        if (!$result['success']) {
            $this->redirectWithErrors(
                $result['errors'] ?? [],
                $postData,
                $gelombangId
            );
            return;
        }

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0
            ? (string) get_permalink($dashboardId)
            : home_url('/dashboard-pmb/');

        // Edit formulir pada pendaftaran yang sudah disubmit — bukan submit pertama,
        // jadi kembali ke halaman detail (bukan halaman konfirmasi "Pendaftaran Berhasil").
        if (!empty($result['isEdit'])) {
            set_transient('jg_form_updated_' . $userId, '1', 30);

            $detailUrl = add_query_arg([
                'action'         => 'detail',
                'pendaftaran_id' => $result['pendaftaran_id'],
            ], $dashboardUrl);

            wp_safe_redirect($detailUrl);
            exit;
        }

        // Sukses (submit pertama): redirect ke halaman konfirmasi
        $successUrl = add_query_arg([
            'action' => 'sukses',
            'ref'    => rawurlencode($result['nomor'] ?? ''),
        ], $dashboardUrl);

        wp_safe_redirect($successUrl);
        exit;
    }

    /**
     * Upload satu berkas (step 3). Hook: admin_post_jg_upload_berkas_item
     */
    public function handleUploadBerkasItem(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect($this->loginUrl());
            exit;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_upload_berkas')) {
            $this->redirectWithError(__('Permintaan tidak valid.', 'jalagistrasi'));
            return;
        }

        $userId          = get_current_user_id();
        $pendaftaranId   = (int) ($_POST['pendaftaran_id'] ?? 0);
        $tipeBerkasId    = (int) ($_POST['tipe_berkas_id'] ?? 0);

        if ($pendaftaranId <= 0 || $tipeBerkasId <= 0) {
            $this->redirectWithError(__('Data tidak lengkap.', 'jalagistrasi'));
            return;
        }

        // Verifikasi kepemilikan
        $pendaftaranRepo = new PendaftaranRepository();
        $pendaftaran     = $pendaftaranRepo->findById($pendaftaranId);

        if (!$pendaftaran || (int) $pendaftaran->user_id !== $userId) {
            $this->redirectWithError(__('Akses ditolak.', 'jalagistrasi'));
            return;
        }

        $berkasRepoCek    = new BerkasRepository();
        $adaBerkasDitolak = !empty(array_filter(
            $berkasRepoCek->findByPendaftaran($pendaftaranId),
            static fn ($b) => $b->status === 'ditolak'
        ));

        // Status dokumen individual independen dari status besar pendaftaran —
        // panitia bisa menolak satu dokumen meski status besar sudah lanjut ke
        // fase tes/seleksi, jadi upload ulang tetap diizinkan kalau ada dokumen
        // yang ditolak, terlepas dari whitelist status besar di bawah ini.
        if (!in_array($pendaftaran->status, [
            StatusPendaftaran::Submitted->value,
            StatusPendaftaran::BerkasDiupload->value,
            StatusPendaftaran::BerkasDitolak->value,
        ], true) && !$adaBerkasDitolak) {
            $this->redirectWithError(__('Status pendaftaran tidak memungkinkan upload berkas.', 'jalagistrasi'));
            return;
        }

        // Validasi tipe berkas
        $tipeBerkasRepo = new TipeBerkasRepository();
        $tipeBerkas     = $tipeBerkasRepo->findById($tipeBerkasId);

        if (!$tipeBerkas || (int) $tipeBerkas->gelombang_id !== (int) $pendaftaran->gelombang_id) {
            $this->redirectWithError(__('Tipe berkas tidak valid.', 'jalagistrasi'));
            return;
        }

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0 ? (string) get_permalink($dashboardId) : home_url('/dashboard-pmb/');
        $backUrl      = add_query_arg(['action' => 'detail', 'pendaftaran_id' => $pendaftaranId], $dashboardUrl);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $fileData = $_FILES['berkas_file'] ?? null;

        if (!$fileData || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            set_transient('jg_upload_error_' . $userId, __('Pilih file terlebih dahulu.', 'jalagistrasi'), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        $fileService = new FileUploadService();
        $maxKb       = (int) $tipeBerkas->max_size_kb;
        $fileErrors  = $fileService->validate($fileData, $maxKb, $tipeBerkas->label);

        if (!empty($fileErrors)) {
            set_transient('jg_upload_error_' . $userId, implode(' ', $fileErrors), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        try {
            $fileInfo   = $fileService->store($fileData, $pendaftaranId, $tipeBerkas->kode);
            $berkasRepo = new BerkasRepository();
            $berkasRepo->deleteByPendaftaranAndTipe($pendaftaranId, $tipeBerkas->kode);
            $berkasRepo->insert([
                'pendaftaran_id'     => $pendaftaranId,
                'tipe_berkas'        => $tipeBerkas->kode,
                'file_path'          => $fileInfo['file_path'],
                'file_name_original' => $fileInfo['file_name_original'],
                'file_name_stored'   => $fileInfo['file_name_stored'],
                'file_size'          => $fileInfo['file_size'],
                'mime_type'          => $fileInfo['mime_type'],
                'status'             => 'pending',
            ]);
        } catch (\RuntimeException $e) {
            set_transient('jg_upload_error_' . $userId, $e->getMessage(), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        set_transient('jg_upload_success_' . $userId, $tipeBerkas->label, 60);
        wp_safe_redirect($backUrl);
        exit;
    }

    /**
     * Selesaikan upload berkas — ubah status ke berkas_diupload. Hook: admin_post_jg_finalize_berkas
     */
    public function handleFinalizeBerkas(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect($this->loginUrl());
            exit;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_finalize_berkas')) {
            $this->redirectWithError(__('Permintaan tidak valid.', 'jalagistrasi'));
            return;
        }

        $userId        = get_current_user_id();
        $pendaftaranId = (int) ($_POST['pendaftaran_id'] ?? 0);

        $pendaftaranRepo = new PendaftaranRepository();
        $pendaftaran     = $pendaftaranRepo->findById($pendaftaranId);

        if (!$pendaftaran || (int) $pendaftaran->user_id !== $userId) {
            $this->redirectWithError(__('Akses ditolak.', 'jalagistrasi'));
            return;
        }

        $statusSaatIni            = StatusPendaftaran::from($pendaftaran->status);
        $statusBolehUploadDokumen = [
            StatusPendaftaran::Submitted,
            StatusPendaftaran::BerkasDiupload,
            StatusPendaftaran::BerkasDitolak,
        ];

        // Cek semua berkas wajib sudah terupload
        $tipeBerkasRepo = new TipeBerkasRepository();
        $berkasRepo     = new BerkasRepository();
        $tipeList       = $tipeBerkasRepo->findByGelombang((int) $pendaftaran->gelombang_id);
        $berkasSaatIni  = $berkasRepo->findByPendaftaran($pendaftaranId);
        $sudahUpload    = [];

        foreach ($berkasSaatIni as $b) {
            $sudahUpload[] = $b->tipe_berkas;
        }

        // Status dokumen individual independen dari status besar pendaftaran —
        // izinkan finalize juga kalau ada dokumen yang ditolak meski status besar
        // sudah lanjut ke fase tes/seleksi (lihat $statusBolehUploadDokumen di atas).
        $adaBerkasDitolak = !empty(array_filter($berkasSaatIni, static fn ($b) => $b->status === 'ditolak'));

        if (!in_array($statusSaatIni, $statusBolehUploadDokumen, true) && !$adaBerkasDitolak) {
            $this->redirectWithError(__('Status tidak sesuai.', 'jalagistrasi'));
            return;
        }

        $kurang = [];
        foreach ($tipeList as $t) {
            if ($t->is_required && !in_array($t->kode, $sudahUpload, true)) {
                $kurang[] = $t->label;
            }
        }

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0 ? (string) get_permalink($dashboardId) : home_url('/dashboard-pmb/');
        $backUrl      = add_query_arg(['action' => 'detail', 'pendaftaran_id' => $pendaftaranId], $dashboardUrl);

        if (!empty($kurang)) {
            set_transient(
                'jg_upload_error_' . $userId,
                sprintf(__('Dokumen berikut belum diupload: %s', 'jalagistrasi'), implode(', ', $kurang)),
                60
            );
            wp_safe_redirect($backUrl);
            exit;
        }

        // Hanya majukan status besar kalau mahasiswa memang masih di fase dokumen.
        // Kalau status besar sudah lanjut (mis. dijadwalkan_tes) dan finalize ini
        // cuma terjadi karena ada dokumen yang ditolak di fase lanjut, jangan
        // turunkan status besar — cukup biarkan dokumen yang baru diupload
        // menunggu verifikasi ulang panitia lewat halaman admin.
        if (in_array($statusSaatIni, $statusBolehUploadDokumen, true)) {
            $pendaftaranRepo->updateStatus($pendaftaranId, StatusPendaftaran::BerkasDiupload->value);

            (new StatusHistoryRepository())->log(
                $pendaftaranId,
                $pendaftaran->status,
                StatusPendaftaran::BerkasDiupload->value,
                $userId
            );
        }

        set_transient('jg_berkas_finalized_' . $userId, '1', 30);
        wp_safe_redirect($backUrl);
        exit;
    }

    /**
     * Upload bukti transfer biaya pendaftaran. Hook: admin_post_jg_upload_pembayaran
     * Reupload (setelah ditolak) menghapus baris lama lalu insert baru — kode unik
     * pembayaran TIDAK ikut berubah (lihat docs/arsitektur-pembayaran.md).
     */
    public function handleUploadPembayaran(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect($this->loginUrl());
            exit;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_upload_pembayaran')) {
            $this->redirectWithError(__('Permintaan tidak valid.', 'jalagistrasi'));
            return;
        }

        $userId          = get_current_user_id();
        $pendaftaranId   = (int) ($_POST['pendaftaran_id'] ?? 0);
        $rekeningBankId  = (int) ($_POST['rekening_bank_id'] ?? 0);
        $jumlah          = (float) ($_POST['jumlah'] ?? 0);
        $namaPengirim    = sanitize_text_field(wp_unslash($_POST['nama_pengirim'] ?? ''));

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0 ? (string) get_permalink($dashboardId) : home_url('/dashboard-pmb/');
        $backUrl      = add_query_arg(['action' => 'detail', 'pendaftaran_id' => $pendaftaranId], $dashboardUrl);

        $pendaftaranRepo = new PendaftaranRepository();
        $pendaftaran     = $pendaftaranRepo->findById($pendaftaranId);

        if (!$pendaftaran || (int) $pendaftaran->user_id !== $userId) {
            $this->redirectWithError(__('Akses ditolak.', 'jalagistrasi'));
            return;
        }

        if (!in_array($pendaftaran->status, [
            StatusPendaftaran::BerkasDiverifikasi->value,
            StatusPendaftaran::PembayaranDitolak->value,
        ], true)) {
            set_transient('jg_pembayaran_error_' . $userId, __('Status pendaftaran tidak memungkinkan upload bukti pembayaran.', 'jalagistrasi'), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        $rekeningBank = (new RekeningBankRepository())->findById($rekeningBankId);
        if (!$rekeningBank || !$rekeningBank->is_aktif) {
            set_transient('jg_pembayaran_error_' . $userId, __('Rekening tujuan tidak valid.', 'jalagistrasi'), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        if ($jumlah <= 0) {
            set_transient('jg_pembayaran_error_' . $userId, __('Nominal yang ditransfer wajib diisi.', 'jalagistrasi'), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $fileData = $_FILES['bukti_file'] ?? null;

        if (!$fileData || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            set_transient('jg_pembayaran_error_' . $userId, __('Pilih file bukti transfer terlebih dahulu.', 'jalagistrasi'), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        $fileService = new FileUploadService();
        $fileErrors  = $fileService->validate($fileData, 5120, __('Bukti Transfer', 'jalagistrasi'));

        if (!empty($fileErrors)) {
            set_transient('jg_pembayaran_error_' . $userId, implode(' ', $fileErrors), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        try {
            $fileInfo       = $fileService->store($fileData, $pendaftaranId, 'bukti_bayar');
            $pembayaranRepo = new PembayaranRepository();
            $pembayaranRepo->deleteByPendaftaran($pendaftaranId);
            $pembayaranRepo->insert([
                'pendaftaran_id'     => $pendaftaranId,
                'rekening_bank_id'   => $rekeningBankId,
                'jumlah'             => $jumlah,
                'nama_pengirim'      => $namaPengirim ?: null,
                'file_path'          => $fileInfo['file_path'],
                'file_name_original' => $fileInfo['file_name_original'],
                'file_name_stored'   => $fileInfo['file_name_stored'],
                'file_size'          => $fileInfo['file_size'],
                'mime_type'          => $fileInfo['mime_type'],
            ]);
        } catch (\RuntimeException $e) {
            set_transient('jg_pembayaran_error_' . $userId, $e->getMessage(), 60);
            wp_safe_redirect($backUrl);
            exit;
        }

        $pendaftaranRepo->updateStatus($pendaftaranId, StatusPendaftaran::PembayaranDiupload->value);
        do_action('jg_pendaftaran_status_changed', $pendaftaranId, $pendaftaran->status, StatusPendaftaran::PembayaranDiupload->value);

        (new StatusHistoryRepository())->log(
            $pendaftaranId,
            $pendaftaran->status,
            StatusPendaftaran::PembayaranDiupload->value,
            $userId
        );

        set_transient('jg_pembayaran_success_' . $userId, '1', 30);
        wp_safe_redirect($backUrl);
        exit;
    }

    /**
     * AJAX: serve preview bukti pembayaran milik pendaftar yang sedang login.
     * Hook: wp_ajax_jg_preview_pembayaran
     */
    public function handlePreviewPembayaran(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', '', ['response' => 401]);
        }

        $pembayaranId = (int) ($_GET['pembayaran_id'] ?? 0);
        $nonce        = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if ($pembayaranId <= 0 || !wp_verify_nonce($nonce, 'jg_preview_pembayaran_' . $pembayaranId)) {
            wp_die('Forbidden', '', ['response' => 403]);
        }

        $pembayaranRepo = new PembayaranRepository();
        $pembayaran     = $pembayaranRepo->findById($pembayaranId);

        if (!$pembayaran) {
            wp_die('Not found', '', ['response' => 404]);
        }

        if (!current_user_can('manage_options')) {
            $pendaftaranRepo = new PendaftaranRepository();
            $pendaftaran     = $pendaftaranRepo->findById((int) $pembayaran->pendaftaran_id);

            if (!$pendaftaran || (int) $pendaftaran->user_id !== get_current_user_id()) {
                wp_die('Forbidden', '', ['response' => 403]);
            }
        }

        $filePath = JG_UPLOAD_DIR . '/' . $pembayaran->file_path;

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
        header('Content-Disposition: inline; filename="' . sanitize_file_name($pembayaran->file_name_original) . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

    /**
     * @param list<string>        $errors
     * @param array<string,mixed> $savedData
     */
    public function handleSaveDraft(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect($this->loginUrl());
            exit;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'jg_submit_pendaftaran')) {
            $this->redirectWithError(__('Permintaan tidak valid.', 'jalagistrasi'));
            return;
        }

        $userId      = get_current_user_id();
        $gelombangId = (int) ($this->safePost('gelombang_id'));

        if ($gelombangId <= 0) {
            $this->redirectWithError(__('Gelombang tidak dipilih.', 'jalagistrasi'));
            return;
        }

        $postData = $this->sanitizePost();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- file handling
        $filesData = $_FILES;
        $result   = $this->service->saveDraft($userId, $gelombangId, $postData, $filesData);

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0
            ? (string) get_permalink($dashboardId)
            : home_url('/dashboard-pmb/');

        if (!$result['success']) {
            $this->redirectWithErrors($result['errors'] ?? [], $postData, $gelombangId);
            return;
        }

        // Redirect ke dashboard dengan notifikasi draft tersimpan
        $userId = get_current_user_id();
        set_transient('jg_draft_saved_' . $userId, '1', 30);

        wp_safe_redirect($dashboardUrl);
        exit;
    }

    /**
     * AJAX: serve preview berkas milik pendaftar yang sedang login.
     * Hook: wp_ajax_jg_preview_berkas
     */
    public function handlePreviewBerkas(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', '', ['response' => 401]);
        }

        $berkasId = (int) ($_GET['berkas_id'] ?? 0);
        $nonce    = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if ($berkasId <= 0 || !wp_verify_nonce($nonce, 'jg_preview_berkas_' . $berkasId)) {
            wp_die('Forbidden', '', ['response' => 403]);
        }

        $berkasRepo = new BerkasRepository();
        $berkas     = $berkasRepo->findById($berkasId);

        if (!$berkas) {
            wp_die('Not found', '', ['response' => 404]);
        }

        // Admin (manage_options) boleh preview berkas siapapun
        if (!current_user_can('manage_options')) {
            $pendaftaranRepo = new PendaftaranRepository();
            $pendaftaran     = $pendaftaranRepo->findById((int) $berkas->pendaftaran_id);

            if (!$pendaftaran || (int) $pendaftaran->user_id !== get_current_user_id()) {
                wp_die('Forbidden', '', ['response' => 403]);
            }
        }

        $filePath = JG_UPLOAD_DIR . '/' . $berkas->file_path;

        if (!file_exists($filePath) || !is_file($filePath)) {
            wp_die('File not found', '', ['response' => 404]);
        }

        // Validasi mime type dari file aktual (bukan dari DB)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            wp_die('Forbidden', '', ['response' => 403]);
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . sanitize_file_name($berkas->file_name_original) . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

    /**
     * AJAX: autocomplete pencarian desa/kelurahan untuk field wilayah_autocomplete.
     * Hook: wp_ajax_jg_search_wilayah — lihat docs/arsitektur-alamat-wilayah.md.
     */
    public function handleSearchWilayah(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized'], 401);
        }

        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_search_wilayah')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        $query = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        if (mb_strlen($query) < 3) {
            wp_send_json_success([]);
        }

        $hasil = (new WilayahRepository())->search($query, 10);

        wp_send_json_success(array_map(
            static fn ($row) => ['kode' => $row->kode, 'label' => $row->nama_lengkap],
            $hasil
        ));
    }

    /**
     * URL halaman Masuk/Daftar custom — bukan wp-login.php, lihat
     * docs/arsitektur-login-register.md.
     */
    private function loginUrl(): string
    {
        $registrasiId = (int) get_option('jalagistrasi_page_registrasi', 0);

        return $registrasiId > 0 ? (string) get_permalink($registrasiId) : home_url('/daftar/');
    }

    private function redirectWithErrors(array $errors, array $savedData, int $gelombangId): void
    {
        $userId = get_current_user_id();

        set_transient('jg_form_errors_' . $userId, $errors, 60);
        set_transient('jg_form_data_' . $userId, $savedData, 60);

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0
            ? (string) get_permalink($dashboardId)
            : home_url('/dashboard-pmb/');

        $formUrl = add_query_arg([
            'action'       => 'form',
            'gelombang_id' => $gelombangId,
        ], $dashboardUrl);

        wp_safe_redirect($formUrl);
        exit;
    }

    private function redirectWithError(string $message): void
    {
        $this->redirectWithErrors([$message], [], 0);
    }

    /**
     * @return array<string,mixed>
     */
    private function sanitizePost(): array
    {
        // phpcs:disable WordPress.Security.NonceVerification
        $data = [];

        foreach ($_POST as $key => $value) {
            $key = sanitize_key($key);

            if (is_array($value)) {
                // prodi_pilihan[] atau checkbox[]
                $data[$key] = array_map('sanitize_text_field', array_map('wp_unslash', $value));
            } else {
                $data[$key] = sanitize_text_field(wp_unslash((string) $value));
            }
        }
        // phpcs:enable

        return $data;
    }

    private function safePost(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        return sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
    }
}
