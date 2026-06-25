<?php
/**
 * Plugin Name:       Jalagistrasi
 * Plugin URI:        https://webane.id
 * Description:       Sistem Pendaftaran Mahasiswa Baru untuk WordPress.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Webane Indonesia
 * Author URI:        https://webane.id
 * License:           Proprietary
 * Text Domain:       jalagistrasi
 * Domain Path:       /languages
 *
 * @package Webane\Jalagistrasi
 * @copyright 2026 Webane Indonesia. All rights reserved.
 */

declare(strict_types=1);

// Cegah akses langsung ke file ini.
defined('ABSPATH') || exit;

// Pastikan versi PHP memenuhi syarat sebelum autoload apapun.
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        printf(
            /* translators: %s: minimum PHP version */
            esc_html__('Plugin Jalagistrasi membutuhkan PHP %s atau lebih tinggi.', 'jalagistrasi'),
            '8.1'
        );
        echo '</p></div>';
    });
    return;
}

// PSR-4 autoloader via Composer.
require_once __DIR__ . '/vendor/autoload.php';

use Webane\Jalagistrasi\Installer;
use Webane\Jalagistrasi\Plugin;

// Hook aktivasi & deaktivasi — harus didaftarkan sebelum Plugin::boot().
register_activation_hook(__FILE__, [Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [Installer::class, 'deactivate']);

// Booting plugin setelah semua plugin lain di-load.
add_action('plugins_loaded', [Plugin::class, 'boot']);
