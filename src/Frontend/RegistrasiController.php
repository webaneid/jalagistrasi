<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Frontend;

use Webane\Jalagistrasi\Auth\RoleManager;
use Webane\Jalagistrasi\Enum\StatusPendaftaran;
use Webane\Jalagistrasi\Repository\BerkasRepository;
use Webane\Jalagistrasi\Repository\FormJawabanRepository;
use Webane\Jalagistrasi\Repository\PembayaranRepository;
use Webane\Jalagistrasi\Repository\RekeningBankRepository;
use Webane\Jalagistrasi\Repository\TipeBerkasRepository;
use Webane\Jalagistrasi\Repository\FormSchemaRepository;
use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\PendaftaranProdiRepository;
use Webane\Jalagistrasi\Repository\PendaftaranRepository;
use Webane\Jalagistrasi\Repository\PendaftarRepository;
use Webane\Jalagistrasi\Repository\ProgramStudiRepository;
use Webane\Jalagistrasi\Service\RegistrationService;

/**
 * Shortcode handler untuk [jg_registrasi] dan [jg_dashboard].
 */
final class RegistrasiController
{
    private RegistrationService $registrationService;

    public function __construct()
    {
        $this->registrationService = new RegistrationService(
            new PendaftarRepository()
        );
    }

    /**
     * Shortcode [jg_registrasi] — form pembuatan akun.
     * Jika sudah login, redirect ke dashboard.
     */
    public function shortcodeRegistrasi(): string
    {
        if (is_user_logged_in()) {
            $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
            $dashboardUrl = $dashboardId > 0
                ? (string) get_permalink($dashboardId)
                : home_url('/dashboard-pmb/');

            wp_safe_redirect($dashboardUrl);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jg_login_nonce'])) {
            return $this->handleSubmitLogin();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jg_registrasi_nonce'])) {
            return $this->handleSubmitRegistrasi();
        }

        return $this->renderFormAuth();
    }

    /**
     * Shortcode [jg_dashboard] — semua halaman pendaftar setelah login.
     * Routing berdasarkan GET param `action`.
     */
    public function shortcodeDashboard(): string
    {
        if (!is_user_logged_in()) {
            $registrasiId = (int) get_option('jalagistrasi_page_registrasi', 0);
            $loginUrl     = $registrasiId > 0 ? (string) get_permalink($registrasiId) : home_url('/daftar/');
            wp_safe_redirect($loginUrl);
            exit;
        }

        $action = sanitize_key($_GET['action'] ?? '');

        return match ($action) {
            'pilih-gelombang' => $this->renderPilihGelombang(),
            'form'            => $this->renderFormPendaftaran(),
            // 'upload-berkas' dipensiunkan — dilebur ke halaman detail (lihat
            // docs/arsitektur-pembayaran.md, "Keputusan UI: Satu Halaman Terpadu").
            // Tetap dipetakan supaya bookmark/link lama tidak 404, langsung ke detail.
            'upload-berkas',
            'detail'          => $this->renderDetailPendaftaran(),
            'sukses'          => $this->renderSukses(),
            default           => $this->renderDashboard(),
        };
    }

    // -----------------------------------------------------------------------
    // Dashboard utama
    // -----------------------------------------------------------------------

    private function renderDashboard(): string
    {
        $userId        = get_current_user_id();
        $user          = wp_get_current_user();
        $pendaftaranRepo = new PendaftaranRepository();
        $gelombangRepo   = new GelombangRepository();

        $riwayat       = $pendaftaranRepo->findByUser($userId);
        $aktifAll      = $gelombangRepo->findAktifTerbuka();

        // Gelombang aktif yang belum didaftari user ini
        $pendaftaranIds = array_column($riwayat, 'gelombang_id');
        $tersedia = array_filter(
            $aktifAll,
            fn ($g) => !in_array((int) $g->id, array_map('intval', $pendaftaranIds), true)
        );

        $draftSavedNotif    = (bool) get_transient('jg_draft_saved_' . $userId);
        $berkasFinalizedNotif = (bool) get_transient('jg_berkas_finalized_' . $userId);
        delete_transient('jg_draft_saved_' . $userId);
        delete_transient('jg_berkas_finalized_' . $userId);

        ob_start();
        $this->loadTemplate('frontend/dashboard/index', [
            'user'            => $user,
            'riwayat'         => $riwayat,
            'tersedia'        => array_values($tersedia),
            'draftSavedNotif'      => $draftSavedNotif,
            'berkasFinalizedNotif' => $berkasFinalizedNotif,
        ]);
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Pilih gelombang
    // -----------------------------------------------------------------------

    private function renderPilihGelombang(): string
    {
        $userId          = get_current_user_id();
        $gelombangRepo   = new GelombangRepository();
        $pendaftaranRepo = new PendaftaranRepository();

        $aktifAll        = $gelombangRepo->findAktifTerbuka();
        $riwayat         = $pendaftaranRepo->findByUser($userId);
        $sudahDaftarIds  = array_map('intval', array_column($riwayat, 'gelombang_id'));

        $gelombangList = array_values(array_filter(
            $aktifAll,
            fn ($g) => !in_array((int) $g->id, $sudahDaftarIds, true)
        ));

        // Jika hanya 1 gelombang tersedia, langsung redirect ke form
        if (count($gelombangList) === 1) {
            $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
            $dashboardUrl = $dashboardId > 0
                ? (string) get_permalink($dashboardId)
                : home_url('/dashboard-pmb/');

            wp_safe_redirect(add_query_arg([
                'action'       => 'form',
                'gelombang_id' => $gelombangList[0]->id,
            ], $dashboardUrl));
            exit;
        }

        ob_start();
        $this->loadTemplate('frontend/dashboard/pilih-gelombang', [
            'gelombangList' => $gelombangList,
        ]);
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Form pendaftaran
    // -----------------------------------------------------------------------

    private function renderFormPendaftaran(): string
    {
        $gelombangId = (int) ($_GET['gelombang_id'] ?? 0);

        if ($gelombangId <= 0) {
            return $this->renderDashboard();
        }

        $gelombangRepo   = new GelombangRepository();
        $formSchemaRepo  = new FormSchemaRepository();
        $prodiRepo       = new ProgramStudiRepository();
        $pendaftarRepo   = new PendaftarRepository();
        $pendaftaranRepo = new PendaftaranRepository();

        $gelombang = $gelombangRepo->findById($gelombangId);
        if (!$gelombang || $gelombang->status !== 'aktif') {
            return $this->renderDashboard();
        }

        $userId = get_current_user_id();

        // Cek pendaftaran yang ada untuk gelombang ini
        $existingPendaftaran = $pendaftaranRepo->findByUserGelombang($userId, $gelombangId);

        // Status sudah lewat batas edit (lihat StatusPendaftaran::isEditable() —
        // docs/arsitektur-frontend-pendaftaran.md #13) → form dikunci, tidak bisa diakses lagi
        if ($existingPendaftaran && !StatusPendaftaran::from($existingPendaftaran->status)->isEditable()) {
            $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
            $dashboardUrl = $dashboardId > 0
                ? (string) get_permalink($dashboardId)
                : home_url('/dashboard-pmb/');
            wp_safe_redirect($dashboardUrl);
            exit;
        }

        $isEditMode = $existingPendaftaran && $existingPendaftaran->status !== StatusPendaftaran::Draft->value;

        $fields    = $formSchemaRepo->findByGelombang($gelombangId);
        $prodiList = $prodiRepo->findAll('aktif');
        $pendaftar = $pendaftarRepo->findByUserId($userId);
        $wpUser    = wp_get_current_user();

        // Error + saved data dari transient (setelah gagal submit).
        // Gunakan is_array() bukan (array) cast agar transient dengan nilai scalar
        // (mis. false, '', 0) tidak salah dianggap non-empty dan melewati pre-fill.
        $rawErrors    = get_transient('jg_form_errors_' . $userId);
        $rawSavedData = get_transient('jg_form_data_' . $userId);
        $errors    = is_array($rawErrors) ? $rawErrors : [];
        $savedData = is_array($rawSavedData) ? $rawSavedData : [];
        delete_transient('jg_form_errors_' . $userId);
        delete_transient('jg_form_data_' . $userId);

        // Reuse hasil query di atas, hindari query ganda
        $draftPendaftaran   = $existingPendaftaran;
        $draftPendaftaranId = null;
        $draftSaved         = false; // notifikasi kini ditampilkan di dashboard

        if ($draftPendaftaran && StatusPendaftaran::from($draftPendaftaran->status)->isEditable()) {
            $draftPendaftaranId = (int) $draftPendaftaran->id;

            // Pre-fill dari draft/pendaftaran existing jika tidak ada transient (tidak sedang repopulate error)
            if (empty($savedData)) {
                $jawabanRepo = new FormJawabanRepository();
                $rawJawaban  = $jawabanRepo->findByPendaftaran($draftPendaftaranId);
                $byFieldId   = [];
                foreach ($rawJawaban as $j) {
                    $byFieldId[(int) $j->field_id] = $j;
                }

                foreach ($fields as $field) {
                    $j = $byFieldId[(int) $field->id] ?? null;
                    if (!$j) {
                        continue;
                    }
                    if ($j->nilai_json) {
                        $savedData[$field->nama_field] = json_decode($j->nilai_json, true) ?? [];
                    } else {
                        $savedData[$field->nama_field] = $j->nilai_text;
                    }
                }

                // Pre-fill prodi dari draft
                $prodiPilRepo = new PendaftaranProdiRepository();
                $prodiDraft   = $prodiPilRepo->findByPendaftaran($draftPendaftaranId);
                $savedProdi   = [];
                foreach ($prodiDraft as $pp) {
                    $savedProdi[(int) $pp->urutan] = (int) $pp->program_studi_id;
                }
                $savedData['prodi_pilihan'] = $savedProdi;
            }
        }

        // Berkas yang sudah diupload ke draft (untuk indikator di form)
        $draftBerkas = [];
        if ($draftPendaftaranId !== null) {
            $berkasRepo = new BerkasRepository();
            foreach ($berkasRepo->findByPendaftaran($draftPendaftaranId) as $b) {
                $draftBerkas[$b->tipe_berkas] = $b;
            }
        }

        // Kelompokkan field per seksi
        $sections = [];
        foreach ($fields as $field) {
            $seksi              = $field->section_name ?: __('Lainnya', 'jalagistrasi');
            $sections[$seksi][] = $field;
        }

        ob_start();
        $this->loadTemplate('frontend/form/index', [
            'gelombang'          => $gelombang,
            'sections'           => $sections,
            'prodiList'          => $prodiList,
            'pendaftar'          => $pendaftar,
            'wpUser'             => $wpUser,
            'errors'             => $errors,
            'savedData'          => $savedData,
            'draftPendaftaranId' => $draftPendaftaranId,
            'draftSaved'         => $draftSaved,
            'draftBerkas'        => $draftBerkas,
            'isEditMode'         => $isEditMode,
        ]);
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Detail pendaftaran — satu halaman terpadu: formulir + dokumen + pembayaran
    // (lihat docs/arsitektur-pembayaran.md, "Keputusan UI: Satu Halaman Terpadu")
    // -----------------------------------------------------------------------

    private function renderDetailPendaftaran(): string
    {
        $userId        = get_current_user_id();
        $pendaftaranId = (int) ($_GET['pendaftaran_id'] ?? 0);

        if ($pendaftaranId <= 0) {
            return $this->renderDashboard();
        }

        $pendaftaranRepo = new PendaftaranRepository();
        $pendaftaran     = $pendaftaranRepo->findById($pendaftaranId);

        if (!$pendaftaran || (int) $pendaftaran->user_id !== $userId) {
            return $this->renderDashboard();
        }

        $gelombangRepo    = new GelombangRepository();
        $formSchemaRepo   = new FormSchemaRepository();
        $jawabanRepo      = new FormJawabanRepository();
        $prodiPilRepo     = new PendaftaranProdiRepository();
        $berkasRepo       = new BerkasRepository();
        $tipeBerkasRepo   = new TipeBerkasRepository();
        $pembayaranRepo   = new PembayaranRepository();
        $rekeningBankRepo = new RekeningBankRepository();

        $gelombang    = $gelombangRepo->findById((int) $pendaftaran->gelombang_id);
        $fields       = $formSchemaRepo->findByGelombang((int) $pendaftaran->gelombang_id);
        $prodiPilihan = $prodiPilRepo->findByPendaftaran($pendaftaranId);

        $rawJawaban = $jawabanRepo->findByPendaftaran($pendaftaranId);
        $byFieldId  = [];
        foreach ($rawJawaban as $j) {
            $byFieldId[(int) $j->field_id] = $j;
        }

        // Kelompokkan field + nilai per seksi, hanya field yang punya jawaban
        $sections = [];
        foreach ($fields as $field) {
            $j = $byFieldId[(int) $field->id] ?? null;
            if (!$j) {
                continue;
            }

            $nilai = $j->nilai_json ? (json_decode($j->nilai_json, true) ?? []) : $j->nilai_text;

            // Nilai mentah field wilayah cuma kode ("11.01.01.2001") — tampilkan
            // breadcrumb lengkapnya, bukan kode mentah.
            if ($field->tipe === 'wilayah_autocomplete' && is_string($nilai) && $nilai !== '') {
                $wilayah = (new \Webane\Jalagistrasi\Repository\WilayahRepository())->findByKode($nilai);
                $nilai   = $wilayah->nama_lengkap ?? $nilai;
            }

            $seksi              = $field->section_name ?: __('Lainnya', 'jalagistrasi');
            $sections[$seksi][] = ['field' => $field, 'nilai' => $nilai];
        }

        // --- Section Dokumen Persyaratan ---
        (new \Webane\Jalagistrasi\Service\DefaultTipeBerkasSeeder())->ensureDefault((int) $pendaftaran->gelombang_id);

        $tipeBerkasList = $tipeBerkasRepo->findByGelombang((int) $pendaftaran->gelombang_id);
        $tipeBerkasByKode = [];
        foreach ($tipeBerkasList as $t) {
            $tipeBerkasByKode[$t->kode] = $t;
        }

        $berkasList  = $berkasRepo->findByPendaftaran($pendaftaranId);
        $sudahUpload = [];
        foreach ($berkasList as $b) {
            $sudahUpload[$b->tipe_berkas] = $b;
        }

        $statusBolehUploadDokumen = [
            StatusPendaftaran::Submitted->value,
            StatusPendaftaran::BerkasDiupload->value,
            StatusPendaftaran::BerkasDitolak->value,
        ];
        // Status dokumen individual independen dari status besar pendaftaran —
        // panitia bisa menolak satu dokumen meski status besar sudah lanjut ke
        // fase tes/seleksi. Section upload tetap harus terbuka kalau ada dokumen
        // yang ditolak, supaya mahasiswa tidak terjebak menunggu admin mengubah
        // status besar secara manual sebelum bisa upload ulang.
        $adaBerkasDitolak = !empty(array_filter($berkasList, static fn ($b) => $b->status === 'ditolak'));
        $dokumenTerbuka   = in_array($pendaftaran->status, $statusBolehUploadDokumen, true) || $adaBerkasDitolak;

        $totalWajib   = count(array_filter($tipeBerkasList, fn ($t) => $t->is_required));
        $sudahWajib   = count(array_filter($tipeBerkasList, fn ($t) => $t->is_required && isset($sudahUpload[$t->kode])));
        $semuaLengkap = $totalWajib === $sudahWajib;

        $uploadError      = get_transient('jg_upload_error_' . $userId);
        $uploadSuccess    = get_transient('jg_upload_success_' . $userId);
        $berkasFinalized  = (bool) get_transient('jg_berkas_finalized_' . $userId);
        $formUpdated      = (bool) get_transient('jg_form_updated_' . $userId);
        delete_transient('jg_upload_error_' . $userId);
        delete_transient('jg_upload_success_' . $userId);
        delete_transient('jg_berkas_finalized_' . $userId);
        delete_transient('jg_form_updated_' . $userId);

        // Tombol "Edit Formulir" (beda dari "Lanjutkan Mengisi Formulir" yang khusus
        // draft) — tampil untuk status editable selain draft. Lihat
        // StatusPendaftaran::isEditable() & docs/arsitektur-frontend-pendaftaran.md #13.
        $formBolehDiedit = $pendaftaran->status !== StatusPendaftaran::Draft->value
            && StatusPendaftaran::from($pendaftaran->status)->isEditable();

        // --- Section Bukti Pembayaran ---
        $rekeningAktif = $rekeningBankRepo->findAllAktif();
        $pembayaran    = $pembayaranRepo->findByPendaftaran($pendaftaranId);

        $statusBolehUploadPembayaran = [
            StatusPendaftaran::BerkasDiverifikasi->value,
            StatusPendaftaran::PembayaranDitolak->value,
        ];
        $pembayaranTerbuka = in_array($pendaftaran->status, $statusBolehUploadPembayaran, true);

        $totalSeharusnya = $pendaftaran->kode_unik_pembayaran !== null
            ? (float) $gelombang->biaya_pendaftaran + (int) $pendaftaran->kode_unik_pembayaran
            : null;

        $pembayaranError   = get_transient('jg_pembayaran_error_' . $userId);
        $pembayaranSuccess = get_transient('jg_pembayaran_success_' . $userId);
        delete_transient('jg_pembayaran_error_' . $userId);
        delete_transient('jg_pembayaran_success_' . $userId);

        ob_start();
        $this->loadTemplate('frontend/detail/index', [
            'pendaftaran'       => $pendaftaran,
            'gelombang'         => $gelombang,
            'sections'          => $sections,
            'prodiPilihan'      => $prodiPilihan,
            'berkasList'        => $berkasList,
            'tipeBerkasByKode'  => $tipeBerkasByKode,
            'tipeBerkasList'    => $tipeBerkasList,
            'sudahUpload'       => $sudahUpload,
            'dokumenTerbuka'    => $dokumenTerbuka,
            'semuaLengkap'      => $semuaLengkap,
            'totalWajib'        => $totalWajib,
            'sudahWajib'        => $sudahWajib,
            'uploadError'       => is_string($uploadError) ? $uploadError : '',
            'uploadSuccess'     => is_string($uploadSuccess) ? $uploadSuccess : '',
            'berkasFinalized'   => $berkasFinalized,
            'rekeningAktif'     => $rekeningAktif,
            'pembayaran'        => $pembayaran,
            'pembayaranTerbuka' => $pembayaranTerbuka,
            'totalSeharusnya'   => $totalSeharusnya,
            'pembayaranError'   => is_string($pembayaranError) ? $pembayaranError : '',
            'pembayaranSuccess' => (bool) $pembayaranSuccess,
            'formUpdated'       => $formUpdated,
            'formBolehDiedit'   => $formBolehDiedit,
        ]);
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Halaman sukses
    // -----------------------------------------------------------------------

    private function renderSukses(): string
    {
        $nomor           = sanitize_text_field($_GET['ref'] ?? '');
        $userId          = get_current_user_id();
        $pendaftaranRepo = new PendaftaranRepository();
        $prodiPilRepo    = new PendaftaranProdiRepository();

        $pendaftaran = null;
        $prodiPilihan = [];

        if ($nomor !== '') {
            // Cari by nomor, pastikan milik user ini
            $found = $pendaftaranRepo->findByUser($userId);
            foreach ($found as $p) {
                if ($p->nomor_pendaftaran === $nomor) {
                    $pendaftaran  = $p;
                    $prodiPilihan = $prodiPilRepo->findByPendaftaran((int) $p->id);
                    break;
                }
            }
        }

        ob_start();
        $this->loadTemplate('frontend/form/sukses', [
            'pendaftaran'  => $pendaftaran,
            'prodiPilihan' => $prodiPilihan,
            'namaInstitusi' => (string) get_option('jalagistrasi_nama_institusi', ''),
        ]);
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Form buat akun (registrasi) — tidak berubah dari sebelumnya
    // -----------------------------------------------------------------------

    private function handleSubmitRegistrasi(): string
    {
        if (!isset($_POST['jg_registrasi_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['jg_registrasi_nonce'])), 'jg_registrasi')
        ) {
            return $this->renderFormAuth(
                activeTab: 'register',
                registerErrors: [__('Permintaan tidak valid. Silakan coba lagi.', 'jalagistrasi')]
            );
        }

        // phpcs:disable WordPress.Security.NonceVerification
        $postData = [
            'nama_lengkap'        => wp_unslash($_POST['nama_lengkap'] ?? ''),
            'email'               => wp_unslash($_POST['email'] ?? ''),
            'nomor_wa'            => wp_unslash($_POST['nomor_wa'] ?? ''),
            'password'            => $_POST['password'] ?? '',
            'konfirmasi_password' => $_POST['konfirmasi_password'] ?? '',
        ];
        // phpcs:enable

        $result = $this->registrationService->register($postData);

        if (!$result['success']) {
            return $this->renderFormAuth(
                activeTab: 'register',
                registerErrors: $result['errors'],
                registerOld: $postData
            );
        }

        $this->registrationService->autoLogin(
            sanitize_email($postData['email']),
            $postData['password']
        );

        $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
        $dashboardUrl = $dashboardId > 0
            ? (string) get_permalink($dashboardId)
            : home_url('/dashboard-pmb/');

        wp_safe_redirect($dashboardUrl);
        exit;
    }

    /**
     * Login lewat form custom (bukan wp-login.php) — lihat docs/arsitektur-login-register.md.
     */
    private function handleSubmitLogin(): string
    {
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));

        if (!isset($_POST['jg_login_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['jg_login_nonce'])), 'jg_login')
        ) {
            return $this->renderFormAuth(
                activeTab: 'login',
                loginErrors: [__('Permintaan tidak valid. Silakan coba lagi.', 'jalagistrasi')],
                oldLoginEmail: $email
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification
        $password = (string) ($_POST['password'] ?? '');

        $user = wp_signon([
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            return $this->renderFormAuth(
                activeTab: 'login',
                loginErrors: [__('Email atau password salah.', 'jalagistrasi')],
                oldLoginEmail: $email
            );
        }

        wp_set_current_user($user->ID);

        if (RoleManager::currentUserHasRole(RoleManager::ROLE_PENDAFTAR)) {
            $dashboardId  = (int) get_option('jalagistrasi_page_dashboard', 0);
            $redirectUrl  = $dashboardId > 0 ? (string) get_permalink($dashboardId) : home_url('/dashboard-pmb/');
        } else {
            // Staff/administrator — masuk ke wp-admin seperti biasa.
            $redirectUrl = admin_url();
        }

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * Render halaman gabungan Masuk + Daftar Baru (tab). Lihat docs/arsitektur-login-register.md.
     *
     * @param list<string>          $loginErrors
     * @param list<string>          $registerErrors
     * @param array<string, string> $registerOld
     */
    private function renderFormAuth(
        string $activeTab = 'login',
        array $loginErrors = [],
        string $oldLoginEmail = '',
        array $registerErrors = [],
        array $registerOld = []
    ): string {
        ob_start();
        $this->loadTemplate('auth/login-register', [
            'activeTab'      => $activeTab,
            'loginErrors'    => $loginErrors,
            'oldLoginEmail'  => $oldLoginEmail,
            'loginNonce'     => wp_nonce_field('jg_login', 'jg_login_nonce', true, false),
            'registerErrors' => $registerErrors,
            'registerOld'    => $registerOld,
            'registerNonce'  => wp_nonce_field('jg_registrasi', 'jg_registrasi_nonce', true, false),
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
