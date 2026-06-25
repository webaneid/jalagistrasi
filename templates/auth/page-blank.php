<?php
/**
 * Template halaman kosong — dipakai halaman Masuk/Daftar dan Dashboard supaya
 * terasa seperti aplikasi sendiri, tanpa header/footer tema. Dipasang lewat
 * filter `template_include`, lihat docs/arsitektur-login-register.md dan
 * docs/arsitektur-dashboard-mahasiswa.md.
 *
 * wp_head()/wp_footer() tetap dipanggil (skrip WP core, plugin lain, SEO meta,
 * dan font/CSS dari tema tetap jalan) — yang dilewati cuma header.php/footer.php
 * milik tema (nav menu, site header/footer widget, dst).
 */
defined('ABSPATH') || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php // Judul halaman di-render oleh wp_head() lewat title-tag support tema — jangan tambah <title> manual di sini, berisiko duplikat. ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('jalagistrasi-blank-page'); ?>>
    <?php wp_body_open(); ?>
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
    <?php wp_footer(); ?>
</body>
</html>
