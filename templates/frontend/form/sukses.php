<?php
/**
 * Halaman konfirmasi setelah submit pendaftaran berhasil.
 *
 * @var object|null  $pendaftaran   Record pendaftaran
 * @var list<object> $prodiPilihan  Pilihan prodi (join nama)
 * @var string       $namaInstitusi Nama institusi dari setting
 */

defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
jg_theme_colors();

$dashboardUrl = remove_query_arg(['action', 'ref'], (string) get_permalink());

if ($pendaftaran && !empty($pendaftaran->verifikasi_token)) {
    $qrUrl = home_url('/verifikasi/' . rawurlencode($pendaftaran->nomor_pendaftaran) . '/' . rawurlencode((string) $pendaftaran->verifikasi_token) . '/');
    $qrDataUri = (new \Webane\Jalagistrasi\Service\QrCodeService())->generateSvgDataUri($qrUrl, 160);
}
?>
<div id="jalagistrasi-wrap">
<div class="jg-page" style="display:flex;align-items:center;justify-content:center;padding:32px 16px;">
    <div style="max-width:440px;width:100%;">

        <?php if (!$pendaftaran) : ?>
            <div class="jg-card" style="text-align:center;">
                <p class="jg-card-sub"><?php esc_html_e('Pendaftaran tidak ditemukan.', 'jalagistrasi'); ?></p>
                <a href="<?php echo esc_url($dashboardUrl); ?>" class="jg-link" style="display:inline-block;margin-top:10px;">
                    <?php esc_html_e('Kembali ke dashboard', 'jalagistrasi'); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="jg-card" style="text-align:center;">

                <div class="jg-sukses-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>

                <h1 class="jg-card-title" style="font-size:19px;margin-top:16px;">
                    <?php esc_html_e('Pendaftaran Berhasil!', 'jalagistrasi'); ?>
                </h1>

                <?php if ($namaInstitusi) : ?>
                    <p class="jg-card-sub" style="margin-top:4px;"><?php echo esc_html($namaInstitusi); ?></p>
                <?php endif; ?>

                <?php if (!empty($qrDataUri)) : ?>
                    <img src="<?php echo esc_attr($qrDataUri); ?>" alt="QR Verifikasi" class="jg-sukses-qr">
                    <p class="jg-field-hint" style="margin-top:0;margin-bottom:18px;">
                        <?php esc_html_e('Simpan QR ini — bisa dipakai sebagai bukti verifikasi saat tes/seleksi.', 'jalagistrasi'); ?>
                    </p>
                <?php endif; ?>

                <!-- Nomor pendaftaran -->
                <div class="jg-sukses-nomor-box">
                    <p class="jg-sukses-nomor-label"><?php esc_html_e('Nomor Pendaftaran', 'jalagistrasi'); ?></p>
                    <p class="jg-sukses-nomor-value"><?php echo esc_html($pendaftaran->nomor_pendaftaran); ?></p>
                    <p class="jg-field-hint" style="margin-top:4px;"><?php esc_html_e('Simpan nomor ini sebagai referensi.', 'jalagistrasi'); ?></p>
                </div>

                <!-- Info gelombang -->
                <div class="jg-verif-detail" style="margin-top:18px;">
                    <div class="jg-verif-detail-row">
                        <span><?php esc_html_e('Gelombang', 'jalagistrasi'); ?></span>
                        <strong><?php echo esc_html($pendaftaran->gelombang_nama ?? '—'); ?></strong>
                    </div>
                    <div class="jg-verif-detail-row">
                        <span><?php esc_html_e('Status', 'jalagistrasi'); ?></span>
                        <span class="jg-badge jg-badge--action"><?php esc_html_e('Formulir Disubmit', 'jalagistrasi'); ?></span>
                    </div>
                    <?php if ($pendaftaran->submitted_at) : ?>
                        <div class="jg-verif-detail-row">
                            <span><?php esc_html_e('Dikirim pada', 'jalagistrasi'); ?></span>
                            <strong><?php echo esc_html(date_i18n('d M Y, H:i', strtotime($pendaftaran->submitted_at))); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pilihan prodi -->
                <?php if (!empty($prodiPilihan)) : ?>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.08);text-align:left;">
                        <p class="jg-section-title" style="margin-bottom:8px;"><?php esc_html_e('Pilihan Program Studi', 'jalagistrasi'); ?></p>
                        <?php foreach ($prodiPilihan as $pp) : ?>
                            <div class="jg-prodi-row">
                                <span class="jg-prodi-number"><?php echo (int) $pp->urutan; ?></span>
                                <span>
                                    <?php echo esc_html($pp->prodi_nama); ?>
                                    <span style="color:rgba(255,255,255,0.4);">(<?php echo esc_html($pp->prodi_kode); ?>)</span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <a href="<?php echo esc_url($dashboardUrl); ?>" class="jg-btn jg-btn--outline jg-btn--block" style="margin-top:20px;">
                    <?php esc_html_e('Kembali ke Dashboard', 'jalagistrasi'); ?>
                </a>

            </div>
        <?php endif; ?>

    </div>
</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-sukses-icon {
    width: 56px; height: 56px; margin: 0 auto; border-radius: 9999px;
    background: rgba(34, 197, 94, 0.15); color: #86efac;
    display: flex; align-items: center; justify-content: center;
}
#jalagistrasi-wrap .jg-sukses-qr {
    width: 150px; height: 150px; margin: 18px auto 10px; display: block;
    background: #fff; padding: 10px; border-radius: 12px;
}
#jalagistrasi-wrap .jg-sukses-nomor-box {
    margin-top: 18px; padding: 16px; border-radius: 14px; text-align: center;
    background: rgba(<?php echo esc_html(jg_theme_colors()['brandRgb']); ?>, 0.12);
    border: 1px solid rgba(<?php echo esc_html(jg_theme_colors()['brandRgb']); ?>, 0.3);
}
#jalagistrasi-wrap .jg-sukses-nomor-label {
    margin: 0; font-size: 11px; font-weight: 600; letter-spacing: 0.04em;
    text-transform: uppercase; color: #93c5fd;
}
#jalagistrasi-wrap .jg-sukses-nomor-value {
    margin: 4px 0 0; font-size: 22px; font-weight: 700; letter-spacing: 0.04em; color: #fff;
}

#jalagistrasi-wrap .jg-prodi-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: 13px; color: rgba(255, 255, 255, 0.8); }
#jalagistrasi-wrap .jg-prodi-number {
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    width: 20px; height: 20px; border-radius: 9999px; font-size: 11px; font-weight: 700;
    background: rgba(<?php echo esc_html(jg_theme_colors()['brandRgb']); ?>, 0.2); color: #93c5fd;
}

#jalagistrasi-wrap .jg-verif-detail-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 0; font-size: 13px; text-align: left;
}
#jalagistrasi-wrap .jg-verif-detail-row span { color: rgba(255, 255, 255, 0.5); }
#jalagistrasi-wrap .jg-verif-detail-row strong { color: #fff; font-weight: 600; }
</style>
