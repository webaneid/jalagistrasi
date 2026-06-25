<?php
/**
 * Admin — detail pendaftar.
 *
 * @var object               $pendaftaran
 * @var object|null          $gelombang
 * @var \WP_User|false       $wpUser
 * @var array<string,list<object>> $sections      field per seksi
 * @var array<int,object>    $jawabanMap    field_id => jawaban
 * @var array<string,object> $berkasMap     tipe_berkas => berkas
 * @var list<object>         $tipeBerkasList tipe berkas yang dikonfigurasi untuk gelombang ini
 * @var list<object>         $prodiPilihan
 * @var \Webane\Jalagistrasi\Enum\StatusPendaftaran|null $currentStatus
 * @var list<\Webane\Jalagistrasi\Enum\StatusPendaftaran> $nextTransitions
 * @var object|null          $pembayaran      bukti transfer (jg_pembayaran), null jika belum upload
 * @var object|null          $rekeningBank    rekening yang dipilih pendaftar saat upload
 * @var float|null           $totalSeharusnya biaya_pendaftaran + kode_unik, null jika kode unik belum dibuat
 * @var bool                 $siapDiverifikasi semua dokumen wajib sudah diterima tapi status besar belum dipindah
 */
defined('ABSPATH') || exit;

use Webane\Jalagistrasi\Enum\StatusPendaftaran;

$listUrl = admin_url('admin.php?page=jg-pendaftar');

$updated = sanitize_key($_GET['updated'] ?? '');

$statusColor = [
    'submitted'             => '#2563eb',
    'berkas_diupload'       => '#0891b2',
    'pembayaran_diupload'   => '#4f46e5',
    'berkas_diverifikasi'   => '#0d9488',
    'berkas_ditolak'        => '#ea580c',
    'dijadwalkan_tes'       => '#7c3aed',
    'diumumkan_lulus'       => '#16a34a',
    'diumumkan_tidak_lulus' => '#dc2626',
    'daftar_ulang'          => '#0d9488',
    'selesai'               => '#16a34a',
    'gagal_daftar_ulang'    => '#dc2626',
    'draft'                 => '#6b7280',
];
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Detail Pendaftar', 'jalagistrasi'); ?>
    </h1>
    <a href="<?php echo esc_url($listUrl); ?>" class="page-title-action">
        ← <?php esc_html_e('Kembali ke Daftar', 'jalagistrasi'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ($updated === '1') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Status pendaftaran berhasil diperbarui.', 'jalagistrasi'); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-top:16px;align-items:start;">

        <!-- Kolom kiri: Data pendaftar -->
        <div>
            <!-- Ringkasan -->
            <div class="postbox" style="margin-bottom:16px;">
                <div class="postbox-header"><h2><?php esc_html_e('Ringkasan', 'jalagistrasi'); ?></h2></div>
                <div class="inside" style="margin:0;padding:16px;">
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:160px;"><?php esc_html_e('Nomor Pendaftaran', 'jalagistrasi'); ?></th>
                            <td><code style="font-size:14px;"><?php echo esc_html($pendaftaran->nomor_pendaftaran); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Pendaftar', 'jalagistrasi'); ?></th>
                            <td>
                                <?php if ($wpUser) : ?>
                                    <strong><?php echo esc_html($wpUser->display_name); ?></strong>
                                    <br><span class="description"><?php echo esc_html($wpUser->user_email); ?></span>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Gelombang', 'jalagistrasi'); ?></th>
                            <td><?php echo esc_html($gelombang ? $gelombang->nama . ' ' . $gelombang->tahun_akademik : '—'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Status', 'jalagistrasi'); ?></th>
                            <td>
                                <span style="display:inline-block;padding:3px 10px;border-radius:9999px;font-size:13px;font-weight:600;background:<?php echo esc_attr($statusColor[$pendaftaran->status] ?? '#6b7280'); ?>20;color:<?php echo esc_attr($statusColor[$pendaftaran->status] ?? '#6b7280'); ?>;">
                                    <?php echo esc_html($currentStatus ? $currentStatus->label() : $pendaftaran->status); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Dikirim pada', 'jalagistrasi'); ?></th>
                            <td><?php echo $pendaftaran->submitted_at ? esc_html(date_i18n('d M Y, H:i', strtotime($pendaftaran->submitted_at))) : '—'; ?></td>
                        </tr>
                        <?php if ($pendaftaran->catatan_panitia) : ?>
                        <tr>
                            <th><?php esc_html_e('Catatan Panitia', 'jalagistrasi'); ?></th>
                            <td><?php echo nl2br(esc_html($pendaftaran->catatan_panitia)); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Pilihan Program Studi -->
            <?php if (!empty($prodiPilihan)) : ?>
            <div class="postbox" style="margin-bottom:16px;">
                <div class="postbox-header"><h2><?php esc_html_e('Pilihan Program Studi', 'jalagistrasi'); ?></h2></div>
                <div class="inside" style="margin:0;padding:16px;">
                    <ol style="margin:0;padding-left:20px;">
                        <?php foreach ($prodiPilihan as $pp) : ?>
                            <li style="padding:4px 0;"><?php echo esc_html($pp->prodi_nama ?? '—'); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dokumen Berkas -->
            <?php if (!empty($tipeBerkasList) || !empty($berkasMap)) : ?>
            <div class="postbox" style="margin-bottom:16px;">
                <div class="postbox-header"><h2><?php esc_html_e('Dokumen Berkas', 'jalagistrasi'); ?></h2></div>
                <div class="inside" style="margin:0;padding:16px;">
                    <?php
                    // Kode tipe berkas yang sudah dikonfigurasi untuk gelombang ini.
                    $kodeDikonfigurasi = array_map(static fn ($t) => $t->kode, $tipeBerkasList);
                    // Berkas yang terupload tapi tipenya sudah tidak ada di konfigurasi (mis. tipe dihapus setelah upload).
                    $berkasYatimKode = array_diff(array_keys($berkasMap), $kodeDikonfigurasi);
                    ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));gap:14px;">
                        <?php foreach ($tipeBerkasList as $tipe) : ?>
                            <?php
                            $berkas  = $berkasMap[$tipe->kode] ?? null;
                            $isWajib = (bool) $tipe->is_required;
                            ?>
                            <?php if ($berkas) : ?>
                                <?php
                                $isImage    = in_array($berkas->mime_type, ['image/jpeg', 'image/png'], true);
                                $isPdf      = $berkas->mime_type === 'application/pdf';
                                $previewUrl = esc_url(add_query_arg([
                                    'action'    => 'jg_preview_berkas',
                                    'berkas_id' => $berkas->id,
                                    '_wpnonce'  => wp_create_nonce('jg_preview_berkas_' . $berkas->id),
                                ], admin_url('admin-ajax.php')));
                                $modalId = 'jg-admin-doc-' . (int) $berkas->id;
                                ?>
                                <?php
                                $verStatus  = $berkas->status ?: 'pending';
                                $verBadge   = [
                                    'pending'  => ['label' => 'Menunggu Verifikasi', 'bg' => '#f0f0f1', 'fg' => '#646970'],
                                    'diterima' => ['label' => '✓ Diterima',          'bg' => '#d1fae5', 'fg' => '#16a34a'],
                                    'ditolak'  => ['label' => '✕ Ditolak',           'bg' => '#fee2e2', 'fg' => '#dc2626'],
                                ][$verStatus] ?? ['label' => $verStatus, 'bg' => '#f0f0f1', 'fg' => '#646970'];
                                $rejectFormId = 'jg-reject-form-' . (int) $berkas->id;
                                ?>
                                <div style="border:1px solid #dcdcde;border-radius:6px;overflow:hidden;background:#f6f7f7;">
                                    <button type="button"
                                            onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').style.display='flex'"
                                            style="display:block;width:100%;text-align:left;padding:0;border:0;background:none;cursor:pointer;">
                                        <?php if ($isImage) : ?>
                                            <div style="position:relative;height:100px;background:#eee;">
                                                <img src="<?php echo $previewUrl; ?>" alt="<?php echo esc_attr($berkas->file_name_original); ?>"
                                                     style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" loading="lazy">
                                            </div>
                                        <?php else : ?>
                                            <div style="height:100px;display:flex;align-items:center;justify-content:center;background:#eee;font-size:28px;">📄</div>
                                        <?php endif; ?>
                                        <div style="padding:8px 10px 4px;">
                                            <p style="margin:0;font-size:12px;font-weight:600;color:#1d2327;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                <?php echo esc_html($tipe->label); ?>
                                            </p>
                                            <p style="margin:2px 0 0;font-size:11px;color:#646970;">
                                                <?php echo esc_html(number_format((int) $berkas->file_size / 1024, 0)); ?> KB
                                            </p>
                                        </div>
                                    </button>

                                    <div style="padding:6px 10px 10px;">
                                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:600;background:<?php echo $verBadge['bg']; ?>;color:<?php echo $verBadge['fg']; ?>;">
                                            <?php echo esc_html($verBadge['label']); ?>
                                        </span>
                                        <?php if ($verStatus === 'ditolak' && $berkas->catatan) : ?>
                                            <p style="margin:4px 0 0;font-size:11px;color:#dc2626;"><?php echo esc_html($berkas->catatan); ?></p>
                                        <?php endif; ?>

                                        <div style="display:flex;gap:4px;margin-top:6px;">
                                            <?php if ($verStatus !== 'diterima') : ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                                                    <?php wp_nonce_field('jg_verify_berkas'); ?>
                                                    <input type="hidden" name="action" value="jg_verify_berkas">
                                                    <input type="hidden" name="berkas_id" value="<?php echo esc_attr($berkas->id); ?>">
                                                    <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">
                                                    <input type="hidden" name="decision" value="diterima">
                                                    <button type="submit" class="button button-small" style="font-size:11px;height:auto;padding:2px 8px;">
                                                        <?php esc_html_e('Terima', 'jalagistrasi'); ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($verStatus !== 'ditolak') : ?>
                                                <button type="button" class="button button-small"
                                                        style="font-size:11px;height:auto;padding:2px 8px;color:#dc2626;"
                                                        onclick="document.getElementById('<?php echo esc_attr($rejectFormId); ?>').style.display='block'">
                                                    <?php esc_html_e('Tolak', 'jalagistrasi'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <div id="<?php echo esc_attr($rejectFormId); ?>" style="display:none;margin-top:6px;">
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                                                <?php wp_nonce_field('jg_verify_berkas'); ?>
                                                <input type="hidden" name="action" value="jg_verify_berkas">
                                                <input type="hidden" name="berkas_id" value="<?php echo esc_attr($berkas->id); ?>">
                                                <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">
                                                <input type="hidden" name="decision" value="ditolak">
                                                <textarea name="catatan" required rows="2" style="width:100%;font-size:11px;margin-bottom:4px;"
                                                          placeholder="<?php esc_attr_e('Alasan penolakan (wajib)…', 'jalagistrasi'); ?>"></textarea>
                                                <button type="submit" class="button button-small" style="font-size:11px;height:auto;padding:2px 8px;color:#dc2626;">
                                                    <?php esc_html_e('Kirim Penolakan', 'jalagistrasi'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal preview -->
                                <div id="<?php echo esc_attr($modalId); ?>"
                                     style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;padding:20px;background:rgba(17,24,39,.65);"
                                     onclick="if(event.target===this)this.style.display='none'">
                                    <div style="position:relative;background:#fff;border-radius:8px;overflow:hidden;width:100%;max-width:<?php echo $isPdf ? '700px' : '460px'; ?>;box-shadow:0 10px 40px rgba(0,0,0,.3);">
                                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #eee;">
                                            <strong style="font-size:13px;"><?php echo esc_html($tipe->label); ?></strong>
                                            <button type="button"
                                                    onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').style.display='none'"
                                                    style="border:0;background:#f0f0f1;border-radius:50%;width:26px;height:26px;cursor:pointer;">✕</button>
                                        </div>
                                        <?php if ($isImage) : ?>
                                            <img src="<?php echo $previewUrl; ?>" alt="<?php echo esc_attr($berkas->file_name_original); ?>"
                                                 style="width:100%;max-height:75vh;object-fit:contain;background:#f6f7f7;display:block;">
                                        <?php elseif ($isPdf) : ?>
                                            <iframe src="<?php echo $previewUrl; ?>" style="width:100%;height:75vh;border:0;"></iframe>
                                        <?php endif; ?>
                                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 16px;border-top:1px solid #eee;">
                                            <span class="description" style="font-size:11px;">
                                                <?php echo esc_html($berkas->file_name_original); ?> &middot;
                                                <?php echo esc_html(number_format((int) $berkas->file_size / 1024, 0)); ?> KB &middot;
                                                <?php echo esc_html(date_i18n('d M Y, H:i', strtotime($berkas->uploaded_at))); ?>
                                            </span>
                                            <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener" style="font-size:11px;">
                                                <?php esc_html_e('Buka tab baru', 'jalagistrasi'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div style="border:1px dashed #dcdcde;border-radius:6px;padding:10px;background:#fafafa;">
                                    <p style="margin:0;font-size:12px;font-weight:600;color:#646970;">
                                        <?php echo esc_html($tipe->label); ?>
                                    </p>
                                    <p style="margin:4px 0 0;font-size:11px;color:<?php echo $isWajib ? '#dc2626' : '#a7aaad'; ?>;">
                                        <?php echo $isWajib
                                            ? esc_html__('✕ Belum diupload (wajib)', 'jalagistrasi')
                                            : esc_html__('— Belum diupload', 'jalagistrasi'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($berkasYatimKode)) : ?>
                        <p class="description" style="display:block;margin-top:12px;">
                            <?php esc_html_e('Catatan: ada berkas terupload dengan tipe yang sudah tidak terdaftar di konfigurasi gelombang ini:', 'jalagistrasi'); ?>
                            <?php echo esc_html(implode(', ', $berkasYatimKode)); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Bukti Pembayaran -->
            <?php if ($pendaftaran->kode_unik_pembayaran !== null) : ?>
            <div class="postbox" style="margin-bottom:16px;">
                <div class="postbox-header"><h2><?php esc_html_e('Bukti Pembayaran', 'jalagistrasi'); ?></h2></div>
                <div class="inside" style="margin:0;padding:16px;">
                    <p style="margin:0 0 10px;font-size:13px;">
                        <?php esc_html_e('Kode unik pendaftaran:', 'jalagistrasi'); ?>
                        <strong style="font-size:15px;"><?php echo esc_html(sprintf('%03d', (int) $pendaftaran->kode_unik_pembayaran)); ?></strong>
                        <span class="description">
                            (<?php esc_html_e('total seharusnya:', 'jalagistrasi'); ?>
                            Rp <?php echo esc_html(number_format((float) $totalSeharusnya, 0, ',', '.')); ?>)
                        </span>
                    </p>

                    <?php if (!$pembayaran) : ?>
                        <p class="description"><?php esc_html_e('Pendaftar belum mengupload bukti pembayaran.', 'jalagistrasi'); ?></p>
                    <?php else : ?>
                        <?php
                        $isMatch = abs((float) $pembayaran->jumlah - (float) $totalSeharusnya) < 0.01;
                        $isImage = in_array($pembayaran->mime_type, ['image/jpeg', 'image/png'], true);
                        $isPdf   = $pembayaran->mime_type === 'application/pdf';
                        $previewUrl = esc_url(add_query_arg([
                            'action'      => 'jg_preview_pembayaran',
                            'pembayaran_id' => $pembayaran->id,
                            '_wpnonce'    => wp_create_nonce('jg_preview_pembayaran_' . $pembayaran->id),
                        ], admin_url('admin-ajax.php')));
                        $modalId = 'jg-admin-pembayaran-' . (int) $pembayaran->id;
                        ?>
                        <table class="form-table" style="margin:0 0 10px;">
                            <tr>
                                <th style="width:160px;"><?php esc_html_e('Nominal dilaporkan', 'jalagistrasi'); ?></th>
                                <td>
                                    Rp <?php echo esc_html(number_format((float) $pembayaran->jumlah, 0, ',', '.')); ?>
                                    <?php if ($isMatch) : ?>
                                        <span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:#d1fae5;color:#16a34a;">✓ <?php esc_html_e('Sesuai', 'jalagistrasi'); ?></span>
                                    <?php else : ?>
                                        <span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:#fee2e2;color:#dc2626;">⚠ <?php esc_html_e('Tidak sesuai, cek manual', 'jalagistrasi'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Rekening tujuan', 'jalagistrasi'); ?></th>
                                <td>
                                    <?php echo $rekeningBank
                                        ? esc_html($rekeningBank->nama_bank . ' — ' . $rekeningBank->nomor_rekening . ' (' . $rekeningBank->nama_pemilik . ')')
                                        : '—'; ?>
                                </td>
                            </tr>
                            <?php if ($pembayaran->nama_pengirim) : ?>
                            <tr>
                                <th><?php esc_html_e('Nama Pengirim', 'jalagistrasi'); ?></th>
                                <td><?php echo esc_html($pembayaran->nama_pengirim); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><?php esc_html_e('Diupload', 'jalagistrasi'); ?></th>
                                <td><?php echo esc_html(date_i18n('d M Y, H:i', strtotime($pembayaran->uploaded_at))); ?></td>
                            </tr>
                        </table>

                        <button type="button"
                                onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').style.display='flex'"
                                class="button">
                            <?php esc_html_e('Lihat Bukti Transfer', 'jalagistrasi'); ?>
                        </button>

                        <div id="<?php echo esc_attr($modalId); ?>"
                             style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;padding:20px;background:rgba(17,24,39,.65);"
                             onclick="if(event.target===this)this.style.display='none'">
                            <div style="position:relative;background:#fff;border-radius:8px;overflow:hidden;width:100%;max-width:<?php echo $isPdf ? '700px' : '460px'; ?>;box-shadow:0 10px 40px rgba(0,0,0,.3);">
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #eee;">
                                    <strong style="font-size:13px;"><?php esc_html_e('Bukti Transfer', 'jalagistrasi'); ?></strong>
                                    <button type="button"
                                            onclick="document.getElementById('<?php echo esc_attr($modalId); ?>').style.display='none'"
                                            style="border:0;background:#f0f0f1;border-radius:50%;width:26px;height:26px;cursor:pointer;">✕</button>
                                </div>
                                <?php if ($isImage) : ?>
                                    <img src="<?php echo $previewUrl; ?>" alt="<?php echo esc_attr($pembayaran->file_name_original); ?>"
                                         style="width:100%;max-height:75vh;object-fit:contain;background:#f6f7f7;display:block;">
                                <?php elseif ($isPdf) : ?>
                                    <iframe src="<?php echo $previewUrl; ?>" style="width:100%;height:75vh;border:0;"></iframe>
                                <?php endif; ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 16px;border-top:1px solid #eee;">
                                    <span class="description" style="font-size:11px;">
                                        <?php echo esc_html($pembayaran->file_name_original); ?> &middot;
                                        <?php echo esc_html(number_format((int) $pembayaran->file_size / 1024, 0)); ?> KB
                                    </span>
                                    <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener" style="font-size:11px;">
                                        <?php esc_html_e('Buka tab baru', 'jalagistrasi'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Data Formulir -->
            <?php foreach ($sections as $seksi => $fields) : ?>
            <div class="postbox" style="margin-bottom:16px;">
                <div class="postbox-header"><h2><?php echo esc_html($seksi); ?></h2></div>
                <div class="inside" style="margin:0;padding:16px;">
                    <table class="form-table" style="margin:0;">
                        <?php foreach ($fields as $field) : ?>
                            <?php
                            $jawaban    = $jawabanMap[(int) $field->id] ?? null;
                            $tipe       = $field->tipe;
                            $namaField  = $field->nama_field;
                            $nilaiText  = $jawaban?->nilai_text ?? '';
                            $nilaiJson  = $jawaban?->nilai_json ? json_decode($jawaban->nilai_json, true) : null;
                            ?>
                            <tr>
                                <th style="width:200px;"><?php echo esc_html($field->label); ?></th>
                                <td>
                                    <?php if ($tipe === 'file_upload') : ?>
                                        <?php
                                        $berkas = $berkasMap[$namaField] ?? null;
                                        if ($berkas) :
                                            $previewUrl = esc_url(add_query_arg([
                                                'action'    => 'jg_preview_berkas',
                                                'berkas_id' => $berkas->id,
                                                '_wpnonce'  => wp_create_nonce('jg_preview_berkas_' . $berkas->id),
                                            ], admin_url('admin-ajax.php')));
                                            $isImage = in_array($berkas->mime_type, ['image/jpeg', 'image/png'], true);
                                        ?>
                                            <?php if ($isImage) : ?>
                                                <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener">
                                                    <img src="<?php echo $previewUrl; ?>"
                                                         alt="<?php echo esc_attr($berkas->file_name_original); ?>"
                                                         style="max-height:120px;max-width:200px;border-radius:6px;border:1px solid #e5e7eb;object-fit:contain;display:block;">
                                                </a>
                                                <span class="description" style="margin-top:4px;display:block;">
                                                    <?php echo esc_html($berkas->file_name_original); ?> &middot;
                                                    <?php echo esc_html(number_format((int) $berkas->file_size / 1024, 0)); ?> KB
                                                </span>
                                            <?php else : ?>
                                                <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener" class="button button-small">
                                                    📄 <?php echo esc_html($berkas->file_name_original); ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="description">—</span>
                                        <?php endif; ?>
                                    <?php elseif ($tipe === 'checkbox' && is_array($nilaiJson)) : ?>
                                        <?php echo esc_html(implode(', ', $nilaiJson)) ?: '—'; ?>
                                    <?php elseif ($tipe === 'wilayah_autocomplete') : ?>
                                        <?php
                                        $wilayahNama = $nilaiText !== ''
                                            ? ((new \Webane\Jalagistrasi\Repository\WilayahRepository())->findByKode($nilaiText)?->nama_lengkap ?? $nilaiText)
                                            : '';
                                        ?>
                                        <?php echo $wilayahNama !== '' ? esc_html($wilayahNama) : '<span class="description">—</span>'; ?>
                                    <?php else : ?>
                                        <?php echo $nilaiText !== '' ? esc_html($nilaiText) : '<span class="description">—</span>'; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Kolom kanan: Update status -->
        <div>
            <?php if ($siapDiverifikasi) : ?>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;margin-bottom:12px;">
                    <p style="margin:0;font-size:13px;color:#92400e;">
                        ⚠ <strong><?php esc_html_e('Semua dokumen wajib sudah diterima.', 'jalagistrasi'); ?></strong><br>
                        <?php esc_html_e('Jangan lupa update status ke "Berkas Diverifikasi" di bawah supaya pendaftar bisa lanjut ke pembayaran.', 'jalagistrasi'); ?>
                    </p>
                </div>
            <?php endif; ?>
            <div class="postbox" style="position:sticky;top:32px;">
                <div class="postbox-header"><h2><?php esc_html_e('Update Status', 'jalagistrasi'); ?></h2></div>
                <div class="inside" style="margin:0;padding:16px;">
                    <?php if ($currentStatus === StatusPendaftaran::PembayaranDiupload && $pembayaran) : ?>
                        <!-- Panel verifikasi pembayaran khusus — bukan dropdown generik, supaya
                             admin tidak asal pilih status tanpa benar-benar cek mutasi rekening.
                             Lihat docs/arsitektur-pembayaran.md. -->
                        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:10px 12px;margin-bottom:14px;">
                            <p style="margin:0;font-size:12px;color:#9a3412;">
                                ⚠ <?php esc_html_e('Cek dulu mutasi rekening sebelum menerima — pastikan dana benar-benar masuk dengan nominal yang sesuai (lihat kode unik di atas).', 'jalagistrasi'); ?>
                            </p>
                        </div>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px;">
                            <?php wp_nonce_field('jg_update_status_pendaftaran'); ?>
                            <input type="hidden" name="action" value="jg_update_status_pendaftaran">
                            <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">
                            <input type="hidden" name="new_status" value="<?php echo esc_attr(StatusPendaftaran::DijadwalkanTes->value); ?>">

                            <label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;font-size:12px;cursor:pointer;">
                                <input type="checkbox" id="jg-konfirmasi-dana" required style="margin-top:2px;">
                                <span>
                                    <?php
                                    printf(
                                        /* translators: %s: nominal yang seharusnya */
                                        esc_html__('Saya sudah memeriksa mutasi rekening dan dana sebesar Rp %s benar-benar sudah masuk.', 'jalagistrasi'),
                                        '<strong>' . esc_html(number_format((float) $totalSeharusnya, 0, ',', '.')) . '</strong>'
                                    );
                                    ?>
                                </span>
                            </label>

                            <button type="submit" id="jg-btn-terima-dana" class="button button-primary widefat" disabled>
                                <?php esc_html_e('✓ Dana Diterima — Lanjutkan ke Tes', 'jalagistrasi'); ?>
                            </button>
                        </form>
                        <script>
                        document.getElementById('jg-konfirmasi-dana').addEventListener('change', function () {
                            document.getElementById('jg-btn-terima-dana').disabled = !this.checked;
                        });
                        </script>

                        <button type="button" class="button widefat" style="color:#dc2626;"
                                onclick="document.getElementById('jg-tolak-pembayaran-form').style.display='block';this.style.display='none';">
                            <?php esc_html_e('✕ Tolak Pembayaran', 'jalagistrasi'); ?>
                        </button>
                        <div id="jg-tolak-pembayaran-form" style="display:none;margin-top:10px;">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('jg_update_status_pendaftaran'); ?>
                                <input type="hidden" name="action" value="jg_update_status_pendaftaran">
                                <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">
                                <input type="hidden" name="new_status" value="<?php echo esc_attr(StatusPendaftaran::PembayaranDitolak->value); ?>">
                                <textarea name="catatan_panitia" rows="3" class="widefat" required style="margin-bottom:8px;"
                                          placeholder="<?php esc_attr_e('Alasan ditolak (wajib) — mis. dana belum masuk, nominal tidak sesuai, bukti tidak jelas…', 'jalagistrasi'); ?>"></textarea>
                                <button type="submit" class="button widefat" style="color:#dc2626;border-color:#fca5a5;">
                                    <?php esc_html_e('Kirim Penolakan', 'jalagistrasi'); ?>
                                </button>
                            </form>
                        </div>

                    <?php elseif (empty($nextTransitions)) : ?>
                        <p class="description">
                            <?php esc_html_e('Status ini sudah final — tidak ada transisi yang tersedia.', 'jalagistrasi'); ?>
                        </p>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('jg_update_status_pendaftaran'); ?>
                            <input type="hidden" name="action" value="jg_update_status_pendaftaran">
                            <input type="hidden" name="pendaftaran_id" value="<?php echo esc_attr($pendaftaran->id); ?>">

                            <div style="margin-bottom:12px;">
                                <label for="new_status" style="display:block;font-weight:600;margin-bottom:6px;">
                                    <?php esc_html_e('Status Baru', 'jalagistrasi'); ?>
                                </label>
                                <select name="new_status" id="new_status" class="widefat" required>
                                    <option value="">— <?php esc_html_e('Pilih status', 'jalagistrasi'); ?> —</option>
                                    <?php foreach ($nextTransitions as $next) : ?>
                                        <option value="<?php echo esc_attr($next->value); ?>">
                                            <?php echo esc_html($next->label()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-bottom:12px;">
                                <label for="catatan_panitia" style="display:block;font-weight:600;margin-bottom:6px;">
                                    <?php esc_html_e('Catatan Panitia', 'jalagistrasi'); ?>
                                    <span style="font-weight:normal;color:#6b7280;">(<?php esc_html_e('opsional', 'jalagistrasi'); ?>)</span>
                                </label>
                                <textarea name="catatan_panitia" id="catatan_panitia" rows="4" class="widefat"
                                          placeholder="<?php esc_attr_e('Catatan untuk pendaftar…', 'jalagistrasi'); ?>"><?php echo esc_textarea($pendaftaran->catatan_panitia ?? ''); ?></textarea>
                            </div>

                            <input type="submit" class="button button-primary widefat"
                                   value="<?php esc_attr_e('Simpan Status', 'jalagistrasi'); ?>">
                        </form>
                    <?php endif; ?>

                    <!-- Status saat ini -->
                    <hr style="margin:16px 0;">
                    <p style="margin:0;font-size:12px;color:#6b7280;">
                        <?php esc_html_e('Status saat ini:', 'jalagistrasi'); ?><br>
                        <strong><?php echo esc_html($currentStatus ? $currentStatus->label() : $pendaftaran->status); ?></strong>
                    </p>
                </div>
            </div>

            <!-- Riwayat Status (audit trail) -->
            <?php if (!empty($statusHistory)) : ?>
            <div class="postbox" style="margin-top:16px;">
                <div class="postbox-header"><h2><?php esc_html_e('Riwayat Status', 'jalagistrasi'); ?></h2></div>
                <div class="inside" style="margin:0;padding:16px;">
                    <ul style="margin:0;padding:0;list-style:none;">
                        <?php foreach (array_reverse($statusHistory) as $h) : ?>
                            <?php
                            $aktor = $h->actor_user_id > 0 ? get_userdata($h->actor_user_id) : false;
                            $statusBaruLabel = StatusPendaftaran::tryFrom($h->status_baru)?->label() ?? $h->status_baru;
                            $statusLamaLabel = $h->status_lama !== '' ? (StatusPendaftaran::tryFrom($h->status_lama)?->label() ?? $h->status_lama) : null;
                            ?>
                            <li style="padding:8px 0;border-bottom:1px solid #f0f0f1;font-size:12px;">
                                <strong><?php echo esc_html($statusBaruLabel); ?></strong>
                                <?php if ($statusLamaLabel) : ?>
                                    <span class="description">(<?php echo esc_html($statusLamaLabel); ?> →)</span>
                                <?php endif; ?>
                                <br>
                                <span class="description">
                                    <?php echo esc_html(date_i18n('d M Y, H:i', strtotime($h->created_at))); ?>
                                    &middot;
                                    <?php echo esc_html($aktor ? $aktor->display_name : __('Sistem', 'jalagistrasi')); ?>
                                </span>
                                <?php if ($h->catatan) : ?>
                                    <br><span style="color:#9a3412;"><?php echo esc_html($h->catatan); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
