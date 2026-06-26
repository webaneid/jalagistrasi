<?php
/**
 * Halaman info publik — cara pendaftaran, gelombang aktif, tombol daftar.
 * Lihat docs/arsitektur-landing-publik.md.
 *
 * @var list<object> $gelombangAktif
 * @var string        $registrasiUrl
 * @var string        $dashboardUrl
 * @var bool          $isLoggedIn
 * @var string        $namaInstitusi
 * @var string        $logoUrl
 * @var string        $tahunAjaranAktif
 * @var string        $alamatInstitusi
 * @var string        $telpInstitusi
 * @var string        $emailInstitusi
 */
defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
$theme = jg_theme_colors();
$colorGen = new \Webane\Jalagistrasi\Service\ColorPaletteGenerator();

// Gradient hero — pola sama dengan halaman Masuk/Daftar (lihat dark-theme.php
// dipakai bersama, tapi gradient diagonal ini spesifik halaman ber-hero besar).
$gradStop1 = $colorGen->mixTowardBlack($theme['brand'], 0.86);
$gradStop2 = $colorGen->mixTowardBlack($theme['brand'], 0.70);
$gradStop3 = $colorGen->mixTowardBlack($theme['brand'], 0.80);
$gradStop4 = $colorGen->mixTowardBlack($theme['brand'], 0.90);

$adaGelombangAktif = !empty($gelombangAktif);

// CTA: sudah login -> ke dashboard. Belum login + ada gelombang buka -> daftar.
// Belum login + TIDAK ada gelombang buka -> tetap kasih jalan masuk buat
// pendaftar lama (gelombang sebelumnya) lewat tombol Login, bukan dead-end.
if ($isLoggedIn) {
    $ctaUrl   = $dashboardUrl;
    $ctaLabel = __('Ke Dashboard Saya', 'jalagistrasi');
} elseif ($adaGelombangAktif) {
    $ctaUrl   = $registrasiUrl;
    $ctaLabel = __('Daftar Sekarang', 'jalagistrasi');
} else {
    $ctaUrl   = $registrasiUrl; // halaman yang sama juga punya tab "Masuk"
    $ctaLabel = __('Login Mahasiswa', 'jalagistrasi');
}

$alurLangkah = [
    __('Buat Akun', 'jalagistrasi'),
    __('Isi Formulir Pendaftaran', 'jalagistrasi'),
    __('Upload Dokumen Persyaratan (KTP, KK, Ijazah, Pas Foto, dll)', 'jalagistrasi'),
    __('Verifikasi oleh Panitia', 'jalagistrasi'),
    __('Upload Bukti Pembayaran', 'jalagistrasi'),
    __('Tes / Seleksi', 'jalagistrasi'),
    __('Pengumuman Hasil', 'jalagistrasi'),
    __('Daftar Ulang', 'jalagistrasi'),
];

$adaKontak = $alamatInstitusi !== '' || $telpInstitusi !== '' || $emailInstitusi !== '';
?>
<div id="jalagistrasi-wrap">
<div class="jg-page">

    <!-- ================================================================
         HERO
         ================================================================ -->
    <div class="jg-info-hero">
        <div class="jg-info-hero-inner">
            <?php if ($logoUrl !== '') : ?>
                <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr($namaInstitusi); ?>" class="jg-info-logo">
            <?php elseif ($namaInstitusi !== '') : ?>
                <span class="jg-info-badge"><?php echo esc_html(mb_strtoupper($namaInstitusi)); ?></span>
            <?php endif; ?>
            <h1 class="jg-info-title">
                <?php esc_html_e('Pendaftaran Mahasiswa Baru', 'jalagistrasi'); ?>
            </h1>
            <?php if ($tahunAjaranAktif !== '') : ?>
                <p class="jg-info-subtitle"><?php echo esc_html($tahunAjaranAktif); ?></p>
            <?php endif; ?>

            <?php if (!$adaGelombangAktif && !$isLoggedIn) : ?>
                <p class="jg-info-closed"><?php esc_html_e('Pendaftaran belum dibuka. Pantau halaman ini untuk info terbaru.', 'jalagistrasi'); ?></p>
            <?php endif; ?>

            <a href="<?php echo esc_url($ctaUrl); ?>" class="jg-btn jg-info-cta"><?php echo esc_html($ctaLabel); ?> →</a>
        </div>
    </div>

    <div class="jg-container">

        <!-- ============================================================
             GELOMBANG AKTIF
             ============================================================ -->
        <?php if ($adaGelombangAktif) : ?>
            <div class="jg-info-section">
                <h2 class="jg-section-title"><?php esc_html_e('Gelombang yang Sedang Dibuka', 'jalagistrasi'); ?></h2>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <?php foreach ($gelombangAktif as $g) : ?>
                        <div class="jg-card jg-info-gelombang-card">
                            <div>
                                <p class="jg-card-title"><?php echo esc_html($g->nama); ?></p>
                                <p class="jg-card-sub"><?php echo esc_html($g->tahun_akademik); ?></p>
                                <div class="jg-info-gelombang-meta">
                                    <span>
                                        <?php esc_html_e('Buka:', 'jalagistrasi'); ?>
                                        <?php echo esc_html(date_i18n('d M Y', strtotime($g->tanggal_buka))); ?>
                                    </span>
                                    <span>
                                        <?php esc_html_e('Tutup:', 'jalagistrasi'); ?>
                                        <?php echo esc_html(date_i18n('d M Y', strtotime($g->tanggal_tutup))); ?>
                                    </span>
                                    <?php if ((float) $g->biaya_pendaftaran > 0) : ?>
                                        <span>
                                            <?php esc_html_e('Biaya:', 'jalagistrasi'); ?>
                                            Rp <?php echo esc_html(number_format((float) $g->biaya_pendaftaran, 0, ',', '.')); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="<?php echo esc_url($ctaUrl); ?>" class="jg-btn jg-btn--small jg-info-gelombang-btn"><?php echo esc_html($ctaLabel); ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================
             ALUR PENDAFTARAN
             ============================================================ -->
        <div class="jg-info-section">
            <h2 class="jg-section-title"><?php esc_html_e('Alur Pendaftaran', 'jalagistrasi'); ?></h2>
            <div class="jg-card">
                <div class="jg-info-timeline">
                    <?php foreach ($alurLangkah as $i => $langkah) : ?>
                        <div class="jg-info-timeline-item">
                            <span class="jg-info-timeline-dot"><?php echo (int) $i + 1; ?></span>
                            <span class="jg-info-timeline-label"><?php echo esc_html($langkah); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ============================================================
             KONTAK
             ============================================================ -->
        <?php if ($adaKontak) : ?>
            <div class="jg-info-section">
                <h2 class="jg-section-title"><?php esc_html_e('Informasi Kontak', 'jalagistrasi'); ?></h2>
                <div class="jg-card" style="font-size:13px;color:rgba(255,255,255,0.7);">
                    <?php if ($alamatInstitusi !== '') : ?>
                        <p style="margin:0 0 8px;"><?php echo nl2br(esc_html($alamatInstitusi)); ?></p>
                    <?php endif; ?>
                    <?php if ($telpInstitusi !== '') : ?>
                        <p style="margin:0 0 8px;"><?php esc_html_e('Telp/WA:', 'jalagistrasi'); ?> <?php echo esc_html($telpInstitusi); ?></p>
                    <?php endif; ?>
                    <?php if ($emailInstitusi !== '') : ?>
                        <p style="margin:0;"><?php esc_html_e('Email:', 'jalagistrasi'); ?> <?php echo esc_html($emailInstitusi); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-info-hero {
    padding: 64px 20px 56px;
    text-align: center;
    background-color: <?php echo esc_html($gradStop4); ?>;
    background-image: linear-gradient(
        135deg,
        <?php echo esc_html($gradStop1); ?> 0%,
        <?php echo esc_html($gradStop2); ?> 30%,
        <?php echo esc_html($gradStop3); ?> 55%,
        <?php echo esc_html($gradStop4); ?> 100%
    );
}
#jalagistrasi-wrap .jg-info-hero-inner { max-width: 640px; margin: 0 auto; }

#jalagistrasi-wrap .jg-info-logo {
    max-height: 72px;
    max-width: 220px;
    margin: 0 auto 20px;
    display: block;
    object-fit: contain;
    /* Logo diupload biasanya PNG/WebP warna asli (gelap/berwarna) — paksa jadi
       siluet putih solid supaya kontras di atas background dark-glass. Cuma
       efektif kalau logo punya background transparan. */
    filter: brightness(0) invert(1);
}

#jalagistrasi-wrap .jg-info-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 9999px;
    background: rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.25);
    border: 1px solid rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.5);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    margin-bottom: 16px;
}

#jalagistrasi-wrap .jg-info-title {
    margin: 0;
    font-size: 30px;
    font-weight: 800;
    color: #fff;
    line-height: 1.25;
}
#jalagistrasi-wrap .jg-info-subtitle {
    margin: 10px 0 0;
    font-size: 15px;
    color: rgba(255, 255, 255, 0.55);
}

#jalagistrasi-wrap .jg-info-cta { margin-top: 28px; padding: 14px 32px; font-size: 15px; }
#jalagistrasi-wrap .jg-info-closed {
    margin: 28px auto 0;
    max-width: 360px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 12px 16px;
}

#jalagistrasi-wrap .jg-info-section { margin-bottom: 32px; }

#jalagistrasi-wrap .jg-info-gelombang-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 0;
}
#jalagistrasi-wrap .jg-info-gelombang-meta {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px 16px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.4);
}
#jalagistrasi-wrap .jg-info-gelombang-btn { flex-shrink: 0; }

#jalagistrasi-wrap .jg-info-timeline { display: flex; flex-direction: column; }
#jalagistrasi-wrap .jg-info-timeline-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 0;
    position: relative;
}
#jalagistrasi-wrap .jg-info-timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 13px;
    top: 36px;
    bottom: -6px;
    width: 1px;
    background: rgba(255, 255, 255, 0.12);
}
#jalagistrasi-wrap .jg-info-timeline-dot {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 27px;
    height: 27px;
    border-radius: 9999px;
    background: rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.18);
    color: #93c5fd;
    font-size: 12px;
    font-weight: 700;
    z-index: 1;
}
#jalagistrasi-wrap .jg-info-timeline-label { font-size: 13px; color: rgba(255, 255, 255, 0.8); }
</style>
