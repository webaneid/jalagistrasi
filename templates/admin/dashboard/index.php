<?php
/**
 * Dashboard admin — statistik pendaftaran. Lihat docs/arsitektur-dashboard-admin.md
 * dan docs/arsitektur-tahun-ajaran.md.
 *
 * @var list<object>                              $tahunAjaranList
 * @var int                                       $tahunAjaranId
 * @var list<object>                              $gelombangList
 * @var int                                       $gelombangId
 * @var int                                       $total
 * @var int                                       $menungguDokumen
 * @var int                                       $menungguBayar
 * @var int                                       $lulusSeleksi
 * @var array<string,int>                         $statusGrouped
 * @var list<\Webane\Jalagistrasi\Enum\StatusPendaftaran> $semuaStatus
 * @var list<object{prodi_nama:string,jumlah:int}> $prodiTerpopuler
 * @var list<array{nama:string,jumlah:int}>        $breakdownGelombang
 */
defined('ABSPATH') || exit;

$pendaftarListUrl = admin_url('admin.php?page=jg-pendaftar');
$dashboardUrl     = admin_url('admin.php?page=jg-dashboard');

$kartu = [
    ['label' => __('Total Pendaftar', 'jalagistrasi'), 'jumlah' => $total, 'color' => '#1d2327', 'link' => null],
    ['label' => __('Menunggu Verifikasi Dokumen', 'jalagistrasi'), 'jumlah' => $menungguDokumen, 'color' => '#0891b2', 'link' => add_query_arg(['status' => 'berkas_diupload', 'gelombang_id' => $gelombangId], $pendaftarListUrl)],
    ['label' => __('Menunggu Verifikasi Pembayaran', 'jalagistrasi'), 'jumlah' => $menungguBayar, 'color' => '#4f46e5', 'link' => add_query_arg(['status' => 'pembayaran_diupload', 'gelombang_id' => $gelombangId], $pendaftarListUrl)],
    ['label' => __('Lulus Seleksi', 'jalagistrasi'), 'jumlah' => $lulusSeleksi, 'color' => '#16a34a', 'link' => null],
];
?>
<div class="wrap">
    <h1><?php esc_html_e('Dashboard Jalagistrasi PMB', 'jalagistrasi'); ?></h1>

    <!-- Filter -->
    <form method="get" style="margin:16px 0;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="page" value="jg-dashboard">
        <div>
            <label for="sel-tahun-ajaran" style="font-weight:600;margin-right:8px;"><?php esc_html_e('Tahun Ajaran:', 'jalagistrasi'); ?></label>
            <select id="sel-tahun-ajaran" name="tahun_ajaran_id" onchange="this.form.submit()" style="max-width:220px;">
                <option value="0" <?php selected($tahunAjaranId, 0); ?>>— <?php esc_html_e('Semua', 'jalagistrasi'); ?> —</option>
                <?php foreach ($tahunAjaranList as $ta) : ?>
                    <option value="<?php echo esc_attr($ta->id); ?>" <?php selected((int) $ta->id, $tahunAjaranId); ?>>
                        <?php echo esc_html($ta->nama); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="sel-gelombang" style="font-weight:600;margin-right:8px;"><?php esc_html_e('Gelombang:', 'jalagistrasi'); ?></label>
            <select id="sel-gelombang" name="gelombang_id" onchange="this.form.submit()" style="max-width:280px;">
                <option value="0" <?php selected($gelombangId, 0); ?>>— <?php esc_html_e('Semua Gelombang', 'jalagistrasi'); ?> —</option>
                <?php foreach ($gelombangList as $g) : ?>
                    <option value="<?php echo esc_attr($g->id); ?>" <?php selected((int) $g->id, $gelombangId); ?>>
                        <?php echo esc_html($g->nama . ' — ' . $g->tahun_akademik); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($tahunAjaranId > 0 || $gelombangId > 0) : ?>
            <a href="<?php echo esc_url($dashboardUrl); ?>" class="button"><?php esc_html_e('Reset Filter', 'jalagistrasi'); ?></a>
        <?php endif; ?>
    </form>

    <!-- Kartu ringkasan -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px;">
        <?php foreach ($kartu as $k) : ?>
            <div class="postbox" style="margin:0;padding:18px;">
                <p style="margin:0 0 6px;font-size:13px;color:#646970;"><?php echo esc_html($k['label']); ?></p>
                <p style="margin:0;font-size:28px;font-weight:700;color:<?php echo esc_attr($k['color']); ?>;">
                    <?php echo (int) $k['jumlah']; ?>
                </p>
                <?php if ($k['link']) : ?>
                    <a href="<?php echo esc_url($k['link']); ?>" style="font-size:12px;"><?php esc_html_e('Lihat daftar →', 'jalagistrasi'); ?></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Breakdown per gelombang (muncul kalau Tahun Ajaran dipilih & Gelombang = semua) -->
    <?php if (!empty($breakdownGelombang)) : ?>
        <div class="postbox" style="margin:0 0 20px;">
            <div class="postbox-header"><h2><?php esc_html_e('Pendaftar per Gelombang dalam Tahun Ajaran Ini', 'jalagistrasi'); ?></h2></div>
            <div class="inside" style="margin:0;padding:0;">
                <table class="wp-list-table widefat" style="border:0;">
                    <tbody>
                        <?php foreach ($breakdownGelombang as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['nama']); ?></td>
                                <td style="text-align:right;width:80px;"><strong><?php echo (int) $row['jumlah']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

        <!-- Breakdown status -->
        <div class="postbox" style="margin:0;">
            <div class="postbox-header"><h2><?php esc_html_e('Breakdown Status', 'jalagistrasi'); ?></h2></div>
            <div class="inside" style="margin:0;padding:0;">
                <table class="wp-list-table widefat" style="border:0;">
                    <tbody>
                        <?php foreach ($semuaStatus as $status) : ?>
                            <?php $jumlah = $statusGrouped[$status->value] ?? 0; ?>
                            <tr>
                                <td><?php echo esc_html($status->label()); ?></td>
                                <td style="text-align:right;width:60px;"><strong><?php echo (int) $jumlah; ?></strong></td>
                                <td style="text-align:right;width:110px;">
                                    <?php if ($jumlah > 0) : ?>
                                        <a href="<?php echo esc_url(add_query_arg(['status' => $status->value, 'gelombang_id' => $gelombangId], $pendaftarListUrl)); ?>" class="button button-small">
                                            <?php esc_html_e('Lihat', 'jalagistrasi'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Prodi terpopuler -->
        <div class="postbox" style="margin:0;">
            <div class="postbox-header"><h2><?php esc_html_e('Prodi Terpopuler (Pilihan ke-1)', 'jalagistrasi'); ?></h2></div>
            <div class="inside" style="margin:0;padding:16px;">
                <?php if (empty($prodiTerpopuler)) : ?>
                    <p class="description"><?php esc_html_e('Belum ada data.', 'jalagistrasi'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat" style="border:0;">
                        <tbody>
                            <?php foreach ($prodiTerpopuler as $p) : ?>
                                <tr>
                                    <td><?php echo esc_html($p->prodi_nama); ?></td>
                                    <td style="text-align:right;width:60px;"><strong><?php echo (int) $p->jumlah; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div style="display:flex;gap:16px;margin-top:20px;">
        <div class="postbox" style="min-width:200px;padding:16px;margin:0;">
            <h3 style="margin-top:0;"><?php esc_html_e('Tahun Ajaran', 'jalagistrasi'); ?></h3>
            <a href="<?php echo esc_url(admin_url('admin.php?page=jg-tahun-ajaran')); ?>" class="button button-primary">
                <?php esc_html_e('Kelola Tahun Ajaran', 'jalagistrasi'); ?>
            </a>
        </div>
        <div class="postbox" style="min-width:200px;padding:16px;margin:0;">
            <h3 style="margin-top:0;"><?php esc_html_e('Gelombang', 'jalagistrasi'); ?></h3>
            <a href="<?php echo esc_url(admin_url('admin.php?page=jg-gelombang')); ?>" class="button button-primary">
                <?php esc_html_e('Kelola Gelombang', 'jalagistrasi'); ?>
            </a>
        </div>
        <div class="postbox" style="min-width:200px;padding:16px;margin:0;">
            <h3 style="margin-top:0;"><?php esc_html_e('Program Studi', 'jalagistrasi'); ?></h3>
            <a href="<?php echo esc_url(admin_url('admin.php?page=jg-program-studi')); ?>" class="button button-primary">
                <?php esc_html_e('Kelola Program Studi', 'jalagistrasi'); ?>
            </a>
        </div>
    </div>
</div>
