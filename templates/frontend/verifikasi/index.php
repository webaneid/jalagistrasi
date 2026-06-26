<?php
/**
 * Halaman publik verifikasi QR — /verifikasi/<nomor>/<token>/. Lihat
 * docs/arsitektur-verifikasi-qr.md.
 *
 * Halaman ini DI LUAR sistem page/post WordPress (di-render langsung dari
 * template_redirect via rewrite rule, lihat Plugin::maybeRenderVerifikasi())
 * — jadi kerangka HTML lengkap (doctype/head/body) ditulis sendiri di sini,
 * bukan lewat the_content() seperti templates/auth/page-blank.php.
 * wp_head()/wp_footer() tetap dipanggil supaya script/font tema & plugin lain
 * (analytics, dst) tetap jalan, sama seperti page-blank.php.
 *
 * @var bool          $ditemukan
 * @var object|null   $pendaftaran
 * @var string|null   $namaLengkap
 * @var string|null   $gelombangNama
 * @var string|null   $tahunAkademik
 * @var list<object>  $prodiPilihan
 * @var string|null   $statusLabel
 * @var string|null   $fotoUrl
 */
defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
jg_theme_colors();

$namaInstitusi = (string) get_option('jalagistrasi_nama_institusi', '');
$logoId        = (int) get_option('jalagistrasi_logo_id', 0);
$logoUrl       = $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'medium') : '';
$namaTampil    = $namaInstitusi !== '' ? $namaInstitusi : (string) get_bloginfo('name');

if ($ditemukan) {
    $qrUrl = home_url('/verifikasi/' . rawurlencode($pendaftaran->nomor_pendaftaran) . '/' . rawurlencode((string) $pendaftaran->verifikasi_token) . '/');
    $qrDataUri = (new \Webane\Jalagistrasi\Service\QrCodeService())->generateSvgDataUri($qrUrl, 180);
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('Verifikasi Pendaftar', 'jalagistrasi'); ?> — <?php echo esc_html($namaTampil); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('jalagistrasi-blank-page'); ?>>
<?php wp_body_open(); ?>

<div id="jalagistrasi-wrap">
<div class="jg-page" style="display:flex;align-items:center;justify-content:center;padding:32px 16px;">
    <div class="jg-card" style="max-width:380px;width:100%;text-align:center;">

        <?php if ($logoUrl !== '') : ?>
            <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr($namaTampil); ?>" class="jg-brand-logo" style="margin:0 auto 16px;">
        <?php else : ?>
            <p class="jg-card-sub" style="margin-bottom:16px;"><?php echo esc_html($namaTampil); ?></p>
        <?php endif; ?>

        <?php if (!$ditemukan) : ?>
            <div class="jg-verif-icon jg-verif-icon--gagal">✕</div>
            <p class="jg-card-title" style="margin-top:14px;"><?php esc_html_e('Data Tidak Ditemukan', 'jalagistrasi'); ?></p>
            <p class="jg-card-sub" style="margin-top:6px;">
                <?php esc_html_e('Link verifikasi tidak valid atau sudah tidak berlaku.', 'jalagistrasi'); ?>
            </p>
        <?php else : ?>
            <?php if ($fotoUrl !== '') : ?>
                <img src="<?php echo esc_url($fotoUrl); ?>" alt="<?php echo esc_attr($namaLengkap); ?>" class="jg-verif-foto">
            <?php else : ?>
                <div class="jg-verif-foto jg-verif-foto--kosong">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                </div>
            <?php endif; ?>

            <p class="jg-card-title" style="margin-top:14px;font-size:17px;"><?php echo esc_html($namaLengkap); ?></p>
            <p class="jg-card-sub" style="margin-top:2px;"><?php echo esc_html($pendaftaran->nomor_pendaftaran); ?></p>

            <span class="jg-badge jg-badge--success" style="margin-top:10px;">✓ <?php esc_html_e('Terverifikasi', 'jalagistrasi'); ?></span>

            <div class="jg-verif-detail">
                <div class="jg-verif-detail-row">
                    <span><?php esc_html_e('Gelombang', 'jalagistrasi'); ?></span>
                    <strong><?php echo esc_html($gelombangNama); ?></strong>
                </div>
                <div class="jg-verif-detail-row">
                    <span><?php esc_html_e('Tahun Akademik', 'jalagistrasi'); ?></span>
                    <strong><?php echo esc_html($tahunAkademik); ?></strong>
                </div>
                <div class="jg-verif-detail-row">
                    <span><?php esc_html_e('Status', 'jalagistrasi'); ?></span>
                    <strong><?php echo esc_html($statusLabel); ?></strong>
                </div>
                <?php if (!empty($prodiPilihan)) : ?>
                    <div class="jg-verif-detail-row" style="align-items:flex-start;flex-direction:column;gap:4px;">
                        <span><?php esc_html_e('Pilihan Prodi', 'jalagistrasi'); ?></span>
                        <div style="display:flex;flex-direction:column;gap:2px;width:100%;">
                            <?php foreach ($prodiPilihan as $pp) : ?>
                                <strong style="font-size:13px;"><?php echo (int) $pp->urutan; ?>. <?php echo esc_html($pp->prodi_nama); ?></strong>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <img src="<?php echo esc_attr($qrDataUri); ?>" alt="QR" class="jg-verif-qr">
            <p class="jg-field-hint"><?php esc_html_e('Kode ini unik untuk pendaftar ini — jangan dibagikan ke orang lain.', 'jalagistrasi'); ?></p>
        <?php endif; ?>

    </div>
</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-verif-icon {
    width: 56px; height: 56px; margin: 0 auto; border-radius: 9999px;
    display: flex; align-items: center; justify-content: center; font-size: 24px;
}
#jalagistrasi-wrap .jg-verif-icon--gagal { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }

#jalagistrasi-wrap .jg-verif-foto {
    width: 96px; height: 96px; margin: 0 auto; border-radius: 9999px;
    object-fit: cover; border: 2px solid rgba(255, 255, 255, 0.15);
}
#jalagistrasi-wrap .jg-verif-foto--kosong {
    display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, 0.06); color: rgba(255, 255, 255, 0.3);
}

#jalagistrasi-wrap .jg-verif-detail {
    margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(255, 255, 255, 0.08);
    text-align: left;
}
#jalagistrasi-wrap .jg-verif-detail-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 0; font-size: 13px;
}
#jalagistrasi-wrap .jg-verif-detail-row span { color: rgba(255, 255, 255, 0.5); }
#jalagistrasi-wrap .jg-verif-detail-row strong { color: #fff; font-weight: 600; }

#jalagistrasi-wrap .jg-verif-qr {
    width: 160px; height: 160px; margin: 20px auto 8px; display: block;
    background: #fff; padding: 10px; border-radius: 12px;
}
</style>

<?php wp_footer(); ?>
</body>
</html>
