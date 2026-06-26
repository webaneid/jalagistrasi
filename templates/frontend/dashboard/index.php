<?php
/**
 * Dashboard pendaftar — redesign total, lihat docs/arsitektur-dashboard-mahasiswa.md.
 *
 * @var \WP_User      $user
 * @var list<object>  $riwayat
 * @var list<object>  $tersedia
 * @var bool          $draftSavedNotif
 * @var bool          $berkasFinalizedNotif
 */
defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
$theme = jg_theme_colors();

// Prioritas tampilan identitas institusi: logo > nama institusi > nama situs WP.
// Pola sama dipakai di halaman info pendaftaran publik & login/daftar.
$namaInstitusi = (string) get_option('jalagistrasi_nama_institusi', '');
$logoId        = (int) get_option('jalagistrasi_logo_id', 0);
$logoUrl       = $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'medium') : '';
$namaTampil    = $namaInstitusi !== '' ? $namaInstitusi : (string) get_bloginfo('name');
$dashboardUrl  = (string) get_permalink();

// ---------------------------------------------------------------------------
// Pipeline 8 langkah — peta status ke (langkah aktif, kondisi visual).
// state: 'action' (perlu tindakan), 'waiting' (menunggu panitia),
//        'rejected' (ditolak, perlu revisi), 'success', 'failed'.
// ---------------------------------------------------------------------------
$langkahLabel = [
    1 => 'Registrasi Akun', 2 => 'Isi Formulir', 3 => 'Upload Berkas',
    4 => 'Verifikasi', 5 => 'Pembayaran', 6 => 'Tes/Seleksi',
    7 => 'Pengumuman', 8 => 'Daftar Ulang',
];
$stepMap = [
    'draft'                 => ['step' => 2, 'state' => 'action'],
    'submitted'             => ['step' => 3, 'state' => 'action'],
    'berkas_diupload'       => ['step' => 4, 'state' => 'waiting'],
    'berkas_ditolak'        => ['step' => 3, 'state' => 'rejected'],
    'berkas_diverifikasi'   => ['step' => 5, 'state' => 'action'],
    'pembayaran_diupload'   => ['step' => 5, 'state' => 'waiting'],
    'pembayaran_ditolak'    => ['step' => 5, 'state' => 'rejected'],
    'dijadwalkan_tes'       => ['step' => 6, 'state' => 'waiting'],
    'diumumkan_lulus'       => ['step' => 7, 'state' => 'success'],
    'diumumkan_tidak_lulus' => ['step' => 7, 'state' => 'failed'],
    'daftar_ulang'          => ['step' => 8, 'state' => 'action'],
    'selesai'               => ['step' => 8, 'state' => 'success'],
    'gagal_daftar_ulang'    => ['step' => 8, 'state' => 'failed'],
];
$statusLabelTeks = [
    'draft'                 => 'Belum Disubmit',
    'submitted'             => 'Formulir Disubmit',
    'berkas_diupload'       => 'Menunggu Verifikasi Dokumen',
    'berkas_ditolak'        => 'Dokumen Perlu Direvisi',
    'berkas_diverifikasi'   => 'Dokumen Terverifikasi',
    'pembayaran_diupload'   => 'Menunggu Verifikasi Pembayaran',
    'pembayaran_ditolak'    => 'Pembayaran Perlu Direvisi',
    'dijadwalkan_tes'       => 'Dijadwalkan Tes',
    'diumumkan_lulus'       => 'Lulus Seleksi',
    'diumumkan_tidak_lulus' => 'Tidak Lulus Seleksi',
    'daftar_ulang'          => 'Proses Daftar Ulang',
    'selesai'               => 'Selesai — Mahasiswa Baru',
    'gagal_daftar_ulang'    => 'Gagal Daftar Ulang',
];
$terminalStatuses = ['diumumkan_tidak_lulus', 'selesai', 'gagal_daftar_ulang'];

// Hero = pendaftaran yang masih berjalan (bukan status akhir); kalau tidak ada,
// pakai yang paling baru. Sisanya tampil sebagai daftar ringkas di bawah.
$heroPendaftaran = null;
foreach ($riwayat as $p) {
    if (!in_array($p->status, $terminalStatuses, true)) {
        $heroPendaftaran = $p;
        break;
    }
}
if (!$heroPendaftaran && !empty($riwayat)) {
    $heroPendaftaran = $riwayat[0];
}
$riwayatLain = array_filter($riwayat, fn ($p) => !$heroPendaftaran || $p->id !== $heroPendaftaran->id);

// current_time('H') sudah otomatis ikut timezone situs (Settings > General >
// Timezone di wp-admin) — kalau sapaan terasa salah jam, itu setting timezone
// situsnya yang perlu dicek, bukan logic ini.
$jam = (int) current_time('H');
$sapaanWaktu = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 19 ? 'Selamat sore' : 'Selamat malam'));
$sapaan = "Assalamu'alaikum, {$sapaanWaktu}";

$namaDepan = explode(' ', trim($user->display_name))[0] ?? $user->display_name;
$inisial   = mb_strtoupper(mb_substr($user->display_name, 0, 1));
?>
<div id="jalagistrasi-wrap">
<div class="jg-page">

    <!-- Top bar -->
    <div class="jg-topbar">
        <div class="jg-topbar-inner">
            <?php if ($logoUrl !== '') : ?>
                <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr($namaTampil); ?>" class="jg-brand-logo">
            <?php else : ?>
                <span class="jg-brand"><?php echo esc_html($namaTampil); ?></span>
            <?php endif; ?>
            <div class="jg-user">
                <span class="jg-avatar"><?php echo esc_html($inisial); ?></span>
                <span class="jg-user-name"><?php echo esc_html($user->display_name); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="jg-logout" title="<?php esc_attr_e('Keluar', 'jalagistrasi'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </div>

    <div class="jg-container">

        <!-- Notifikasi -->
        <?php if (!empty($draftSavedNotif)) : ?>
            <div class="jg-notif jg-notif--success">✓ <?php esc_html_e('Draft pendaftaran berhasil disimpan.', 'jalagistrasi'); ?></div>
        <?php endif; ?>
        <?php if (!empty($berkasFinalizedNotif)) : ?>
            <div class="jg-notif jg-notif--success">✓ <?php esc_html_e('Berkas berhasil diupload. Panitia akan segera memverifikasi.', 'jalagistrasi'); ?></div>
        <?php endif; ?>

        <!-- Sapaan -->
        <div class="jg-dash-greeting">
            <p class="jg-dash-greeting-sub"><?php echo esc_html($sapaan); ?> 👋</p>
            <h1 class="jg-dash-greeting-title"><?php echo esc_html($namaDepan); ?></h1>
        </div>

        <!-- ====================== HERO: Pendaftaran Aktif ====================== -->
        <?php if ($heroPendaftaran) : ?>
            <?php
            $p        = $heroPendaftaran;
            $map      = $stepMap[$p->status] ?? ['step' => 1, 'state' => 'action'];
            $curStep  = $map['step'];
            $curState = $map['state'];
            $isDraft  = $p->status === 'draft';
            $nomorTampil = (!$isDraft && $p->nomor_pendaftaran && strpos($p->nomor_pendaftaran, 'DRAFT-') !== 0) ? $p->nomor_pendaftaran : null;
            $detailUrl = add_query_arg(['action' => 'detail', 'pendaftaran_id' => $p->id], $dashboardUrl);
            $formUrl   = add_query_arg(['action' => 'form', 'gelombang_id' => $p->gelombang_id], $dashboardUrl);

            $ctaUrl   = $isDraft ? $formUrl : $detailUrl;
            $ctaLabel = match (true) {
                $isDraft                 => __('Lanjutkan Mengisi Formulir', 'jalagistrasi'),
                $curState === 'rejected' => __('Perbaiki Sekarang', 'jalagistrasi'),
                $curState === 'action'   => __('Lanjutkan', 'jalagistrasi'),
                default                   => __('Lihat Detail', 'jalagistrasi'),
            };
            ?>
            <div class="jg-dash-hero">
                <div class="jg-dash-hero-head">
                    <div>
                        <p class="jg-dash-hero-gelombang"><?php echo esc_html($p->gelombang_nama ?? '—'); ?></p>
                        <p class="jg-dash-hero-meta">
                            <?php echo esc_html($p->tahun_akademik ?? ''); ?>
                            <?php if ($nomorTampil) : ?> &middot; <?php echo esc_html($nomorTampil); ?><?php endif; ?>
                        </p>
                    </div>
                    <span class="jg-badge jg-badge--<?php echo esc_attr($curState); ?>">
                        <?php echo esc_html($statusLabelTeks[$p->status] ?? ucfirst($p->status)); ?>
                    </span>
                </div>

                <!-- Stepper -->
                <div class="jg-dash-stepper">
                    <?php for ($i = 1; $i <= 8; $i++) : ?>
                        <?php $kondisi = $i < $curStep ? 'done' : ($i === $curStep ? $curState : 'upcoming'); ?>
                        <div class="jg-dash-step jg-dash-step--<?php echo esc_attr($kondisi); ?>">
                            <span class="jg-dash-step-dot">
                                <?php if ($kondisi === 'done' || $kondisi === 'success') : ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <?php elseif ($kondisi === 'rejected' || $kondisi === 'failed') : ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                <?php else : ?>
                                    <?php echo $i; ?>
                                <?php endif; ?>
                            </span>
                            <span class="jg-dash-step-label"><?php echo esc_html($langkahLabel[$i]); ?></span>
                        </div>
                        <?php if ($i < 8) : ?><span class="jg-dash-step-line jg-dash-step-line--<?php echo $i < $curStep ? 'done' : 'upcoming'; ?>"></span><?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($curState === 'rejected' && !empty($p->catatan_panitia)) : ?>
                    <div class="jg-notif jg-notif--danger">
                        <strong><?php esc_html_e('Catatan Panitia:', 'jalagistrasi'); ?></strong>
                        <?php echo esc_html($p->catatan_panitia); ?>
                    </div>
                <?php endif; ?>

                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <a href="<?php echo esc_url($ctaUrl); ?>" class="jg-btn"><?php echo esc_html($ctaLabel); ?> →</a>

                    <?php if (!empty($p->verifikasi_token)) : ?>
                        <?php
                        $kartuPesertaUrl = home_url('/verifikasi/' . rawurlencode($p->nomor_pendaftaran) . '/' . rawurlencode((string) $p->verifikasi_token) . '/');
                        ?>
                        <a href="<?php echo esc_url($kartuPesertaUrl); ?>" target="_blank" rel="noopener" class="jg-btn jg-btn--outline">
                            <?php esc_html_e('Tampilkan Kartu CAMABA', 'jalagistrasi'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ====================== Gelombang Dibuka ====================== -->
        <?php if (!empty($tersedia)) : ?>
            <div class="jg-dash-section">
                <h2 class="jg-section-title"><?php esc_html_e('Pendaftaran Dibuka', 'jalagistrasi'); ?></h2>
                <div class="jg-dash-grid">
                    <?php foreach ($tersedia as $g) : ?>
                        <div class="jg-card">
                            <p class="jg-card-title"><?php echo esc_html($g->nama); ?></p>
                            <p class="jg-card-sub">
                                <?php echo esc_html($g->tahun_akademik); ?> &middot;
                                <?php esc_html_e('Tutup', 'jalagistrasi'); ?> <?php echo esc_html(date_i18n('d M Y', strtotime($g->tanggal_tutup))); ?>
                            </p>
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'form', 'gelombang_id' => $g->id], $dashboardUrl)); ?>" class="jg-btn jg-btn--small" style="margin-top:14px;">
                                <?php esc_html_e('Daftar Sekarang', 'jalagistrasi'); ?> →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ====================== Riwayat Lainnya ====================== -->
        <?php if (!empty($riwayatLain)) : ?>
            <div class="jg-dash-section">
                <h2 class="jg-section-title"><?php esc_html_e('Riwayat Lainnya', 'jalagistrasi'); ?></h2>
                <div class="jg-dash-list">
                    <?php foreach ($riwayatLain as $p) : ?>
                        <a href="<?php echo esc_url(add_query_arg(['action' => 'detail', 'pendaftaran_id' => $p->id], $dashboardUrl)); ?>" class="jg-dash-row">
                            <div>
                                <p class="jg-dash-row-title"><?php echo esc_html($p->gelombang_nama ?? '—'); ?></p>
                                <p class="jg-dash-row-sub"><?php echo esc_html($p->tahun_akademik ?? ''); ?></p>
                            </div>
                            <span class="jg-badge jg-badge--<?php echo esc_attr(($stepMap[$p->status] ?? ['state' => 'action'])['state']); ?>">
                                <?php echo esc_html($statusLabelTeks[$p->status] ?? ucfirst($p->status)); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ====================== Empty state ====================== -->
        <?php if (empty($tersedia) && empty($riwayat)) : ?>
            <div class="jg-empty">
                <p class="jg-empty-title"><?php esc_html_e('Belum ada gelombang pendaftaran yang aktif', 'jalagistrasi'); ?></p>
                <p class="jg-empty-sub"><?php esc_html_e('Pantau halaman ini — info gelombang baru akan muncul di sini.', 'jalagistrasi'); ?></p>
            </div>
        <?php endif; ?>

    </div>
</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-dash-greeting { margin-bottom: 24px; }
#jalagistrasi-wrap .jg-dash-greeting-sub { margin: 0 0 2px; font-size: 14px; color: rgba(255, 255, 255, 0.55); }
#jalagistrasi-wrap .jg-dash-greeting-title { margin: 0; font-size: 28px; font-weight: 700; color: #fff; }

/* Hero card — sedikit lebih besar dari .jg-card generik */
#jalagistrasi-wrap .jg-dash-hero {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 24px;
    padding: 26px 24px;
    margin-bottom: 28px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
}
#jalagistrasi-wrap .jg-dash-hero-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 22px; }
#jalagistrasi-wrap .jg-dash-hero-gelombang { margin: 0; font-size: 17px; font-weight: 700; color: #fff; }
#jalagistrasi-wrap .jg-dash-hero-meta { margin: 2px 0 0; font-size: 12px; color: rgba(255, 255, 255, 0.5); }

/* Stepper */
#jalagistrasi-wrap .jg-dash-stepper { display: flex; align-items: flex-start; overflow-x: auto; padding-bottom: 4px; margin: 0 -4px 20px; }
#jalagistrasi-wrap .jg-dash-step { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; width: 76px; }
#jalagistrasi-wrap .jg-dash-step-dot {
    display: flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 9999px; font-size: 11px; font-weight: 700; margin-bottom: 6px;
    background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.4); border: 1px solid rgba(255, 255, 255, 0.12);
}
#jalagistrasi-wrap .jg-dash-step-label { font-size: 10px; line-height: 1.3; text-align: center; color: rgba(255, 255, 255, 0.4); }
#jalagistrasi-wrap .jg-dash-step-line { flex-shrink: 0; width: 16px; height: 1px; background: rgba(255, 255, 255, 0.12); margin-top: 13px; }
#jalagistrasi-wrap .jg-dash-step-line--done { background: <?php echo esc_html($theme['brand']); ?>; }

#jalagistrasi-wrap .jg-dash-step--done .jg-dash-step-dot,
#jalagistrasi-wrap .jg-dash-step--success .jg-dash-step-dot { background: <?php echo esc_html($theme['brand']); ?>; color: #fff; border-color: transparent; }
#jalagistrasi-wrap .jg-dash-step--done .jg-dash-step-label,
#jalagistrasi-wrap .jg-dash-step--success .jg-dash-step-label { color: rgba(255, 255, 255, 0.75); }

#jalagistrasi-wrap .jg-dash-step--action .jg-dash-step-dot {
    background: <?php echo esc_html($theme['brand']); ?>; color: #fff; border-color: transparent;
    box-shadow: 0 0 0 4px rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.25);
}
#jalagistrasi-wrap .jg-dash-step--action .jg-dash-step-label { color: #fff; font-weight: 600; }

#jalagistrasi-wrap .jg-dash-step--waiting .jg-dash-step-dot { background: rgba(234, 179, 8, 0.18); color: #fde047; border-color: rgba(234, 179, 8, 0.4); }
#jalagistrasi-wrap .jg-dash-step--waiting .jg-dash-step-label { color: #fde047; font-weight: 600; }

#jalagistrasi-wrap .jg-dash-step--rejected .jg-dash-step-dot,
#jalagistrasi-wrap .jg-dash-step--failed .jg-dash-step-dot { background: rgba(239, 68, 68, 0.18); color: #fca5a5; border-color: rgba(239, 68, 68, 0.4); }
#jalagistrasi-wrap .jg-dash-step--rejected .jg-dash-step-label,
#jalagistrasi-wrap .jg-dash-step--failed .jg-dash-step-label { color: #fca5a5; font-weight: 600; }

/* Sections */
#jalagistrasi-wrap .jg-dash-section { margin-bottom: 28px; }
#jalagistrasi-wrap .jg-dash-grid { display: grid; gap: 14px; }
#jalagistrasi-wrap .jg-dash-list { display: flex; flex-direction: column; gap: 10px; }

#jalagistrasi-wrap .jg-dash-row {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px; padding: 14px 16px; text-decoration: none;
    transition: background-color .15s, border-color .15s;
}
#jalagistrasi-wrap .jg-dash-row:hover { background: rgba(255, 255, 255, 0.07); border-color: rgba(255, 255, 255, 0.16); }
#jalagistrasi-wrap .jg-dash-row-title { margin: 0; font-size: 14px; font-weight: 600; color: #fff; }
#jalagistrasi-wrap .jg-dash-row-sub { margin: 2px 0 0; font-size: 12px; color: rgba(255, 255, 255, 0.5); }
</style>
