<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Auth;

/**
 * Helper statis untuk cek role dan capability user yang sedang aktif.
 * Tidak ada state — semua method delegasi ke fungsi WordPress.
 */
final class RoleManager
{
    public const ROLE_PENDAFTAR          = 'pendaftar';
    public const ROLE_PANITIA_PMB        = 'panitia_pmb';
    public const ROLE_VERIFIKATOR_BERKAS = 'verifikator_berkas';
    public const ROLE_ADMIN_PMB          = 'admin_pmb';

    public const CAP_VIEW_PENDAFTARAN     = 'jg_view_pendaftaran';
    public const CAP_UPDATE_STATUS        = 'jg_update_status';
    public const CAP_VERIFY_BERKAS        = 'jg_verify_berkas';
    public const CAP_EXPORT_DATA          = 'jg_export_data';
    public const CAP_MANAGE_GELOMBANG     = 'jg_manage_gelombang';
    public const CAP_MANAGE_PROGRAM_STUDI = 'jg_manage_program_studi';
    public const CAP_MANAGE_FORM_BUILDER  = 'jg_manage_form_builder';
    public const CAP_MANAGE_SETTINGS      = 'jg_manage_settings';

    public static function currentUserIsPendaftar(): bool
    {
        return self::currentUserHasRole(self::ROLE_PENDAFTAR);
    }

    public static function currentUserIsStaff(): bool
    {
        return current_user_can(self::CAP_VIEW_PENDAFTARAN);
    }

    public static function currentUserIsAdminPmb(): bool
    {
        return current_user_can(self::CAP_MANAGE_SETTINGS)
            || current_user_can('administrator');
    }

    public static function currentUserHasRole(string $role): bool
    {
        $user = wp_get_current_user();

        if (!($user instanceof \WP_User) || $user->ID === 0) {
            return false;
        }

        return in_array($role, (array) $user->roles, true);
    }

    /**
     * Verifikasi capability dengan fallback ke administrator.
     * Administrator selalu lolos semua capability plugin.
     */
    public static function currentUserCan(string $capability): bool
    {
        return current_user_can($capability) || current_user_can('administrator');
    }

    /**
     * Throw-style guard — lempar WP_Error jika user tidak punya capability.
     * Dipakai di controller sebelum memproses request.
     */
    public static function requireCapability(string $capability): void
    {
        if (!self::currentUserCan($capability)) {
            wp_die(
                esc_html__('Anda tidak memiliki izin untuk melakukan aksi ini.', 'jalagistrasi'),
                esc_html__('Akses Ditolak', 'jalagistrasi'),
                ['response' => 403]
            );
        }
    }
}
