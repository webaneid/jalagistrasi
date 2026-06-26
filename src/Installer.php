<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi;

/**
 * Mengelola instalasi, aktivasi, deaktivasi, dan uninstall plugin.
 *
 * Aturan penulisan SQL untuk dbDelta (WAJIB — jangan ubah formatnya):
 * - Setiap kolom/key pada baris sendiri.
 * - Dua spasi antara nama kolom dan tipe data.
 * - PRIMARY KEY ditulis sebagai baris terpisah.
 * - Tidak ada ENUM — gunakan VARCHAR.
 * - Tidak ada FOREIGN KEY constraint di SQL (WordPress convention).
 * - Tidak ada trailing comma setelah baris terakhir sebelum closing paren.
 */
final class Installer
{
    /**
     * Dipanggil oleh register_activation_hook saat plugin diaktivasi.
     */
    public static function activate(): void
    {
        self::createUploadDirectory();
        self::createTables();
        self::createRoles();
        self::createRequiredPages();
        (new \Webane\Jalagistrasi\Service\WilayahImportService())->import();

        update_option('jalagistrasi_db_version', Plugin::DB_VERSION);

        // Flush rewrite rules agar halaman custom terdaftar dengan benar.
        flush_rewrite_rules();
    }

    /**
     * Dipanggil oleh register_deactivation_hook.
     * Tidak menghapus data — uninstall dilakukan via uninstall.php terpisah.
     */
    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Membuat atau memperbarui semua custom table menggunakan dbDelta.
     * Aman dipanggil berulang kali — dbDelta hanya ALTER jika ada perubahan skema.
     */
    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        // Prefix ganda: {wp_prefix}jg_  — contoh: wp_jg_gelombang
        $p = $wpdb->prefix . 'jg_';

        $tables = self::buildTableSql($p, $charset);

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * Membangun array SQL DDL untuk setiap tabel.
     * Dipisah agar mudah dibaca dan diuji secara independen.
     *
     * @return list<string>
     */
    private static function buildTableSql(string $p, string $charset): array
    {
        return [
            // ----------------------------------------------------------------
            // 0. Tahun ajaran — entitas tersendiri, gelombang merujuk ke sini
            //    lewat tahun_ajaran_id (lihat docs/arsitektur-tahun-ajaran.md).
            //    Kolom tahun_akademik di tabel gelombang (di bawah) SUDAH DIHAPUS
            //    dari DDL ini — untuk instalasi lama, didrop manual via migrasi
            //    di Plugin::migrateTahunAjaran().
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}tahun_ajaran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'nonaktif',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nama (nama),
  KEY idx_status (status)
) $charset;",

            // ----------------------------------------------------------------
            // 1. Gelombang pendaftaran
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}gelombang (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(200) NOT NULL,
  tahun_ajaran_id BIGINT UNSIGNED DEFAULT NULL,
  tanggal_buka DATETIME NOT NULL,
  tanggal_tutup DATETIME NOT NULL,
  max_pilihan_prodi TINYINT UNSIGNED NOT NULL DEFAULT 2,
  biaya_pendaftaran DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'nonaktif',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_tanggal (tanggal_buka, tanggal_tutup),
  KEY idx_tahun_ajaran_id (tahun_ajaran_id)
) $charset;",

            // ----------------------------------------------------------------
            // 2. Program studi
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}program_studi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(200) NOT NULL,
  kode VARCHAR(20) NOT NULL,
  deskripsi TEXT,
  kuota INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'aktif',
  urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kode (kode),
  KEY idx_status_urutan (status, urutan)
) $charset;",

            // ----------------------------------------------------------------
            // 3. Profil pendaftar (satu baris per WP user)
            //    UNIQUE KEY pada nomor_wa, nik, nisn untuk menjamin tidak duplikat
            //    di level DB — tidak bisa diandalkan dari wp_usermeta.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}pendaftar (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  nomor_wa VARCHAR(20) NOT NULL,
  nik VARCHAR(16) DEFAULT NULL,
  nisn VARCHAR(10) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_id (user_id),
  UNIQUE KEY uq_nomor_wa (nomor_wa),
  UNIQUE KEY uq_nik (nik),
  UNIQUE KEY uq_nisn (nisn)
) $charset;",

            // ----------------------------------------------------------------
            // 4. Record pendaftaran utama
            //    UNIQUE KEY uq_user_gelombang: satu user hanya boleh 1 pendaftaran
            //    per gelombang.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}pendaftaran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  gelombang_id BIGINT UNSIGNED NOT NULL,
  nomor_pendaftaran VARCHAR(50) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  catatan_panitia TEXT,
  kode_unik_pembayaran SMALLINT UNSIGNED DEFAULT NULL,
  verifikasi_token VARCHAR(64) DEFAULT NULL,
  submitted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nomor_pendaftaran (nomor_pendaftaran),
  UNIQUE KEY uq_user_gelombang (user_id, gelombang_id),
  KEY idx_gelombang_status (gelombang_id, status),
  KEY idx_user_id (user_id),
  KEY idx_status (status)
) $charset;",

            // ----------------------------------------------------------------
            // 5. Pilihan prodi per pendaftaran
            //    Jumlah baris per pendaftaran_id dibatasi oleh
            //    jg_gelombang.max_pilihan_prodi — divalidasi di PHP.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}pendaftaran_prodi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pendaftaran_id BIGINT UNSIGNED NOT NULL,
  program_studi_id BIGINT UNSIGNED NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pendaftaran_urutan (pendaftaran_id, urutan),
  UNIQUE KEY uq_pendaftaran_prodi (pendaftaran_id, program_studi_id),
  KEY idx_program_studi_id (program_studi_id)
) $charset;",

            // ----------------------------------------------------------------
            // 6. Definisi field formulir (skema per gelombang)
            //    is_core = 1: field inti tidak bisa dihapus admin.
            //    konfigurasi: JSON — options, validasi, conditional logic.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}form_field (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gelombang_id BIGINT UNSIGNED NOT NULL,
  section_name VARCHAR(100) DEFAULT NULL,
  nama_field VARCHAR(100) NOT NULL,
  label VARCHAR(200) NOT NULL,
  tipe VARCHAR(50) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  is_core TINYINT(1) NOT NULL DEFAULT 0,
  urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  konfigurasi JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gelombang_nama (gelombang_id, nama_field),
  KEY idx_gelombang_urutan (gelombang_id, urutan)
) $charset;",

            // ----------------------------------------------------------------
            // 7. Jawaban pendaftar (EAV)
            //    nilai_text: single-value (text, date, select, radio, dsb.)
            //    nilai_json: multi-value (checkbox) atau tipe kompleks.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}form_jawaban (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pendaftaran_id BIGINT UNSIGNED NOT NULL,
  field_id BIGINT UNSIGNED NOT NULL,
  nilai_text TEXT DEFAULT NULL,
  nilai_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pendaftaran_field (pendaftaran_id, field_id),
  KEY idx_pendaftaran_id (pendaftaran_id),
  KEY idx_field_id (field_id)
) $charset;",

            // ----------------------------------------------------------------
            // 8. Berkas yang diupload (unified file storage)
            //    Menampung semua file: KTP, KK, Ijazah, Foto, Bukti Bayar.
            //    File TIDAK disimpan di WP Media Library — disimpan di direktori
            //    privat yang diproteksi .htaccess, diakses via PHP endpoint.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}berkas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pendaftaran_id BIGINT UNSIGNED NOT NULL,
  tipe_berkas VARCHAR(50) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_name_original VARCHAR(255) NOT NULL,
  file_name_stored VARCHAR(255) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  catatan TEXT DEFAULT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_at DATETIME DEFAULT NULL,
  verified_by BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pendaftaran_id (pendaftaran_id),
  KEY idx_tipe_status (tipe_berkas, status),
  KEY idx_status (status)
) $charset;",

            // ----------------------------------------------------------------
            // 9. Bukti pembayaran biaya pendaftaran (lihat docs/arsitektur-pembayaran.md)
            //    Cuma 1 bukti aktif per pendaftaran — re-upload = hapus baris lama, insert baru.
            //    TIDAK ada status/verifikasi terpisah di sini — keputusan terima/tolak
            //    pembayaran lewat transisi jg_pendaftaran.status (panel Update Status admin),
            //    karena granularitasnya 1:1 dengan pendaftaran (beda dengan jg_berkas yang
            //    banyak file per pendaftaran).
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}pembayaran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pendaftaran_id BIGINT UNSIGNED NOT NULL,
  rekening_bank_id BIGINT UNSIGNED NOT NULL,
  jumlah DECIMAL(12,2) NOT NULL,
  nama_pengirim VARCHAR(150) DEFAULT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_name_original VARCHAR(255) NOT NULL,
  file_name_stored VARCHAR(255) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pendaftaran (pendaftaran_id),
  KEY idx_pendaftaran_id (pendaftaran_id),
  KEY idx_rekening_bank_id (rekening_bank_id)
) $charset;",

            // ----------------------------------------------------------------
            // 9b. Rekening tujuan transfer — bisa lebih dari satu, berlaku untuk
            //     semua gelombang (cuma nominal biaya yang beda per gelombang).
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}rekening_bank (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_bank VARCHAR(100) NOT NULL,
  nomor_rekening VARCHAR(50) NOT NULL,
  nama_pemilik VARCHAR(150) NOT NULL,
  is_aktif TINYINT UNSIGNED NOT NULL DEFAULT 1,
  urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_is_aktif (is_aktif)
) $charset;",

            // ----------------------------------------------------------------
            // 10. Tipe berkas yang wajib diupload per gelombang (step 3)
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}tipe_berkas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gelombang_id BIGINT UNSIGNED NOT NULL,
  kode  VARCHAR(50) NOT NULL,
  label  VARCHAR(150) NOT NULL,
  keterangan  TEXT DEFAULT NULL,
  is_required  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  max_size_kb  INT UNSIGNED NOT NULL DEFAULT 2048,
  urutan  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gelombang_kode (gelombang_id, kode),
  KEY idx_gelombang_urutan (gelombang_id, urutan)
) $charset;",

            // ----------------------------------------------------------------
            // 11. Audit trail perubahan status
            //     Log ini tidak pernah dihapus.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pendaftaran_id BIGINT UNSIGNED NOT NULL,
  status_lama VARCHAR(50) DEFAULT NULL,
  status_baru VARCHAR(50) NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  catatan TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pendaftaran_id (pendaftaran_id),
  KEY idx_created_at (created_at)
) $charset;",

            // ----------------------------------------------------------------
            // 12. Data master wilayah Indonesia (provinsi/kabupaten/kecamatan/desa)
            //     Satu tabel flat — hierarki ada di dalam string `kode` (format
            //     Kemendagri, dipisah titik: "11" / "11.01" / "11.01.01" / "11.01.01.2001").
            //     level: 1=provinsi, 2=kabupaten/kota, 3=kecamatan, 4=desa/kelurahan.
            //     nama_lengkap hanya diisi untuk level=4 (breadcrumb lengkap, dipakai
            //     untuk pencarian autocomplete) — lihat docs/arsitektur-alamat-wilayah.md.
            //     Diisi oleh WilayahImportService dari data/wilayah.csv, bukan input admin.
            // ----------------------------------------------------------------
            "CREATE TABLE {$p}wilayah (
  kode VARCHAR(13) NOT NULL,
  nama VARCHAR(100) NOT NULL,
  level TINYINT UNSIGNED NOT NULL,
  nama_lengkap VARCHAR(300) DEFAULT NULL,
  PRIMARY KEY (kode),
  KEY idx_level (level)
) $charset;",
        ];
    }

    /**
     * Membuat direktori upload privat beserta file .htaccess pelindung.
     * Direktori ini menyimpan berkas sensitif (KTP, KK, Ijazah, Bukti Bayar).
     */
    private static function createUploadDirectory(): void
    {
        $dir = WP_CONTENT_DIR . '/jalagistrasi-uploads';

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        // Tolak semua akses HTTP langsung ke direktori ini.
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents(
                $htaccess,
                "Options -Indexes\ndeny from all\n"
            );
        }

        // File index.php kosong sebagai fallback jika .htaccess tidak aktif (server Nginx).
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden.');
        }
    }

    /**
     * Mendaftarkan custom roles WordPress.
     * Roles tidak langsung aktif di v1 — semua dihandle oleh administrator.
     * Didaftarkan sejak awal agar capability mapping sudah ada untuk v2.
     */
    private static function createRoles(): void
    {
        // Pendaftar: calon mahasiswa.
        if (get_role('pendaftar') === null) {
            add_role('pendaftar', __('Pendaftar', 'jalagistrasi'), [
                'read' => true,
            ]);
        }

        // Panitia PMB: staff yang mengelola proses pendaftaran.
        if (get_role('panitia_pmb') === null) {
            add_role('panitia_pmb', __('Panitia PMB', 'jalagistrasi'), [
                'read'                    => true,
                'jg_view_pendaftaran'     => true,
                'jg_update_status'        => true,
                'jg_export_data'          => true,
            ]);
        }

        // Verifikator Berkas: hanya approve/reject dokumen.
        if (get_role('verifikator_berkas') === null) {
            add_role('verifikator_berkas', __('Verifikator Berkas', 'jalagistrasi'), [
                'read'                => true,
                'jg_view_pendaftaran' => true,
                'jg_verify_berkas'    => true,
            ]);
        }

        // Admin PMB: kelola gelombang, prodi, form builder, pengaturan.
        if (get_role('admin_pmb') === null) {
            add_role('admin_pmb', __('Admin PMB', 'jalagistrasi'), [
                'read'                    => true,
                'jg_view_pendaftaran'     => true,
                'jg_update_status'        => true,
                'jg_verify_berkas'        => true,
                'jg_export_data'          => true,
                'jg_manage_gelombang'     => true,
                'jg_manage_program_studi' => true,
                'jg_manage_form_builder'  => true,
                'jg_manage_settings'      => true,
            ]);
        }
    }

    /**
     * Membuat halaman WordPress yang dibutuhkan plugin jika belum ada.
     * ID halaman disimpan di wp_options agar bisa dikonfigurasi admin.
     */
    public static function createRequiredPages(): void
    {
        $pages = [
            'jalagistrasi_page_registrasi' => [
                'title'     => __('Pendaftaran Mahasiswa Baru', 'jalagistrasi'),
                'slug'      => 'daftar',
                'shortcode' => '[jg_registrasi]',
            ],
            'jalagistrasi_page_dashboard' => [
                'title'     => __('Dashboard Pendaftar', 'jalagistrasi'),
                'slug'      => 'dashboard-pmb',
                'shortcode' => '[jg_dashboard]',
            ],
            'jalagistrasi_page_info' => [
                'title'     => __('Informasi Pendaftaran', 'jalagistrasi'),
                'slug'      => 'informasi-pendaftaran',
                'shortcode' => '[jg_info_pendaftaran]',
            ],
        ];

        foreach ($pages as $optionKey => $page) {
            // Jangan buat ulang jika option sudah ada dan halaman masih eksis.
            $existingId = (int) get_option($optionKey, 0);
            if ($existingId > 0 && get_post($existingId) !== null) {
                continue;
            }

            $pageId = wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ]);

            if (!is_wp_error($pageId) && $pageId > 0) {
                update_option($optionKey, $pageId);
            }
        }
    }
}
