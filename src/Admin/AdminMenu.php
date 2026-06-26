<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

class AdminMenu
{
    public function registerMenus(): void
    {
        $dashboardCtrl = new DashboardController();

        add_menu_page(
            __('Jalagistrasi PMB', 'jalagistrasi'),
            __('Jalagistrasi PMB', 'jalagistrasi'),
            'manage_options',
            'jg-dashboard',
            [$dashboardCtrl, 'renderPage'],
            // 'dashicons-graduation' tidak ada di set Dashicons resmi WordPress (icon
            // tidak pernah muncul). 'dashicons-welcome-learn-more' adalah ikon topi
            // toga/wisuda asli bawaan WP — paling pas untuk sistem PMB.
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'jg-dashboard',
            __('Dashboard PMB', 'jalagistrasi'),
            __('Dashboard', 'jalagistrasi'),
            'manage_options',
            'jg-dashboard',
            [$dashboardCtrl, 'renderPage']
        );

        $tahunAjaranCtrl = new TahunAjaranController();
        add_submenu_page(
            'jg-dashboard',
            __('Tahun Ajaran', 'jalagistrasi'),
            __('Tahun Ajaran', 'jalagistrasi'),
            'manage_options',
            'jg-tahun-ajaran',
            [$tahunAjaranCtrl, 'renderPage']
        );

        $gelombangCtrl = new GelombangController();
        add_submenu_page(
            'jg-dashboard',
            __('Gelombang Pendaftaran', 'jalagistrasi'),
            __('Gelombang', 'jalagistrasi'),
            'manage_options',
            'jg-gelombang',
            [$gelombangCtrl, 'renderPage']
        );

        $prodiCtrl = new ProgramStudiController();
        add_submenu_page(
            'jg-dashboard',
            __('Program Studi', 'jalagistrasi'),
            __('Program Studi', 'jalagistrasi'),
            'manage_options',
            'jg-program-studi',
            [$prodiCtrl, 'renderPage']
        );

        $pendaftarCtrl = new PendaftarController();
        add_submenu_page(
            'jg-dashboard',
            __('Data Pendaftar', 'jalagistrasi'),
            __('Pendaftar', 'jalagistrasi'),
            'manage_options',
            'jg-pendaftar',
            [$pendaftarCtrl, 'renderPage']
        );

        // Diletakkan TEPAT di bawah "Pendaftar" sengaja — beda lingkup: ini
        // SEMUA akun ber-role pendaftar (termasuk yang belum pernah submit),
        // bukan cuma yang sudah submit pendaftaran. Lihat docs/arsitektur-overview.md.
        $akunPendaftarCtrl = new AkunPendaftarController();
        add_submenu_page(
            'jg-dashboard',
            __('Role Pendaftar', 'jalagistrasi'),
            __('Role Pendaftar', 'jalagistrasi'),
            'manage_options',
            'jg-akun-pendaftar',
            [$akunPendaftarCtrl, 'renderPage']
        );

        $tipeBerkasCtrl = new TipeBerkasController();
        add_submenu_page(
            'jg-dashboard',
            __('Tipe Berkas Upload', 'jalagistrasi'),
            __('Tipe Berkas', 'jalagistrasi'),
            'manage_options',
            'jg-tipe-berkas',
            [$tipeBerkasCtrl, 'renderPage']
        );

        $rekeningBankCtrl = new RekeningBankController();
        add_submenu_page(
            'jg-dashboard',
            __('Rekening Bank Tujuan', 'jalagistrasi'),
            __('Rekening Bank', 'jalagistrasi'),
            'manage_options',
            'jg-rekening-bank',
            [$rekeningBankCtrl, 'renderPage']
        );

        $formBuilderCtrl = new FormBuilderController();
        add_submenu_page(
            'jg-dashboard',
            __('Form Builder', 'jalagistrasi'),
            __('Form Builder', 'jalagistrasi'),
            'manage_options',
            'jg-form-builder',
            [$formBuilderCtrl, 'renderPage']
        );

        $pengaturanCtrl = new PengaturanController();
        add_submenu_page(
            'jg-dashboard',
            __('Pengaturan PMB', 'jalagistrasi'),
            __('Pengaturan', 'jalagistrasi'),
            'manage_options',
            'jg-pengaturan',
            [$pengaturanCtrl, 'renderPage']
        );
    }

    public function enqueueAdminAssets(string $hookSuffix): void
    {
        // Hanya load di halaman plugin
        $pluginPages = [
            'toplevel_page_jg-dashboard',
            'jalagistrasi-pmb_page_jg-tahun-ajaran',
            'jalagistrasi-pmb_page_jg-gelombang',
            'jalagistrasi-pmb_page_jg-program-studi',
            'jalagistrasi-pmb_page_jg-pendaftar',
            'jalagistrasi-pmb_page_jg-tipe-berkas',
            'jalagistrasi-pmb_page_jg-rekening-bank',
            'jalagistrasi-pmb_page_jg-form-builder',
            'jalagistrasi-pmb_page_jg-pengaturan',
        ];

        if (!in_array($hookSuffix, $pluginPages, true)) {
            return;
        }

        // jQuery UI Sortable sudah bundled di WP — dipakai Form Builder drag & drop
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_script(
            'jalagistrasi-admin',
            JG_PLUGIN_URL . 'assets/js/admin.js',
            [],
            JG_VERSION,
            true
        );
    }

    public function renderComingSoon(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Segera Hadir', 'jalagistrasi'); ?></h1>
            <p><?php esc_html_e('Fitur ini sedang dalam pengembangan.', 'jalagistrasi'); ?></p>
        </div>
        <?php
    }
}
