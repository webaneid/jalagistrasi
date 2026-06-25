<?php
/**
 * Admin — daftar pendaftar.
 *
 * @var list<object>  $rows
 * @var int           $total
 * @var int           $perPage
 * @var int           $page
 * @var int           $gelombangId
 * @var string        $status
 * @var string        $search
 * @var list<object>  $gelombangList
 * @var string        $updated
 */
defined('ABSPATH') || exit;

use Webane\Jalagistrasi\Enum\StatusPendaftaran;

$pageUrl   = admin_url('admin.php?page=jg-pendaftar');
$totalPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

$statusBadge = [
    'submitted'             => ['Formulir Disubmit',       'bg-blue-100 text-blue-800'],
    'berkas_diupload'       => ['Berkas Diupload',         'bg-cyan-100 text-cyan-800'],
    'pembayaran_diupload'   => ['Bukti Bayar Diupload',    'bg-indigo-100 text-indigo-800'],
    'berkas_diverifikasi'   => ['Berkas Diverifikasi',     'bg-teal-100 text-teal-800'],
    'berkas_ditolak'        => ['Berkas Ditolak',          'bg-orange-100 text-orange-800'],
    'dijadwalkan_tes'       => ['Dijadwalkan Tes',         'bg-purple-100 text-purple-800'],
    'diumumkan_lulus'       => ['Lulus Seleksi',           'bg-green-100 text-green-800'],
    'diumumkan_tidak_lulus' => ['Tidak Lulus Seleksi',     'bg-red-100 text-red-800'],
    'daftar_ulang'          => ['Proses Daftar Ulang',     'bg-teal-100 text-teal-800'],
    'selesai'               => ['Selesai',                 'bg-green-100 text-green-800'],
    'gagal_daftar_ulang'    => ['Gagal Daftar Ulang',      'bg-red-100 text-red-800'],
    'draft'                 => ['Draft',                   'bg-gray-100 text-gray-600'],
];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Data Pendaftar', 'jalagistrasi'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($updated === '1') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Status pendaftaran berhasil diperbarui.', 'jalagistrasi'); ?></p></div>
    <?php endif; ?>

    <!-- Filter -->
    <form method="get" action="<?php echo esc_url($pageUrl); ?>" class="wp-clearfix" style="margin:16px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="page" value="jg-pendaftar">

        <div>
            <label class="screen-reader-text" for="filter-gelombang"><?php esc_html_e('Gelombang', 'jalagistrasi'); ?></label>
            <select id="filter-gelombang" name="gelombang_id" class="postform">
                <option value="0"><?php esc_html_e('Semua Gelombang', 'jalagistrasi'); ?></option>
                <?php foreach ($gelombangList as $g) : ?>
                    <option value="<?php echo esc_attr($g->id); ?>" <?php selected((int) $g->id, $gelombangId); ?>>
                        <?php echo esc_html($g->nama . ' ' . $g->tahun_akademik); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="screen-reader-text" for="filter-status"><?php esc_html_e('Status', 'jalagistrasi'); ?></label>
            <select id="filter-status" name="status" class="postform">
                <option value=""><?php esc_html_e('Semua Status', 'jalagistrasi'); ?></option>
                <?php foreach (StatusPendaftaran::cases() as $s) : ?>
                    <?php if ($s === StatusPendaftaran::Draft) continue; ?>
                    <option value="<?php echo esc_attr($s->value); ?>" <?php selected($s->value, $status); ?>>
                        <?php echo esc_html($s->label()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="screen-reader-text" for="filter-search"><?php esc_html_e('Cari', 'jalagistrasi'); ?></label>
            <input type="search" id="filter-search" name="s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php esc_attr_e('Cari nama atau nomor…', 'jalagistrasi'); ?>"
                   class="regular-text">
        </div>

        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'jalagistrasi'); ?>">
        <?php if ($gelombangId || $status || $search) : ?>
            <a href="<?php echo esc_url($pageUrl); ?>" class="button"><?php esc_html_e('Reset', 'jalagistrasi'); ?></a>
        <?php endif; ?>

        <?php
        $exportUrl = wp_nonce_url(
            add_query_arg([
                'action'       => 'jg_export_pendaftar',
                'gelombang_id' => $gelombangId,
                'status'       => $status,
                's'            => $search,
            ], admin_url('admin-post.php')),
            'jg_export_pendaftar'
        );
        ?>
        <a href="<?php echo esc_url($exportUrl); ?>" class="button button-primary" style="margin-left:auto;">
            ⬇ <?php esc_html_e('Export Excel', 'jalagistrasi'); ?>
        </a>
    </form>

    <p class="description" style="margin-bottom:12px;">
        <?php
        printf(
            /* translators: %d: jumlah pendaftar */
            esc_html(_n('%d pendaftar ditemukan.', '%d pendaftar ditemukan.', $total, 'jalagistrasi')),
            $total
        );
        ?>
    </p>

    <?php if (empty($rows)) : ?>
        <div class="notice notice-info"><p><?php esc_html_e('Belum ada data pendaftar.', 'jalagistrasi'); ?></p></div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:160px;"><?php esc_html_e('Nomor Pendaftaran', 'jalagistrasi'); ?></th>
                <th><?php esc_html_e('Nama Pendaftar', 'jalagistrasi'); ?></th>
                <th><?php esc_html_e('Gelombang', 'jalagistrasi'); ?></th>
                <th style="width:180px;"><?php esc_html_e('Status', 'jalagistrasi'); ?></th>
                <th style="width:140px;"><?php esc_html_e('Tanggal Submit', 'jalagistrasi'); ?></th>
                <th style="width:80px;"><?php esc_html_e('Aksi', 'jalagistrasi'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) : ?>
                <?php
                [$badgeLabel, $badgeClass] = $statusBadge[$row->status] ?? [ucfirst($row->status), 'bg-gray-100 text-gray-700'];
                $detailUrl = add_query_arg(['page' => 'jg-pendaftar', 'id' => $row->id], admin_url('admin.php'));
                ?>
                <tr>
                    <td><code><?php echo esc_html($row->nomor_pendaftaran); ?></code></td>
                    <td>
                        <strong><a href="<?php echo esc_url($detailUrl); ?>"><?php echo esc_html($row->nama_pendaftar); ?></a></strong>
                        <div class="row-actions">
                            <span><a href="<?php echo esc_url($detailUrl); ?>"><?php esc_html_e('Lihat Detail', 'jalagistrasi'); ?></a></span>
                        </div>
                    </td>
                    <td><?php echo esc_html($row->gelombang_nama . ' ' . $row->tahun_akademik); ?></td>
                    <td>
                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:500;"
                              class="<?php echo esc_attr($badgeClass); ?>">
                            <?php echo esc_html($badgeLabel); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $row->submitted_at
                            ? esc_html(date_i18n('d M Y, H:i', strtotime($row->submitted_at)))
                            : '—'; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($detailUrl); ?>" class="button button-small">
                            <?php esc_html_e('Detail', 'jalagistrasi'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginasi -->
    <?php if ($totalPage > 1) : ?>
        <div class="tablenav bottom" style="margin-top:12px;">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(esc_html__('%d item', 'jalagistrasi'), $total); ?>
                </span>
                <span class="pagination-links">
                    <?php for ($i = 1; $i <= $totalPage; $i++) : ?>
                        <?php $pUrl = add_query_arg(['page' => 'jg-pendaftar', 'paged' => $i, 'gelombang_id' => $gelombangId, 'status' => $status, 's' => $search], admin_url('admin.php')); ?>
                        <?php if ($i === $page) : ?>
                            <span class="page-numbers current"><?php echo $i; ?></span>
                        <?php else : ?>
                            <a class="page-numbers" href="<?php echo esc_url($pUrl); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
