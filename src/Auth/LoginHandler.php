<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Auth;

/**
 * Menangani redirect setelah login dan memblokir akses wp-admin untuk pendaftar.
 */
final class LoginHandler
{
    /**
     * Didaftarkan ke filter 'login_redirect'.
     * Menentukan ke mana user diarahkan setelah login berhasil.
     *
     * @param string    $redirect_to URL tujuan yang diminta
     * @param string    $requested   URL yang diminta sebelum login
     * @param \WP_User|\WP_Error $user User yang baru login
     */
    public function redirectAfterLogin(
        string $redirect_to,
        string $requested,
        \WP_User|\WP_Error $user
    ): string {
        if (!($user instanceof \WP_User)) {
            return $redirect_to;
        }

        if (RoleManager::currentUserHasRole(RoleManager::ROLE_PENDAFTAR)) {
            $dashboard_id = (int) get_option('jalagistrasi_page_dashboard', 0);

            if ($dashboard_id > 0) {
                return (string) get_permalink($dashboard_id);
            }

            return home_url('/dashboard-pmb/');
        }

        // Staff dan administrator masuk ke wp-admin seperti biasa.
        return $redirect_to;
    }

    /**
     * Didaftarkan ke action 'admin_init'.
     * Blokir pendaftar dari mengakses halaman wp-admin manapun.
     * AJAX tetap diizinkan — beberapa plugin membutuhkan AJAX dari frontend.
     */
    public function blockPendaftarFromAdmin(): void
    {
        if (!is_admin()) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // admin-post.php adalah endpoint sah untuk form submission frontend.
        // Pendaftar perlu akses ke sini agar hook admin_post_ bisa jalan.
        global $pagenow;
        if ($pagenow === 'admin-post.php') {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        if (!RoleManager::currentUserHasRole(RoleManager::ROLE_PENDAFTAR)) {
            return;
        }

        $dashboard_id = (int) get_option('jalagistrasi_page_dashboard', 0);
        $redirect_url = $dashboard_id > 0
            ? (string) get_permalink($dashboard_id)
            : home_url('/dashboard-pmb/');

        wp_safe_redirect($redirect_url);
        exit;
    }
}
