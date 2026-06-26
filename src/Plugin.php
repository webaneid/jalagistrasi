<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi;

use Webane\Jalagistrasi\Admin\AdminMenu;
use Webane\Jalagistrasi\Admin\FormBuilderController;
use Webane\Jalagistrasi\Admin\GelombangController;
use Webane\Jalagistrasi\Admin\PengaturanController;
use Webane\Jalagistrasi\Admin\ProgramStudiController;
use Webane\Jalagistrasi\Auth\LoginHandler;
use Webane\Jalagistrasi\Frontend\InfoPendaftaranController;
use Webane\Jalagistrasi\Frontend\PendaftaranController;
use Webane\Jalagistrasi\Frontend\RegistrasiController;

/**
 * Bootstrap utama plugin. Singleton — hanya satu instance yang boleh ada.
 *
 * Tanggung jawab class ini terbatas pada:
 * - Mendefinisikan konstanta plugin.
 * - Mendaftarkan hook WordPress yang dibutuhkan modul lain.
 * - Menjalankan migrasi DB jika versi plugin berubah.
 *
 * Tidak ada logika bisnis di sini.
 */
final class Plugin
{
    public const VERSION     = '0.1.2';
    public const DB_VERSION  = '6';
    public const SLUG        = 'jalagistrasi';
    public const TEXT_DOMAIN = 'jalagistrasi';

    private static ?self $instance = null;

    private function __construct()
    {
        self::defineConstants();
        $this->registerHooks();
        self::buildUpdateChecker();
    }

    /**
     * Entry point yang dipanggil dari hook `plugins_loaded`.
     */
    public static function boot(): void
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    /**
     * Definisikan konstanta plugin. PUBLIC STATIC + idempotent (guard `defined()`
     * per konstanta) supaya bisa dipanggil lebih awal dari jalagistrasi.php —
     * SEBELUM register_activation_hook(), bukan cuma lewat boot() di 'plugins_loaded'.
     *
     * Kenapa ini penting: register_activation_hook() (Installer::activate(), yang
     * import data wilayah dkk) dijalankan WordPress LEBIH AWAL dari 'plugins_loaded'
     * pada request aktivasi plugin. Kalau konstanta cuma didefinisikan di boot(),
     * Installer::activate() akan fatal error "Undefined constant JG_PLUGIN_DIR"
     * tepat saat plugin pertama kali diaktifkan di server baru (lihat percakapan
     * "fatal error saat aktivasi di server" — kejadian nyata, bukan teoretis).
     */
    public static function defineConstants(): void
    {
        if (defined('JG_VERSION')) {
            return;
        }

        define('JG_VERSION',     self::VERSION);
        define('JG_DB_VERSION',  self::DB_VERSION);
        define('JG_PLUGIN_FILE', dirname(__DIR__) . '/jalagistrasi.php');
        define('JG_PLUGIN_DIR',  plugin_dir_path(JG_PLUGIN_FILE));
        define('JG_PLUGIN_URL',  plugin_dir_url(JG_PLUGIN_FILE));
        define('JG_UPLOAD_DIR',  WP_CONTENT_DIR . '/jalagistrasi-uploads');
        define('JG_UPLOAD_URL',  WP_CONTENT_URL . '/jalagistrasi-uploads');
    }

    private function registerHooks(): void
    {
        add_action('init', [$this, 'loadTextDomain']);
        add_action('init', [$this, 'registerShortcodes']);
        add_action('init', [$this, 'maybeCreatePages']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_filter('script_loader_tag', [$this, 'addTypeModuleToPluginScripts'], 10, 3);
        add_filter('template_include', [$this, 'maybeUseBlankTemplate']);

        $loginHandler = new LoginHandler();
        add_filter('login_redirect', [$loginHandler, 'redirectAfterLogin'], 10, 3);
        add_action('admin_init', [$loginHandler, 'blockPendaftarFromAdmin']);

        $adminMenu = new AdminMenu();
        add_action('admin_menu', [$adminMenu, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$adminMenu, 'enqueueAdminAssets']);

        $gelombangCtrl = new GelombangController();
        add_action('admin_post_jg_save_gelombang', [$gelombangCtrl, 'handleSave']);
        add_action('admin_post_jg_delete_gelombang', [$gelombangCtrl, 'handleDelete']);

        $prodiCtrl = new ProgramStudiController();
        add_action('admin_post_jg_save_program_studi', [$prodiCtrl, 'handleSave']);
        add_action('admin_post_jg_delete_program_studi', [$prodiCtrl, 'handleDelete']);

        $formBuilderCtrl = new FormBuilderController();
        add_action('admin_post_jg_save_form_field',    [$formBuilderCtrl, 'handleSave']);
        add_action('admin_post_jg_delete_form_field', [$formBuilderCtrl, 'handleDelete']);
        add_action('wp_ajax_jg_reorder_fields',        [$formBuilderCtrl, 'handleReorder']);
        add_action('admin_post_jg_seed_default_form',  [$formBuilderCtrl, 'handleSeedDefault']);

        $pengaturanCtrl = new PengaturanController();
        add_action('admin_post_jg_save_pengaturan', [$pengaturanCtrl, 'handleSave']);
        add_action('admin_post_jg_sync_wilayah',    [$pengaturanCtrl, 'handleSyncWilayah']);
        add_action('admin_post_jg_check_update',    [$pengaturanCtrl, 'handleCheckUpdate']);

        $pendaftaranCtrl = new PendaftaranController();
        add_action('admin_post_jg_submit_pendaftaran',    [$pendaftaranCtrl, 'handleSubmit']);
        add_action('admin_post_jg_save_draft_pendaftaran',      [$pendaftaranCtrl, 'handleSaveDraft']);
        add_action('wp_ajax_jg_preview_berkas',                [$pendaftaranCtrl, 'handlePreviewBerkas']);
        add_action('wp_ajax_jg_search_wilayah',                [$pendaftaranCtrl, 'handleSearchWilayah']);
        $pendaftarCtrl = new \Webane\Jalagistrasi\Admin\PendaftarController();
        add_action('admin_post_jg_update_status_pendaftaran', [$pendaftarCtrl, 'handleUpdateStatus']);
        add_action('admin_post_jg_verify_berkas',             [$pendaftarCtrl, 'handleVerifyBerkas']);
        add_action('admin_post_jg_export_pendaftar',          [$pendaftarCtrl, 'handleExportExcel']);
        add_action('wp_ajax_jg_export_preview_berkas',        [$pendaftarCtrl, 'handleExportPreviewBerkas']);
        add_action('wp_ajax_jg_export_preview_pembayaran',    [$pendaftarCtrl, 'handleExportPreviewPembayaran']);

        $tipeBerkasCtrl = new \Webane\Jalagistrasi\Admin\TipeBerkasController();
        add_action('admin_post_jg_save_tipe_berkas',   [$tipeBerkasCtrl, 'handleSave']);
        add_action('admin_post_jg_delete_tipe_berkas', [$tipeBerkasCtrl, 'handleDelete']);

        add_action('admin_post_jg_upload_berkas_item', [$pendaftaranCtrl, 'handleUploadBerkasItem']);
        add_action('admin_post_jg_finalize_berkas',    [$pendaftaranCtrl, 'handleFinalizeBerkas']);

        add_action('admin_post_jg_upload_pembayaran', [$pendaftaranCtrl, 'handleUploadPembayaran']);
        add_action('wp_ajax_jg_preview_pembayaran',   [$pendaftaranCtrl, 'handlePreviewPembayaran']);

        $rekeningBankCtrl = new \Webane\Jalagistrasi\Admin\RekeningBankController();
        add_action('admin_post_jg_save_rekening_bank',   [$rekeningBankCtrl, 'handleSave']);
        add_action('admin_post_jg_delete_rekening_bank', [$rekeningBankCtrl, 'handleDelete']);

        $tahunAjaranCtrl = new \Webane\Jalagistrasi\Admin\TahunAjaranController();
        add_action('admin_post_jg_save_tahun_ajaran',   [$tahunAjaranCtrl, 'handleSave']);
        add_action('admin_post_jg_delete_tahun_ajaran', [$tahunAjaranCtrl, 'handleDelete']);

        $this->runMigrationsIfNeeded();
    }

    /**
     * Cek update plugin dari GitHub Releases (repo publik webaneid/jalagistrasi).
     * Lihat docs/arsitektur-update-plugin.md — paket update HARUS berupa Release
     * asset custom (zip yang sudah di-build, vendor/ included), bukan source-code-zip
     * otomatis GitHub (itu tidak berisi vendor/ karena di-gitignore di repo).
     *
     * Versi yang dibandingkan PUC diambil dari header `Version:` di jalagistrasi.php
     * — WAJIB disinkronkan manual dengan konstanta self::VERSION tiap rilis
     * (lihat checklist rilis di docs/arsitektur-update-plugin.md §6).
     *
     * Method ini PUBLIC STATIC (bukan dipanggil sekali saja di constructor) supaya
     * PengaturanController bisa bikin instance yang sama untuk tombol "Cek Update
     * Sekarang" & tampilan versi terbaru di tab Update — satu sumber konfigurasi,
     * tidak duplikat parameter repo/slug di dua tempat.
     *
     * SINGLETON per request (static cache di $updateCheckerInstance) — WAJIB,
     * bukan sekadar optimisasi. PUC menolak keras instansiasi dobel untuk slug
     * yang sama dalam satu request (lempar E_USER_ERROR "Slugs must be unique"
     * kalau WP_DEBUG aktif) — kejadian nyata waktu method ini dipanggil dari
     * boot() DAN renderTabUpdate() dalam request yang sama. Lihat percakapan
     * "critical error di tab Update".
     *
     * @return \YahnisElsts\PluginUpdateChecker\v5\Plugin\UpdateChecker|null null kalau library belum terpasang
     */
    public static function buildUpdateChecker(): ?object
    {
        static $updateCheckerInstance = null;

        if ($updateCheckerInstance !== null) {
            return $updateCheckerInstance;
        }

        if (!class_exists(\YahnisElsts\PluginUpdateChecker\v5\PucFactory::class)) {
            return null;
        }

        $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/webaneid/jalagistrasi/',
            JG_PLUGIN_FILE,
            self::SLUG
        );

        $updateChecker->setBranch('main');
        $updateChecker->getVcsApi()->enableReleaseAssets('/\.zip($|[?&#])/i');

        $updateCheckerInstance = $updateChecker;

        return $updateCheckerInstance;
    }

    /**
     * Buat halaman WordPress yang dibutuhkan plugin jika belum ada.
     * Dipanggil via hook 'init' — bukan hanya saat aktivasi — agar
     * tetap berjalan meskipun plugin di-deploy ulang tanpa re-aktivasi.
     * Guard via option 'jalagistrasi_pages_created' mencegah duplikasi.
     */
    /**
     * Tambahkan type="module" pada script plugin agar ES module import berjalan di browser.
     * WordPress tidak menambahkan atribut ini secara otomatis.
     */
    public function addTypeModuleToPluginScripts(string $tag, string $handle): string
    {
        $pluginHandles = ['jalagistrasi-app', 'jalagistrasi-admin'];

        if (!in_array($handle, $pluginHandles, true)) {
            return $tag;
        }

        return str_replace(' src=', ' type="module" src=', $tag);
    }

    /**
     * Halaman Masuk/Daftar dan Dashboard pendaftar dirender tanpa header/footer
     * tema — supaya terasa seperti aplikasi sendiri, bukan halaman web biasa.
     * wp_head()/wp_footer() tetap jalan (lihat templates/auth/page-blank.php) —
     * cuma header.php/footer.php tema yang dilewati.
     */
    public function maybeUseBlankTemplate(string $template): string
    {
        $blankPageIds = [
            (int) get_option('jalagistrasi_page_registrasi', 0),
            (int) get_option('jalagistrasi_page_dashboard', 0),
        ];

        foreach ($blankPageIds as $pageId) {
            if ($pageId > 0 && is_page($pageId)) {
                return JG_PLUGIN_DIR . 'templates/auth/page-blank.php';
            }
        }

        return $template;
    }

    public function maybeCreatePages(): void
    {
        // createRequiredPages() sudah idempotent per-halaman (skip kalau sudah ada) —
        // dipanggil tiap 'init' tanpa guard tunggal supaya halaman BARU yang ditambah
        // di rilis berikutnya tetap otomatis terbuat di site yang sudah lama aktif.
        Installer::createRequiredPages();
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(plugin_basename(JG_PLUGIN_FILE)) . '/languages'
        );
    }

    public function registerShortcodes(): void
    {
        $controller = new RegistrasiController();
        add_shortcode('jg_registrasi', [$controller, 'shortcodeRegistrasi']);
        add_shortcode('jg_dashboard',  [$controller, 'shortcodeDashboard']);

        $infoCtrl = new InfoPendaftaranController();
        add_shortcode('jg_info_pendaftaran', [$infoCtrl, 'shortcodeInfoPendaftaran']);
    }

    /**
     * Enqueue CSS dan JS hanya di halaman yang memakai shortcode plugin.
     * Tidak membebani halaman lain dengan asset yang tidak diperlukan.
     */
    public function enqueueFrontendAssets(): void
    {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $pluginShortcodes = ['jg_registrasi', 'jg_dashboard', 'jg_info_pendaftaran'];
        $hasShortcode     = false;

        foreach ($pluginShortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $hasShortcode = true;
                break;
            }
        }

        if (!$hasShortcode) {
            return;
        }

        wp_enqueue_style(
            'jalagistrasi-app',
            JG_PLUGIN_URL . 'assets/css/app.css',
            [],
            JG_VERSION
        );
        wp_add_inline_style('jalagistrasi-app', $this->buildBrandColorCss());

        wp_enqueue_script(
            'jalagistrasi-app',
            JG_PLUGIN_URL . 'assets/js/app.js',
            [],
            JG_VERSION,
            true // footer
        );
    }

    /**
     * Bangun CSS override untuk skala warna brand primer (--jg-color-*) dan sekunder
     * (--jg-secondary-*) berdasarkan warna yang diset admin di Pengaturan.
     * Lihat docs/arsitektur-color-palette.md.
     */
    private function buildBrandColorCss(): string
    {
        $generator = new \Webane\Jalagistrasi\Service\ColorPaletteGenerator();

        $vars = '';
        $vars .= $this->buildScaleVars('--jg-color-', get_option('jalagistrasi_warna_brand', '#2563eb'), '#2563eb', $generator);
        $vars .= $this->buildScaleVars('--jg-secondary-', get_option('jalagistrasi_warna_sekunder', '#f59e0b'), '#f59e0b', $generator);

        return ":root{{$vars}}";
    }

    private function buildScaleVars(string $prefix, mixed $warna, string $fallback, \Webane\Jalagistrasi\Service\ColorPaletteGenerator $generator): string
    {
        $warna = (string) $warna;

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $warna)) {
            $warna = $fallback;
        }

        $vars = '';
        foreach ($generator->generateScale($warna) as $shade => $hex) {
            $vars .= "{$prefix}{$shade}: {$hex};";
        }

        return $vars;
    }

    /**
     * Jalankan migrasi DB jika versi schema berubah sejak aktivasi terakhir.
     */
    private function runMigrationsIfNeeded(): void
    {
        $installedDbVersion = get_option('jalagistrasi_db_version', '0');

        if ($installedDbVersion !== self::DB_VERSION) {
            Installer::createTables();

            if (version_compare($installedDbVersion, '3', '<')) {
                $this->migratePasFotoKeTipeBerkas();
            }

            if (version_compare($installedDbVersion, '5', '<')) {
                $this->migrateTahunAjaran();
            }

            if (version_compare($installedDbVersion, '6', '<')) {
                (new \Webane\Jalagistrasi\Service\WilayahImportService())->import();
            }

            update_option('jalagistrasi_db_version', self::DB_VERSION);
        }
    }

    /**
     * Migrasi satu kali: Pas Foto pindah dari field formulir dinamis (file_upload)
     * menjadi tipe berkas default otomatis di Step 3 untuk setiap gelombang yang
     * sudah ada, supaya tidak diminta dua kali (di formulir & di upload berkas).
     */
    private function migratePasFotoKeTipeBerkas(): void
    {
        global $wpdb;

        // Hapus field 'foto' lama dari formulir dinamis (jika ada).
        $wpdb->delete(
            $wpdb->prefix . 'jg_form_field',
            ['nama_field' => 'foto', 'tipe' => 'file_upload'],
            ['%s', '%s']
        );

        // Pastikan setiap gelombang yang sudah ada punya tipe berkas Pas Foto default.
        $gelombangIds = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}jg_gelombang");
        $seeder       = new \Webane\Jalagistrasi\Service\DefaultTipeBerkasSeeder();

        foreach ($gelombangIds as $gelombangId) {
            $seeder->ensureDefault((int) $gelombangId);
        }
    }

    /**
     * Migrasi satu kali: pindahkan teks bebas `jg_gelombang.tahun_akademik` jadi
     * entitas tersendiri `jg_tahun_ajaran` + FK `jg_gelombang.tahun_ajaran_id`,
     * lalu hapus kolom lama. Lihat docs/arsitektur-tahun-ajaran.md.
     */
    private function migrateTahunAjaran(): void
    {
        global $wpdb;

        $gelombangTable = $wpdb->prefix . 'jg_gelombang';
        $tahunTable     = $wpdb->prefix . 'jg_tahun_ajaran';

        // Kolom lama sudah didrop di run sebelumnya — tidak ada yang perlu dimigrasi.
        $kolomAda = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'tahun_akademik'",
            DB_NAME,
            $gelombangTable
        ));

        if (!$kolomAda) {
            return;
        }

        // 1. Insert tiap nilai distinct sebagai Tahun Ajaran baru (skip yang sudah ada).
        $nilaiDistinct = $wpdb->get_col("SELECT DISTINCT tahun_akademik FROM {$gelombangTable} WHERE tahun_akademik != ''");

        foreach ($nilaiDistinct as $nama) {
            $sudahAda = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tahunTable} WHERE nama = %s",
                $nama
            ));

            if (!$sudahAda) {
                $wpdb->insert($tahunTable, ['nama' => $nama, 'status' => 'nonaktif'], ['%s', '%s']);
            }
        }

        // 2. Backfill FK berdasarkan nama yang cocok.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query(
            "UPDATE {$gelombangTable} g
             JOIN {$tahunTable} ta ON ta.nama = g.tahun_akademik
             SET g.tahun_ajaran_id = ta.id
             WHERE g.tahun_ajaran_id IS NULL"
        );

        // 3. Drop kolom lama — dbDelta tidak bisa drop kolom, jadi manual.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query("ALTER TABLE {$gelombangTable} DROP COLUMN tahun_akademik");
    }
}
