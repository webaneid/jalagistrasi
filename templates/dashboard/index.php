<?php
/**
 * Template dashboard pendaftar — stub v1.
 *
 * @var \WP_User $user User yang sedang login
 */

defined('ABSPATH') || exit;
?>
<div id="jalagistrasi-wrap">
    <div class="min-h-screen bg-gray-50 py-10 px-4">
        <div class="max-w-2xl mx-auto">

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
                <h1 class="text-xl font-bold text-gray-900 mb-2">
                    <?php
                    printf(
                        /* translators: %s: nama pendaftar */
                        esc_html__('Selamat datang, %s!', 'jalagistrasi'),
                        esc_html($user->display_name)
                    );
                    ?>
                </h1>
                <p class="text-sm text-gray-600 mb-6">
                    <?php esc_html_e('Dashboard Pendaftaran Mahasiswa Baru', 'jalagistrasi'); ?>
                </p>

                <div class="rounded-lg bg-brand-50 border border-brand-200 p-4">
                    <p class="text-sm text-brand-700">
                        <?php esc_html_e('Akun Anda berhasil dibuat. Fitur dashboard sedang dalam pengembangan.', 'jalagistrasi'); ?>
                    </p>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-100">
                    <a
                        href="<?php echo esc_url(wp_logout_url(home_url())); ?>"
                        class="text-sm text-gray-500 hover:text-gray-700"
                    >
                        <?php esc_html_e('Keluar', 'jalagistrasi'); ?>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
