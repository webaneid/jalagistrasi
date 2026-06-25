<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Plugin;

/**
 * Halaman Pengaturan plugin — setting dasar yang mempengaruhi seluruh sistem.
 */
final class PengaturanController
{
    /** @var array<string,array{label:string,default:string,description:string,type?:string}> */
    private const SETTINGS = [
        'jalagistrasi_nomor_prefix' => [
            'label'       => 'Prefix Nomor Pendaftaran',
            'default'     => 'PMB',
            'description' => 'Contoh: PMB → menghasilkan PMB-2026-0001',
        ],
        'jalagistrasi_nomor_seq_length' => [
            'label'       => 'Panjang Digit Urutan',
            'default'     => '4',
            'description' => '4 → urutan tampil sebagai 0001, 0002, dst.',
            'type'        => 'number',
        ],
        'jalagistrasi_nama_institusi' => [
            'label'       => 'Nama Institusi',
            'default'     => '',
            'description' => 'Ditampilkan di form pendaftaran dan halaman konfirmasi.',
        ],
        'jalagistrasi_alamat_institusi' => [
            'label'       => 'Alamat Institusi',
            'default'     => '',
            'description' => 'Alamat lengkap — ditampilkan di halaman info publik.',
            'type'        => 'textarea',
        ],
        'jalagistrasi_telp_institusi' => [
            'label'       => 'Telepon / WhatsApp Kontak',
            'default'     => '',
            'description' => 'Nomor kontak resmi yang ditampilkan ke publik.',
        ],
        'jalagistrasi_email_institusi' => [
            'label'       => 'Email Kontak',
            'default'     => '',
            'description' => 'Email kontak resmi yang ditampilkan ke publik.',
            'type'        => 'email',
        ],
    ];

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $tab = sanitize_key($_GET['tab'] ?? 'umum');
        $message = sanitize_text_field($_GET['message'] ?? '');
        $baseUrl = admin_url('admin.php?page=jg-pengaturan');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Pengaturan Jalagistrasi PMB', 'jalagistrasi'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url($baseUrl . '&tab=umum'); ?>" class="nav-tab <?php echo $tab === 'umum' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Umum', 'jalagistrasi'); ?>
                </a>
                <a href="<?php echo esc_url($baseUrl . '&tab=update'); ?>" class="nav-tab <?php echo $tab === 'update' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Update', 'jalagistrasi'); ?>
                </a>
            </h2>

            <?php if ($tab === 'update') : ?>
                <?php $this->renderTabUpdate($message); ?>
            <?php else : ?>
                <?php $this->renderTabUmum($message); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderTabUmum(string $message): void
    {
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        $logoId     = (int) get_option('jalagistrasi_logo_id', 0);
        $logoUrl    = $logoId > 0 ? wp_get_attachment_image_url($logoId, 'medium') : '';
        $warnaBrand    = (string) get_option('jalagistrasi_warna_brand', '#2563eb');
        $warnaSekunder = (string) get_option('jalagistrasi_warna_sekunder', '#f59e0b');
        ?>
            <?php if ($message === 'saved') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Pengaturan berhasil disimpan.', 'jalagistrasi'); ?></p>
                </div>
            <?php elseif ($message === 'invalid_logo') : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Logo tidak valid — pilih file gambar dari Media Library.', 'jalagistrasi'); ?></p>
                </div>
            <?php elseif ($message === 'invalid_warna') : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Format warna brand tidak valid — gunakan color picker.', 'jalagistrasi'); ?></p>
                </div>
            <?php elseif ($message === 'wilayah_synced') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Data wilayah berhasil disinkronkan.', 'jalagistrasi'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('jg_save_pengaturan'); ?>
                <input type="hidden" name="action" value="jg_save_pengaturan">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Logo Institusi', 'jalagistrasi'); ?></th>
                        <td>
                            <input type="hidden" id="jalagistrasi_logo_id" name="settings[jalagistrasi_logo_id]" value="<?php echo esc_attr((string) $logoId); ?>">
                            <div id="jg-logo-preview" style="margin-bottom:8px;<?php echo $logoUrl ? '' : 'display:none;'; ?>">
                                <img src="<?php echo esc_url($logoUrl); ?>" alt="" style="max-height:100px;max-width:200px;border:1px solid #dcdcde;border-radius:4px;padding:6px;background:#fff;display:block;">
                            </div>
                            <button type="button" class="button" id="jg-pilih-logo"><?php esc_html_e('Pilih Logo', 'jalagistrasi'); ?></button>
                            <button type="button" class="button" id="jg-hapus-logo" style="<?php echo $logoUrl ? '' : 'display:none;'; ?>"><?php esc_html_e('Hapus', 'jalagistrasi'); ?></button>
                            <p class="description"><?php esc_html_e('Belum dipakai di mana pun saat ini — disiapkan untuk kop surat ekspor PDF dan halaman info publik.', 'jalagistrasi'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Warna Brand (Primer)', 'jalagistrasi'); ?></th>
                        <td>
                            <input type="text"
                                   id="jalagistrasi_warna_brand"
                                   name="settings[jalagistrasi_warna_brand]"
                                   value="<?php echo esc_attr($warnaBrand); ?>"
                                   class="jg-color-picker"
                                   data-default-color="#2563eb">
                            <p class="description">
                                <?php esc_html_e('Warna tombol aksi utama (Daftar Sekarang, Upload, Kirim, dst) di seluruh halaman pendaftar.', 'jalagistrasi'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Warna Sekunder (Aksen)', 'jalagistrasi'); ?></th>
                        <td>
                            <input type="text"
                                   id="jalagistrasi_warna_sekunder"
                                   name="settings[jalagistrasi_warna_sekunder]"
                                   value="<?php echo esc_attr($warnaSekunder); ?>"
                                   class="jg-color-picker"
                                   data-default-color="#f59e0b">
                            <p class="description">
                                <?php esc_html_e('Warna aksen untuk elemen dekoratif/info (nomor langkah, badge, border aksen) — bukan tombol aksi utama.', 'jalagistrasi'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php foreach (self::SETTINGS as $optionKey => $meta) : ?>
                        <?php
                        $currentVal = (string) get_option($optionKey, $meta['default']);
                        $type       = $meta['type'] ?? 'text';
                        ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($optionKey); ?>">
                                    <?php echo esc_html($meta['label']); ?>
                                </label>
                            </th>
                            <td>
                                <?php if ($type === 'textarea') : ?>
                                    <textarea
                                        id="<?php echo esc_attr($optionKey); ?>"
                                        name="settings[<?php echo esc_attr($optionKey); ?>]"
                                        rows="3"
                                        class="large-text"
                                    ><?php echo esc_textarea($currentVal); ?></textarea>
                                <?php else : ?>
                                    <input
                                        type="<?php echo $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text'); ?>"
                                        id="<?php echo esc_attr($optionKey); ?>"
                                        name="settings[<?php echo esc_attr($optionKey); ?>]"
                                        value="<?php echo esc_attr($currentVal); ?>"
                                        class="regular-text"
                                        <?php echo $type === 'number' ? 'min="1" max="10"' : ''; ?>
                                    >
                                <?php endif; ?>
                                <p class="description"><?php echo esc_html($meta['description']); ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(__('Simpan Pengaturan', 'jalagistrasi')); ?>
            </form>

            <hr style="margin:32px 0;">

            <h2><?php esc_html_e('Data Wilayah Indonesia', 'jalagistrasi'); ?></h2>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: jumlah baris data wilayah */
                    esc_html__('Saat ini tersimpan %s baris data provinsi/kabupaten/kecamatan/desa, dipakai oleh field "Provinsi/Kabupaten/Kecamatan/Desa" di formulir pendaftaran. Sumber: Kepmendagri terbaru (lihat docs/arsitektur-alamat-wilayah.md).', 'jalagistrasi'),
                    '<strong>' . number_format((new \Webane\Jalagistrasi\Repository\WilayahRepository())->countAll()) . '</strong>'
                );
                ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('jg_sync_wilayah'); ?>
                <input type="hidden" name="action" value="jg_sync_wilayah">
                <button type="submit" class="button"><?php esc_html_e('Sync Data Wilayah', 'jalagistrasi'); ?></button>
                <p class="description"><?php esc_html_e('Jalankan ulang kalau file data/wilayah.csv di plugin sudah diperbarui (mis. ada pemekaran wilayah baru).', 'jalagistrasi'); ?></p>
            </form>
        <script>
        jQuery(function ($) {
            $('.jg-color-picker').wpColorPicker();
        });
        (function () {
            var frame;
            document.getElementById('jg-pilih-logo').addEventListener('click', function (e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: '<?php echo esc_js(__('Pilih Logo Institusi', 'jalagistrasi')); ?>',
                    button: { text: '<?php echo esc_js(__('Gunakan Logo Ini', 'jalagistrasi')); ?>' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    document.getElementById('jalagistrasi_logo_id').value = attachment.id;
                    var preview = document.getElementById('jg-logo-preview');
                    preview.innerHTML = '<img src="' + attachment.url + '" alt="" style="max-height:100px;max-width:200px;border:1px solid #dcdcde;border-radius:4px;padding:6px;background:#fff;display:block;">';
                    preview.style.display = 'block';
                    document.getElementById('jg-hapus-logo').style.display = 'inline-block';
                });
                frame.open();
            });
            document.getElementById('jg-hapus-logo').addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('jalagistrasi_logo_id').value = '0';
                document.getElementById('jg-logo-preview').style.display = 'none';
                this.style.display = 'none';
            });
        })();
        </script>
        <?php
    }

    private function renderTabUpdate(string $message): void
    {
        $updateChecker = Plugin::buildUpdateChecker();
        $installedVersion = Plugin::VERSION;
        $lastChecked = get_option('jalagistrasi_update_last_checked', '');

        $update = $updateChecker?->getUpdate();
        $latestVersion = $update?->version;
        ?>
            <?php if ($message === 'checked') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Pengecekan update selesai.', 'jalagistrasi'); ?></p>
                </div>
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Versi Terpasang', 'jalagistrasi'); ?></th>
                    <td><code><?php echo esc_html($installedVersion); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Versi Terbaru', 'jalagistrasi'); ?></th>
                    <td>
                        <?php if ($latestVersion) : ?>
                            <code><?php echo esc_html($latestVersion); ?></code>
                            <span class="description"> — <?php esc_html_e('update tersedia, buka halaman Plugins untuk update.', 'jalagistrasi'); ?></span>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e('Anda sudah menggunakan versi terbaru (atau belum pernah dicek).', 'jalagistrasi'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Terakhir Dicek', 'jalagistrasi'); ?></th>
                    <td>
                        <?php echo $lastChecked !== '' ? esc_html(date_i18n('d M Y H:i', strtotime((string) $lastChecked))) : '—'; ?>
                    </td>
                </tr>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('jg_check_update'); ?>
                <input type="hidden" name="action" value="jg_check_update">
                <button type="submit" class="button button-primary"><?php esc_html_e('Cek Update Sekarang', 'jalagistrasi'); ?></button>
                <p class="description">
                    <?php esc_html_e('Sumber update: GitHub Releases repo webaneid/jalagistrasi (branch main).', 'jalagistrasi'); ?>
                </p>
            </form>
        <?php
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_save_pengaturan')) {
            wp_die(esc_html__('Nonce tidak valid.', 'jalagistrasi'), 403);
        }

        // phpcs:ignore WordPress.Security.NonceVerification
        $submitted = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];

        $redirectArgs = ['page' => 'jg-pengaturan', 'message' => 'saved'];

        // Logo — validasi terpisah karena bukan field teks biasa.
        $logoId = (int) ($submitted['jalagistrasi_logo_id'] ?? 0);
        if ($logoId > 0 && !wp_attachment_is_image($logoId)) {
            $redirectArgs['message'] = 'invalid_logo';
        } else {
            update_option('jalagistrasi_logo_id', $logoId);
        }

        // Warna brand & sekunder — validasi terpisah karena bukan field teks biasa.
        foreach (['jalagistrasi_warna_brand', 'jalagistrasi_warna_sekunder'] as $warnaKey) {
            $warna = sanitize_text_field(wp_unslash((string) ($submitted[$warnaKey] ?? '')));
            if ($warna === '') {
                continue;
            }
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $warna)) {
                update_option($warnaKey, strtolower($warna));
            } else {
                $redirectArgs['message'] = 'invalid_warna';
            }
        }

        foreach (self::SETTINGS as $optionKey => $meta) {
            if (!array_key_exists($optionKey, $submitted)) {
                continue;
            }

            $type = $meta['type'] ?? 'text';
            $raw  = wp_unslash((string) $submitted[$optionKey]);

            $val = match ($type) {
                'textarea' => sanitize_textarea_field($raw),
                'email'    => sanitize_email($raw),
                default    => sanitize_text_field($raw),
            };

            // Validasi khusus
            if ($optionKey === 'jalagistrasi_nomor_seq_length') {
                $val = (string) max(1, min(10, (int) $val));
            }

            if ($optionKey === 'jalagistrasi_nomor_prefix') {
                $val = strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', $val));
            }

            update_option($optionKey, $val);
        }

        wp_safe_redirect(add_query_arg($redirectArgs, admin_url('admin.php')));
        exit;
    }

    /**
     * Re-import data/wilayah.csv ke tabel jg_wilayah. Hook: admin_post_jg_sync_wilayah
     */
    public function handleSyncWilayah(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        check_admin_referer('jg_sync_wilayah');

        (new \Webane\Jalagistrasi\Service\WilayahImportService())->import();

        wp_safe_redirect(add_query_arg(
            ['page' => 'jg-pengaturan', 'message' => 'wilayah_synced'],
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Force-refresh pengecekan update dari GitHub (bypass interval cache 12 jam
     * default PUC). Hook: admin_post_jg_check_update
     */
    public function handleCheckUpdate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        check_admin_referer('jg_check_update');

        $updateChecker = Plugin::buildUpdateChecker();
        $updateChecker?->checkForUpdates();

        update_option('jalagistrasi_update_last_checked', current_time('mysql'));

        wp_safe_redirect(add_query_arg(
            ['page' => 'jg-pengaturan', 'tab' => 'update', 'message' => 'checked'],
            admin_url('admin.php')
        ));
        exit;
    }
}
