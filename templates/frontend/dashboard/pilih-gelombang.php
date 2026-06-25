<?php
/**
 * Halaman pilih gelombang (tampil jika ada lebih dari 1 gelombang aktif tersedia).
 *
 * @var list<object> $gelombangList Gelombang aktif yang belum didaftari user
 */
defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
jg_theme_colors();

$dashboardUrl = (string) get_permalink();
?>
<div id="jalagistrasi-wrap">
<div class="jg-page">

    <div class="jg-topbar">
        <div class="jg-topbar-inner">
            <div class="jg-topbar-left">
                <a href="<?php echo esc_url($dashboardUrl); ?>" class="jg-back" aria-label="<?php esc_attr_e('Kembali', 'jalagistrasi'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="jg-brand"><?php esc_html_e('Pilih Gelombang Pendaftaran', 'jalagistrasi'); ?></span>
            </div>
        </div>
    </div>

    <div class="jg-container jg-container--narrow">

        <p class="jg-card-sub" style="margin-bottom:20px;">
            <?php esc_html_e('Pilih gelombang pendaftaran yang ingin Anda ikuti.', 'jalagistrasi'); ?>
        </p>

        <?php if (empty($gelombangList)) : ?>
            <div class="jg-empty">
                <p class="jg-empty-title"><?php esc_html_e('Tidak ada gelombang pendaftaran yang tersedia', 'jalagistrasi'); ?></p>
                <p class="jg-empty-sub" style="margin-bottom:16px;"><?php esc_html_e('Semua gelombang aktif sudah Anda ikuti, atau belum ada yang dibuka.', 'jalagistrasi'); ?></p>
                <a href="<?php echo esc_url($dashboardUrl); ?>" class="jg-btn jg-btn--outline jg-btn--small"><?php esc_html_e('Kembali ke Dashboard', 'jalagistrasi'); ?></a>
            </div>
        <?php else : ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($gelombangList as $g) : ?>
                    <a href="<?php echo esc_url(add_query_arg(['action' => 'form', 'gelombang_id' => $g->id], $dashboardUrl)); ?>" class="jg-card jg-pilih-row">
                        <div>
                            <p class="jg-card-title"><?php echo esc_html($g->nama); ?></p>
                            <p class="jg-card-sub"><?php echo esc_html($g->tahun_akademik); ?></p>
                            <p class="jg-card-sub" style="margin-top:8px;">
                                <?php esc_html_e('Buka:', 'jalagistrasi'); ?> <?php echo esc_html(date_i18n('d M Y', strtotime($g->tanggal_buka))); ?>
                                &middot;
                                <?php esc_html_e('Tutup:', 'jalagistrasi'); ?> <?php echo esc_html(date_i18n('d M Y', strtotime($g->tanggal_tutup))); ?>
                            </p>
                        </div>
                        <span class="jg-pilih-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-pilih-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    text-decoration: none;
    margin-bottom: 0;
    transition: border-color .15s, background-color .15s;
}
#jalagistrasi-wrap .jg-pilih-row:hover {
    border-color: rgba(<?php echo esc_html(jg_theme_colors()['brandRgb']); ?>, 0.5);
    background: rgba(255, 255, 255, 0.08);
}
#jalagistrasi-wrap .jg-pilih-arrow {
    flex-shrink: 0;
    color: rgba(255, 255, 255, 0.3);
}
#jalagistrasi-wrap .jg-pilih-row:hover .jg-pilih-arrow {
    color: rgba(<?php echo esc_html(jg_theme_colors()['brandRgb']); ?>, 1);
}
</style>
