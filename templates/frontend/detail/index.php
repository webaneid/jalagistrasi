<?php
/**
 * Detail pendaftaran — satu halaman terpadu: lihat formulir, upload/lihat
 * dokumen persyaratan, dan upload/lihat bukti pembayaran.
 * Lihat docs/arsitektur-pembayaran.md, "Keputusan UI: Satu Halaman Terpadu".
 *
 * @var object               $pendaftaran
 * @var object|null          $gelombang
 * @var array<string,list<array{field:object,nilai:mixed}>> $sections
 * @var list<object>         $prodiPilihan
 * @var list<object>         $berkasList
 * @var array<string,object> $tipeBerkasByKode
 * @var list<object>         $tipeBerkasList
 * @var array<string,object> $sudahUpload     tipe_berkas => berkas
 * @var bool                 $dokumenTerbuka  boleh upload/ganti dokumen sekarang?
 * @var bool                 $semuaLengkap
 * @var int                  $totalWajib
 * @var int                  $sudahWajib
 * @var string               $uploadError
 * @var string               $uploadSuccess
 * @var bool                 $berkasFinalized
 * @var list<object>         $rekeningAktif
 * @var object|null          $pembayaran
 * @var bool                 $pembayaranTerbuka
 * @var float|null           $totalSeharusnya
 * @var string               $pembayaranError
 * @var bool                 $pembayaranSuccess
 * @var bool                 $formUpdated      baru saja simpan perubahan formulir (edit mode)
 * @var bool                 $formBolehDiedit  boleh edit ulang formulir biodata sekarang?
 */
defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
$theme = jg_theme_colors();

$dashboardUrl = remove_query_arg(['action', 'pendaftaran_id'], (string) get_permalink());
$uploadAction = esc_url(admin_url('admin-post.php'));
$status       = $pendaftaran->status;

$statusLabel = [
    'draft'                 => 'Belum Disubmit',
    'submitted'             => 'Formulir Disubmit',
    'berkas_diupload'       => 'Berkas Diupload',
    'berkas_diverifikasi'   => 'Berkas Diverifikasi',
    'berkas_ditolak'        => 'Berkas Ditolak — Revisi',
    'pembayaran_diupload'   => 'Bukti Bayar Diupload',
    'pembayaran_ditolak'    => 'Pembayaran Ditolak',
    'dijadwalkan_tes'       => 'Dijadwalkan Tes',
    'diumumkan_lulus'       => 'Lulus Seleksi',
    'diumumkan_tidak_lulus' => 'Tidak Lulus Seleksi',
    'daftar_ulang'          => 'Proses Daftar Ulang',
    'selesai'               => 'Selesai',
    'gagal_daftar_ulang'    => 'Gagal Daftar Ulang',
];
$statusState = [
    'draft'                 => 'neutral',
    'submitted'             => 'action',
    'berkas_diupload'       => 'waiting',
    'berkas_diverifikasi'   => 'action',
    'berkas_ditolak'        => 'rejected',
    'pembayaran_diupload'   => 'waiting',
    'pembayaran_ditolak'    => 'rejected',
    'dijadwalkan_tes'       => 'waiting',
    'diumumkan_lulus'       => 'success',
    'diumumkan_tidak_lulus' => 'failed',
    'daftar_ulang'          => 'action',
    'selesai'               => 'success',
    'gagal_daftar_ulang'    => 'failed',
];
$badgeLabel = $statusLabel[$status] ?? ucfirst($status);
$badgeState = $statusState[$status] ?? 'neutral';

$verBadgeState = [
    'pending'  => ['label' => 'Menunggu Verifikasi', 'state' => 'waiting'],
    'diterima' => ['label' => 'Diterima', 'state' => 'success'],
    'ditolak'  => ['label' => 'Ditolak', 'state' => 'rejected'],
];

$berkasDitolakList = array_filter($berkasList, static fn ($b) => $b->status === 'ditolak');
$adaBerkasDitolak  = !empty($berkasDitolakList);

// Section formulir & dokumen sudah pernah disubmit kalau bukan draft.
$formulirSelesai = $status !== 'draft';
// Section pembayaran terkunci total kalau dokumen belum pernah diverifikasi.
$pembayaranTerkunciTotal = in_array($status, ['draft', 'submitted', 'berkas_diupload', 'berkas_ditolak'], true);

$formatNilai = function ($nilai): string {
    if (is_array($nilai)) {
        return implode(', ', array_map('strval', $nilai));
    }
    return (string) $nilai;
};

// Modal/lightbox HARUS dirender di luar .jg-card (backdrop-filter pada .jg-card
// membuat containing block baru untuk descendant position:fixed di Chrome/Firefox,
// sehingga z-index modal jadi terjebak di dalam stacking context kartu itu dan
// tertutup kartu lain yang muncul belakangan). Dikumpulkan di sini, dicetak
// setelah semua kartu ditutup.
$pendingModals = '';
?>
<div id="jalagistrasi-wrap">
<div class="jg-page">

    <div class="jg-topbar">
        <div class="jg-topbar-inner">
            <div class="jg-topbar-left">
                <a href="<?php echo esc_url($dashboardUrl); ?>" class="jg-back" aria-label="<?php esc_attr_e('Kembali', 'jalagistrasi'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="jg-brand"><?php esc_html_e('Detail Pendaftaran', 'jalagistrasi'); ?></span>
            </div>
            <span class="jg-badge jg-badge--<?php echo esc_attr($badgeState); ?>"><?php echo esc_html($badgeLabel); ?></span>
        </div>
    </div>

    <div class="jg-container jg-container--narrow">

        <p class="jg-card-sub" style="margin-bottom:20px;">
            <?php echo esc_html($gelombang?->nama ?? '—'); ?>
            <?php if ($pendaftaran->nomor_pendaftaran && strpos($pendaftaran->nomor_pendaftaran, 'DRAFT-') !== 0) : ?>
                &middot; <?php echo esc_html($pendaftaran->nomor_pendaftaran); ?>
            <?php endif; ?>
        </p>

        <!-- Loading overlay (dipakai semua form upload di halaman ini) -->
        <div id="jg-upload-loading" class="jg-loading-overlay">
            <div class="jg-loading-box">
                <div class="jg-loading-spinner"></div>
                <p class="jg-card-title" style="font-size:13px;"><?php esc_html_e('Mengupload…', 'jalagistrasi'); ?></p>
                <p class="jg-card-sub"><?php esc_html_e('Mohon tunggu sebentar.', 'jalagistrasi'); ?></p>
            </div>
        </div>
        <script>
        (function(){
            document.querySelectorAll('form').forEach(function(f){
                f.addEventListener('submit',function(){
                    var el=document.getElementById('jg-upload-loading');
                    if(el) el.style.display='flex';
                });
            });
        })();
        </script>

        <!-- Notifikasi -->
        <?php if (!empty($formUpdated)) : ?>
            <div class="jg-notif jg-notif--success">✓ <?php esc_html_e('Perubahan formulir berhasil disimpan.', 'jalagistrasi'); ?></div>
        <?php endif; ?>
        <?php if (!empty($berkasFinalized)) : ?>
            <div class="jg-notif jg-notif--success">✓ <?php esc_html_e('Dokumen berhasil diselesaikan. Panitia akan segera memverifikasi.', 'jalagistrasi'); ?></div>
        <?php endif; ?>
        <?php if ($uploadError !== '') : ?>
            <div class="jg-notif jg-notif--danger">✕ <?php echo esc_html($uploadError); ?></div>
        <?php endif; ?>
        <?php if ($uploadSuccess !== '') : ?>
            <div class="jg-notif jg-notif--success">✓ <?php printf(esc_html__('%s berhasil diupload.', 'jalagistrasi'), '<strong>' . esc_html($uploadSuccess) . '</strong>'); ?></div>
        <?php endif; ?>
        <?php if (!empty($pembayaranSuccess)) : ?>
            <div class="jg-notif jg-notif--success">✓ <?php esc_html_e('Bukti pembayaran berhasil diupload. Panitia akan segera memverifikasi.', 'jalagistrasi'); ?></div>
        <?php endif; ?>
        <?php if ($pembayaranError !== '') : ?>
            <div class="jg-notif jg-notif--danger">✕ <?php echo esc_html($pembayaranError); ?></div>
        <?php endif; ?>

        <!-- Catatan panitia (status besar ditolak) -->
        <?php if (in_array($status, ['berkas_ditolak', 'pembayaran_ditolak'], true) && !empty($pendaftaran->catatan_panitia)) : ?>
            <div class="jg-notif jg-notif--warning">
                <p style="font-weight:600;margin-bottom:2px;">
                    <?php echo $status === 'pembayaran_ditolak'
                        ? esc_html__('Pembayaran Anda perlu direvisi', 'jalagistrasi')
                        : esc_html__('Berkas Anda perlu direvisi', 'jalagistrasi'); ?>
                </p>
                <p><?php echo esc_html($pendaftaran->catatan_panitia); ?></p>
            </div>
        <?php endif; ?>

        <!-- Ada dokumen ditolak per-item — tampil terlepas dari status besar -->
        <?php if ($adaBerkasDitolak) : ?>
            <div class="jg-notif jg-notif--warning">
                <?php
                printf(
                    /* translators: %d: jumlah dokumen ditolak */
                    esc_html__('%d dokumen perlu diupload ulang. Lihat catatan di setiap dokumen pada section "Dokumen Persyaratan" di bawah.', 'jalagistrasi'),
                    count($berkasDitolakList)
                );
                ?>
            </div>
        <?php endif; ?>

        <!-- ================================================================
             SECTION 1: DATA FORMULIR
             ================================================================ -->
        <?php if ($status === 'draft') : ?>
            <a href="<?php echo esc_url(add_query_arg(['action' => 'form', 'gelombang_id' => $pendaftaran->gelombang_id], $dashboardUrl)); ?>"
               class="jg-btn jg-btn--block" style="margin-bottom:18px;">
                <?php esc_html_e('Lanjutkan Mengisi Formulir', 'jalagistrasi'); ?>
            </a>
        <?php elseif ($formBolehDiedit) : ?>
            <a href="<?php echo esc_url(add_query_arg(['action' => 'form', 'gelombang_id' => $pendaftaran->gelombang_id], $dashboardUrl)); ?>"
               class="jg-btn jg-btn--outline jg-btn--block" style="margin-bottom:18px;">
                <?php esc_html_e('Edit Formulir', 'jalagistrasi'); ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($prodiPilihan)) : ?>
            <div class="jg-card">
                <p class="jg-card-title" style="margin-bottom:12px;"><?php esc_html_e('Pilihan Program Studi', 'jalagistrasi'); ?></p>
                <?php foreach ($prodiPilihan as $pp) : ?>
                    <div class="jg-prodi-row">
                        <span class="jg-prodi-number"><?php echo (int) $pp->urutan; ?></span>
                        <span>
                            <?php echo esc_html($pp->prodi_nama ?? '—'); ?>
                            <?php if ($pp->prodi_kode) : ?>
                                <span style="color:rgba(255,255,255,0.4);">(<?php echo esc_html($pp->prodi_kode); ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($sections as $sectionName => $items) : ?>
            <?php if (empty($items)) continue; ?>
            <div class="jg-card">
                <p class="jg-card-title" style="margin-bottom:10px;"><?php echo esc_html($sectionName); ?></p>
                <?php foreach ($items as $item) : ?>
                    <?php $nilaiTampil = $formatNilai($item['nilai']); ?>
                    <div class="jg-detail-row">
                        <span class="jg-detail-label"><?php echo esc_html($item['field']->label); ?></span>
                        <span class="jg-detail-value">
                            <?php echo $nilaiTampil !== '' ? esc_html($nilaiTampil) : '<span style="color:rgba(255,255,255,0.25);">—</span>'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- ================================================================
             SECTION 2: DOKUMEN PERSYARATAN
             ================================================================ -->
        <div class="jg-card" style="<?php echo $formulirSelesai ? '' : 'opacity:.5;'; ?>">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <p class="jg-card-title"><?php esc_html_e('Dokumen Persyaratan', 'jalagistrasi'); ?></p>
                <?php if ($formulirSelesai && $totalWajib > 0) : ?>
                    <span class="jg-card-sub" style="font-weight:600;color:<?php echo $semuaLengkap ? '#86efac' : '#93c5fd'; ?>;">
                        <?php echo (int) $sudahWajib; ?>/<?php echo (int) $totalWajib; ?> <?php esc_html_e('wajib', 'jalagistrasi'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!$formulirSelesai) : ?>
                <p class="jg-card-sub"><?php esc_html_e('Selesaikan formulir pendaftaran dulu untuk membuka section ini.', 'jalagistrasi'); ?></p>
            <?php elseif (empty($tipeBerkasList)) : ?>
                <p class="jg-card-sub"><?php esc_html_e('Tidak ada dokumen yang perlu diupload untuk gelombang ini.', 'jalagistrasi'); ?></p>

            <?php elseif ($dokumenTerbuka) : ?>
                <!-- Mode aktif: bisa upload/ganti -->
                <div class="jg-doc-progress" style="margin-top:10px;margin-bottom:16px;">
                    <div class="jg-doc-progress-bar" style="width:<?php echo $totalWajib > 0 ? round($sudahWajib / $totalWajib * 100) : 0; ?>%;background:<?php echo $semuaLengkap ? '#22c55e' : esc_attr($theme['brand']); ?>;"></div>
                </div>

                <?php foreach ($tipeBerkasList as $tipe) : ?>
                    <?php
                    $berkas     = $sudahUpload[$tipe->kode] ?? null;
                    $uploaded   = $berkas !== null;
                    $isWajib    = (bool) $tipe->is_required;
                    $isImage    = $berkas && in_array($berkas->mime_type, ['image/jpeg', 'image/png'], true);
                    $previewUrl = $berkas ? esc_url(add_query_arg([
                        'action'    => 'jg_preview_berkas',
                        'berkas_id' => $berkas->id,
                        '_wpnonce'  => wp_create_nonce('jg_preview_berkas_' . $berkas->id),
                    ], admin_url('admin-ajax.php'))) : '';
                    $verStatus = $berkas->status ?? 'pending';
                    $verStatus = $verStatus ?: 'pending';
                    $verInfo   = $verBadgeState[$verStatus] ?? null;
                    ?>
                    <div class="jg-doc-item <?php echo $uploaded ? 'jg-doc-item--uploaded' : ''; ?>">
                        <div class="jg-doc-item-head">
                            <span class="jg-doc-icon <?php echo $uploaded ? 'jg-doc-icon--ok' : ''; ?>">
                                <?php if ($uploaded) : ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <?php else : ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <?php endif; ?>
                            </span>
                            <div class="jg-doc-item-info">
                                <p class="jg-doc-item-label">
                                    <?php echo esc_html($tipe->label); ?>
                                    <?php if ($isWajib) : ?><span class="req">*</span><?php endif; ?>
                                </p>
                                <?php if ($uploaded && $berkas) : ?>
                                    <p class="jg-card-sub" style="margin-top:2px;">
                                        <?php echo esc_html($berkas->file_name_original); ?>
                                        &middot; <?php echo number_format((int) $berkas->file_size / 1024, 0); ?> KB
                                    </p>
                                    <?php if ($verInfo) : ?>
                                        <span class="jg-badge jg-badge--<?php echo esc_attr($verInfo['state']); ?>" style="margin-top:6px;"><?php echo esc_html($verInfo['label']); ?></span>
                                    <?php endif; ?>
                                <?php elseif ($tipe->keterangan) : ?>
                                    <p class="jg-card-sub" style="margin-top:2px;"><?php echo esc_html($tipe->keterangan); ?></p>
                                <?php else : ?>
                                    <p class="jg-card-sub" style="margin-top:2px;">JPG, PNG, PDF &middot; maks <?php echo number_format((int) $tipe->max_size_kb); ?> KB</p>
                                <?php endif; ?>
                            </div>

                            <form method="post" action="<?php echo $uploadAction; ?>" enctype="multipart/form-data" style="flex-shrink:0;">
                                <?php wp_nonce_field('jg_upload_berkas'); ?>
                                <input type="hidden" name="action" value="jg_upload_berkas_item">
                                <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">
                                <input type="hidden" name="tipe_berkas_id" value="<?php echo esc_attr($tipe->id); ?>">
                                <label class="jg-doc-upload-btn <?php echo $uploaded ? 'jg-doc-upload-btn--ghost' : ''; ?>">
                                    <?php if (!$uploaded) : ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    <?php endif; ?>
                                    <?php echo $uploaded ? esc_html__('Ganti', 'jalagistrasi') : esc_html__('Upload', 'jalagistrasi'); ?>
                                    <input type="file" name="berkas_file" accept=".jpg,.jpeg,.png,.pdf" required
                                           style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"
                                           onchange="this.closest('form').submit();">
                                </label>
                            </form>
                        </div>

                        <?php if ($uploaded) : ?>
                            <?php if ($isImage) : ?>
                                <div class="jg-doc-preview">
                                    <img src="<?php echo $previewUrl; ?>" alt="<?php echo esc_attr($berkas->file_name_original); ?>" loading="lazy">
                                </div>
                            <?php else : ?>
                                <div class="jg-doc-item-foot">
                                    <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener" class="jg-link"><?php esc_html_e('Lihat file', 'jalagistrasi'); ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (($berkas->status ?? '') === 'ditolak' && $berkas->catatan) : ?>
                                <div class="jg-doc-tolak-note">
                                    <strong><?php esc_html_e('Alasan ditolak:', 'jalagistrasi'); ?></strong>
                                    <?php echo esc_html($berkas->catatan); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (!$semuaLengkap) : ?>
                    <div class="jg-notif jg-notif--warning" style="text-align:center;margin-top:8px;">
                        <?php printf(
                            esc_html__('Masih perlu %d dokumen wajib lagi sebelum melanjutkan.', 'jalagistrasi'),
                            $totalWajib - $sudahWajib
                        ); ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?php echo $uploadAction; ?>" style="margin-top:8px;">
                    <?php wp_nonce_field('jg_finalize_berkas'); ?>
                    <input type="hidden" name="action" value="jg_finalize_berkas">
                    <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">
                    <button type="submit" <?php echo !$semuaLengkap ? 'disabled' : ''; ?> class="jg-btn jg-btn--block <?php echo !$semuaLengkap ? 'is-disabled' : ''; ?>">
                        <?php esc_html_e('Selesaikan Upload Dokumen', 'jalagistrasi'); ?>
                        <?php if ($semuaLengkap) : ?> →<?php endif; ?>
                    </button>
                </form>

            <?php else : ?>
                <!-- Mode read-only: dokumen sudah diselesaikan (lewat tahap upload) -->
                <div class="jg-doc-grid" style="margin-top:8px;">
                    <?php foreach ($berkasList as $berkas) : ?>
                        <?php
                        $tipe       = $tipeBerkasByKode[$berkas->tipe_berkas] ?? null;
                        $isImage    = in_array($berkas->mime_type, ['image/jpeg', 'image/png'], true);
                        $isPdf      = $berkas->mime_type === 'application/pdf';
                        $previewUrl = esc_url(add_query_arg([
                            'action'    => 'jg_preview_berkas',
                            'berkas_id' => $berkas->id,
                            '_wpnonce'  => wp_create_nonce('jg_preview_berkas_' . $berkas->id),
                        ], admin_url('admin-ajax.php')));
                        $modalId   = 'jg-doc-modal-' . (int) $berkas->id;
                        $verStatus = $berkas->status ?: 'pending';
                        $verInfo   = $verBadgeState[$verStatus] ?? null;
                        ?>
                        <button type="button"
                                onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').classList.remove('jg-hidden')"
                                class="jg-doc-thumb-btn">
                            <?php if ($isImage) : ?>
                                <div class="jg-doc-thumb-img"><img src="<?php echo $previewUrl; ?>" alt="<?php echo esc_attr($berkas->file_name_original); ?>" loading="lazy"></div>
                            <?php else : ?>
                                <div class="jg-doc-thumb-img jg-doc-thumb-img--file">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                            <?php endif; ?>
                            <div class="jg-doc-thumb-foot">
                                <p class="jg-doc-thumb-label"><?php echo esc_html($tipe->label ?? ucfirst($berkas->tipe_berkas)); ?></p>
                                <?php if ($verInfo) : ?>
                                    <span class="jg-badge jg-badge--<?php echo esc_attr($verInfo['state']); ?>" style="margin-top:4px;font-size:10px;padding:3px 8px;"><?php echo esc_html($verInfo['label']); ?></span>
                                <?php endif; ?>
                            </div>
                        </button>

                        <?php
                        ob_start();
                        ?>
                        <div id="<?php echo esc_attr($modalId); ?>" class="jg-lightbox jg-hidden" onclick="if(event.target===this)this.classList.add('jg-hidden')">
                            <div class="jg-lightbox-inner jg-lightbox-inner--doc">
                                <div class="jg-lightbox-doc-head">
                                    <p class="jg-card-title" style="font-size:13px;"><?php echo esc_html($tipe->label ?? ucfirst($berkas->tipe_berkas)); ?></p>
                                    <button type="button" onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').classList.add('jg-hidden')" class="jg-lightbox-close-inline">✕</button>
                                </div>
                                <?php if ($isImage) : ?>
                                    <img src="<?php echo $previewUrl; ?>" alt="<?php echo esc_attr($berkas->file_name_original); ?>" style="width:100%;max-height:70vh;object-fit:contain;display:block;">
                                <?php elseif ($isPdf) : ?>
                                    <iframe src="<?php echo $previewUrl; ?>" style="width:100%;height:70vh;border:0;"></iframe>
                                <?php endif; ?>
                                <div class="jg-lightbox-doc-foot">
                                    <p class="jg-card-sub" style="margin:0;"><?php echo esc_html($berkas->file_name_original); ?> &middot; <?php echo number_format((int) $berkas->file_size / 1024, 0); ?> KB</p>
                                    <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener" class="jg-link"><?php esc_html_e('Buka tab baru', 'jalagistrasi'); ?></a>
                                </div>
                            </div>
                        </div>
                        <?php
                        $pendingModals .= ob_get_clean();
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================================================================
             SECTION 3: BUKTI PEMBAYARAN
             ================================================================ -->
        <div class="jg-card" style="<?php echo $pembayaranTerkunciTotal ? 'opacity:.5;' : ''; ?>">
            <p class="jg-card-title" style="margin-bottom:8px;"><?php esc_html_e('Bukti Pembayaran', 'jalagistrasi'); ?></p>

            <?php if ($pembayaranTerkunciTotal) : ?>
                <p class="jg-card-sub"><?php esc_html_e('Section ini terbuka setelah dokumen Anda diverifikasi panitia.', 'jalagistrasi'); ?></p>

            <?php elseif ($pembayaranTerbuka) : ?>
                <!-- Mode aktif: upload bukti transfer -->
                <div class="jg-pay-highlight">
                    <p class="jg-card-sub" style="margin-bottom:4px;"><?php esc_html_e('Transfer tepat ke salah satu rekening di bawah:', 'jalagistrasi'); ?></p>
                    <p class="jg-pay-amount">Rp <?php echo esc_html(number_format((float) $totalSeharusnya, 0, ',', '.')); ?></p>
                    <p class="jg-card-sub" style="margin-top:6px;">
                        <?php
                        printf(
                            /* translators: %s: kode unik 3 digit */
                            esc_html__('Termasuk kode unik %s — wajib transfer pas (tidak dibulatkan) agar mudah diverifikasi.', 'jalagistrasi'),
                            '<strong>' . esc_html(sprintf('%03d', (int) $pendaftaran->kode_unik_pembayaran)) . '</strong>'
                        );
                        ?>
                    </p>
                </div>

                <?php if (empty($rekeningAktif)) : ?>
                    <p class="jg-card-sub"><?php esc_html_e('Belum ada rekening tujuan yang dikonfigurasi. Hubungi panitia.', 'jalagistrasi'); ?></p>
                <?php else : ?>
                    <form method="post" action="<?php echo $uploadAction; ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('jg_upload_pembayaran'); ?>
                        <input type="hidden" name="action" value="jg_upload_pembayaran">
                        <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">

                        <div class="jg-field">
                            <label><?php esc_html_e('Rekening Tujuan', 'jalagistrasi'); ?></label>
                            <?php foreach ($rekeningAktif as $i => $rek) : ?>
                                <label class="jg-radio-row">
                                    <input type="radio" name="rekening_bank_id" value="<?php echo esc_attr($rek->id); ?>" <?php echo $i === 0 ? 'checked' : ''; ?> required>
                                    <span>
                                        <strong><?php echo esc_html($rek->nama_bank); ?></strong> —
                                        <?php echo esc_html($rek->nomor_rekening); ?>
                                        <span style="color:rgba(255,255,255,0.4);">(<?php echo esc_html($rek->nama_pemilik); ?>)</span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="jg-field">
                            <label for="jg-jumlah"><?php esc_html_e('Nominal yang Anda transfer', 'jalagistrasi'); ?></label>
                            <input type="number" id="jg-jumlah" name="jumlah" min="1" step="1"
                                   value="<?php echo esc_attr((string) (int) $totalSeharusnya); ?>" class="jg-input" required>
                        </div>

                        <div class="jg-field">
                            <label for="jg-pengirim">
                                <?php esc_html_e('Nama Pengirim', 'jalagistrasi'); ?>
                                <span style="color:rgba(255,255,255,0.4);font-weight:400;">(<?php esc_html_e('opsional, jika beda dari nama Anda', 'jalagistrasi'); ?>)</span>
                            </label>
                            <input type="text" id="jg-pengirim" name="nama_pengirim" class="jg-input">
                        </div>

                        <div class="jg-field">
                            <label><?php esc_html_e('Bukti Transfer (JPG/PNG/PDF)', 'jalagistrasi'); ?></label>
                            <input type="file" name="bukti_file" accept=".jpg,.jpeg,.png,.pdf" required class="jg-file-input">
                        </div>

                        <button type="submit" class="jg-btn jg-btn--block"><?php esc_html_e('Kirim Bukti Pembayaran', 'jalagistrasi'); ?></button>
                    </form>
                <?php endif; ?>

            <?php elseif ($pembayaran) : ?>
                <!-- Mode read-only: sudah upload, menunggu/selesai verifikasi -->
                <?php
                $isImage = in_array($pembayaran->mime_type, ['image/jpeg', 'image/png'], true);
                $isPdf   = $pembayaran->mime_type === 'application/pdf';
                $previewUrl = esc_url(add_query_arg([
                    'action'        => 'jg_preview_pembayaran',
                    'pembayaran_id' => $pembayaran->id,
                    '_wpnonce'      => wp_create_nonce('jg_preview_pembayaran_' . $pembayaran->id),
                ], admin_url('admin-ajax.php')));
                $modalId = 'jg-pay-modal-' . (int) $pembayaran->id;
                ?>
                <p class="jg-card-sub" style="margin-bottom:10px;"><?php esc_html_e('Bukti pembayaran sudah dikirim, menunggu verifikasi panitia.', 'jalagistrasi'); ?></p>
                <button type="button"
                        onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').classList.remove('jg-hidden')"
                        class="jg-doc-item" style="display:flex;align-items:center;gap:12px;width:100%;text-align:left;cursor:pointer;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);">
                    <span class="jg-doc-icon">
                        <?php if ($isImage) : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?php else : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <?php endif; ?>
                    </span>
                    <span>
                        <span class="jg-doc-item-label" style="display:block;"><?php echo esc_html($pembayaran->file_name_original); ?></span>
                        <span class="jg-card-sub">
                            Rp <?php echo esc_html(number_format((float) $pembayaran->jumlah, 0, ',', '.')); ?>
                            &middot; <?php echo number_format((int) $pembayaran->file_size / 1024, 0); ?> KB
                        </span>
                    </span>
                </button>

                <?php
                ob_start();
                ?>
                <div id="<?php echo esc_attr($modalId); ?>" class="jg-lightbox jg-hidden" onclick="if(event.target===this)this.classList.add('jg-hidden')">
                    <div class="jg-lightbox-inner jg-lightbox-inner--doc">
                        <div class="jg-lightbox-doc-head">
                            <p class="jg-card-title" style="font-size:13px;"><?php esc_html_e('Bukti Pembayaran', 'jalagistrasi'); ?></p>
                            <button type="button" onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').classList.add('jg-hidden')" class="jg-lightbox-close-inline">✕</button>
                        </div>
                        <?php if ($isImage) : ?>
                            <img src="<?php echo $previewUrl; ?>" alt="<?php echo esc_attr($pembayaran->file_name_original); ?>" style="width:100%;max-height:70vh;object-fit:contain;display:block;">
                        <?php elseif ($isPdf) : ?>
                            <iframe src="<?php echo $previewUrl; ?>" style="width:100%;height:70vh;border:0;"></iframe>
                        <?php endif; ?>
                        <div class="jg-lightbox-doc-foot">
                            <p class="jg-card-sub" style="margin:0;"><?php echo esc_html($pembayaran->file_name_original); ?> &middot; <?php echo number_format((int) $pembayaran->file_size / 1024, 0); ?> KB</p>
                            <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener" class="jg-link"><?php esc_html_e('Buka tab baru', 'jalagistrasi'); ?></a>
                        </div>
                    </div>
                </div>
                <?php
                $pendingModals .= ob_get_clean();
                ?>
            <?php else : ?>
                <p class="jg-card-sub"><?php esc_html_e('Belum ada bukti pembayaran.', 'jalagistrasi'); ?></p>
            <?php endif; ?>
        </div>

        <?php echo $pendingModals; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML sudah di-escape per-field saat dibuat di atas ?>

    </div>
</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-prodi-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 13px; color: rgba(255, 255, 255, 0.8); }
#jalagistrasi-wrap .jg-prodi-row + .jg-prodi-row { border-top: 1px solid rgba(255, 255, 255, 0.06); }
#jalagistrasi-wrap .jg-prodi-number {
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    width: 22px; height: 22px; border-radius: 9999px; font-size: 11px; font-weight: 700;
    background: rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.2); color: #93c5fd;
}

#jalagistrasi-wrap .jg-detail-row { padding: 10px 0; border-top: 1px solid rgba(255, 255, 255, 0.06); }
#jalagistrasi-wrap .jg-detail-row:first-of-type { border-top: 0; }
#jalagistrasi-wrap .jg-detail-label { display: block; font-size: 11px; color: rgba(255, 255, 255, 0.4); margin-bottom: 2px; }
#jalagistrasi-wrap .jg-detail-value { display: block; font-size: 14px; font-weight: 500; color: rgba(255, 255, 255, 0.9); }

#jalagistrasi-wrap .jg-loading-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center;
    padding: 16px; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(2px);
}
#jalagistrasi-wrap .jg-loading-box {
    background: rgba(20, 24, 34, 0.95); border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px; padding: 28px 32px; text-align: center; max-width: 280px; margin: 0 16px;
}
#jalagistrasi-wrap .jg-loading-spinner {
    width: 36px; height: 36px; margin: 0 auto 14px;
    border: 3px solid rgba(255, 255, 255, 0.12); border-top-color: <?php echo esc_html($theme['brand']); ?>;
    border-radius: 50%; animation: jg-spin .7s linear infinite;
}
@keyframes jg-spin { to { transform: rotate(360deg); } }

#jalagistrasi-wrap .jg-doc-progress { height: 6px; width: 100%; border-radius: 9999px; background: rgba(255, 255, 255, 0.08); overflow: hidden; }
#jalagistrasi-wrap .jg-doc-progress-bar { height: 100%; border-radius: 9999px; transition: width .4s; }

#jalagistrasi-wrap .jg-doc-item {
    border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; margin-bottom: 10px; overflow: hidden;
    background: rgba(255, 255, 255, 0.03);
}
#jalagistrasi-wrap .jg-doc-item--uploaded { border-color: rgba(34, 197, 94, 0.25); }
#jalagistrasi-wrap .jg-doc-item-head { display: flex; align-items: center; gap: 14px; padding: 14px; }
#jalagistrasi-wrap .jg-doc-icon {
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    width: 32px; height: 32px; border-radius: 9999px; background: rgba(255, 255, 255, 0.07); color: rgba(255, 255, 255, 0.4);
}
#jalagistrasi-wrap .jg-doc-icon--ok { background: rgba(34, 197, 94, 0.15); color: #86efac; }
#jalagistrasi-wrap .jg-doc-item-info { flex: 1; min-width: 0; }
#jalagistrasi-wrap .jg-doc-item-label { margin: 0; font-size: 13px; font-weight: 600; color: #fff; }
#jalagistrasi-wrap .jg-doc-item-foot { padding: 0 14px 14px; }
#jalagistrasi-wrap .jg-doc-preview { padding: 0 14px 14px; }
#jalagistrasi-wrap .jg-doc-preview img {
    width: 100%; height: 130px; object-fit: contain; border-radius: 12px;
    background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08);
}
#jalagistrasi-wrap .jg-doc-tolak-note {
    margin: 0 14px 14px; padding: 10px 12px; border-radius: 10px; font-size: 12px;
    background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #fecaca;
}

#jalagistrasi-wrap .jg-doc-upload-btn {
    position: relative; display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 9px; font-size: 12px; font-weight: 600; cursor: pointer;
    background: <?php echo esc_html($theme['brand']); ?>; color: #fff;
}
#jalagistrasi-wrap .jg-doc-upload-btn--ghost { background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.75); }

#jalagistrasi-wrap .jg-doc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
#jalagistrasi-wrap .jg-doc-thumb-btn {
    display: block; text-align: left; border-radius: 14px; overflow: hidden; cursor: pointer;
    background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); padding: 0; width: 100%;
}
#jalagistrasi-wrap .jg-doc-thumb-img { height: 96px; background: rgba(255, 255, 255, 0.04); display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.3); }
#jalagistrasi-wrap .jg-doc-thumb-img img { width: 100%; height: 100%; object-fit: cover; }
#jalagistrasi-wrap .jg-doc-thumb-foot { padding: 8px 10px; }
#jalagistrasi-wrap .jg-doc-thumb-label { margin: 0; font-size: 12px; font-weight: 600; color: rgba(255, 255, 255, 0.85); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

#jalagistrasi-wrap .jg-lightbox {
    position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center;
    padding: 16px; background: rgba(0, 0, 0, 0.8);
}
#jalagistrasi-wrap .jg-lightbox.jg-hidden { display: none; }
#jalagistrasi-wrap .jg-lightbox-inner { position: relative; max-width: 540px; width: 100%; }
#jalagistrasi-wrap .jg-lightbox-inner--doc {
    max-width: 640px; background: rgba(20, 24, 34, 0.97); border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px; overflow: hidden;
}
#jalagistrasi-wrap .jg-lightbox-doc-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
#jalagistrasi-wrap .jg-lightbox-doc-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 14px; border-top: 1px solid rgba(255, 255, 255, 0.08); }
#jalagistrasi-wrap .jg-lightbox-close-inline {
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    width: 26px; height: 26px; border-radius: 9999px; border: 0; background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7); font-size: 12px; cursor: pointer;
}
#jalagistrasi-wrap .jg-lightbox-close-inline:hover { background: rgba(255, 255, 255, 0.18); color: #fff; }
#jalagistrasi-wrap .jg-lightbox-close {
    position: absolute; top: -14px; right: -14px; width: 30px; height: 30px; border-radius: 9999px; border: 0;
    background: #fff; color: #111; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}

#jalagistrasi-wrap .jg-pay-highlight {
    border-radius: 14px; padding: 14px 16px; margin-bottom: 16px;
    background: rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.12);
    border: 1px solid rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.3);
}
#jalagistrasi-wrap .jg-pay-amount { margin: 0; font-size: 26px; font-weight: 700; color: #fff; }

#jalagistrasi-wrap .jg-file-input {
    display: block; width: 100%; font-size: 13px; color: rgba(255, 255, 255, 0.6);
}
#jalagistrasi-wrap .jg-file-input::file-selector-button {
    margin-right: 12px; padding: 7px 14px; border-radius: 9px; border: 1px solid rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.85); font-size: 12px; cursor: pointer;
}
#jalagistrasi-wrap .jg-file-input::file-selector-button:hover { background: rgba(255, 255, 255, 0.14); }
</style>
