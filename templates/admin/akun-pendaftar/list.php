<?php
/**
 * Admin — daftar SEMUA akun ber-role 'pendaftar' (menu "Role Pendaftar").
 * Lihat docs/arsitektur-overview.md.
 *
 * @var list<object> $rows
 * @var int           $total
 * @var int           $perPage
 * @var int           $page
 * @var string        $statusFilter
 * @var string        $search
 */
defined('ABSPATH') || exit;

$pageUrl   = admin_url('admin.php?page=jg-akun-pendaftar');
$totalPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Role Pendaftar', 'jalagistrasi'); ?></h1>
    <hr class="wp-header-end">

    <p class="description" style="margin:8px 0 16px;max-width:680px;">
        <?php esc_html_e('Semua akun yang pernah dibuat dengan role "pendaftar" — termasuk yang cuma bikin akun tanpa pernah submit formulir. Beda dari halaman "Pendaftar" yang cuma menampilkan yang sudah submit ke gelombang tertentu.', 'jalagistrasi'); ?>
    </p>

    <!-- Filter -->
    <form method="get" action="<?php echo esc_url($pageUrl); ?>" class="wp-clearfix" style="margin:16px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="page" value="jg-akun-pendaftar">

        <div>
            <label class="screen-reader-text" for="filter-status"><?php esc_html_e('Status', 'jalagistrasi'); ?></label>
            <select id="filter-status" name="status" class="postform">
                <option value=""><?php esc_html_e('Semua Status', 'jalagistrasi'); ?></option>
                <option value="sudah_mendaftar" <?php selected($statusFilter, 'sudah_mendaftar'); ?>><?php esc_html_e('Sudah Mendaftar', 'jalagistrasi'); ?></option>
                <option value="baru_akun" <?php selected($statusFilter, 'baru_akun'); ?>><?php esc_html_e('Baru Bikin Akun', 'jalagistrasi'); ?></option>
            </select>
        </div>

        <div>
            <label class="screen-reader-text" for="filter-search"><?php esc_html_e('Cari', 'jalagistrasi'); ?></label>
            <input type="search" id="filter-search" name="s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php esc_attr_e('Cari nama atau email…', 'jalagistrasi'); ?>"
                   class="regular-text">
        </div>

        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'jalagistrasi'); ?>">
        <?php if ($statusFilter || $search) : ?>
            <a href="<?php echo esc_url($pageUrl); ?>" class="button"><?php esc_html_e('Reset', 'jalagistrasi'); ?></a>
        <?php endif; ?>

        <?php
        $exportUrl = wp_nonce_url(
            add_query_arg([
                'action' => 'jg_export_akun_pendaftar',
                'status' => $statusFilter,
                's'      => $search,
            ], admin_url('admin-post.php')),
            'jg_export_akun_pendaftar'
        );
        ?>
        <a href="<?php echo esc_url($exportUrl); ?>" class="button button-primary" style="margin-left:auto;">
            ⬇ <?php esc_html_e('Export Excel', 'jalagistrasi'); ?>
        </a>
    </form>

    <p class="description" style="margin-bottom:12px;">
        <?php
        printf(
            /* translators: %d: jumlah akun */
            esc_html(_n('%d akun ditemukan.', '%d akun ditemukan.', $total, 'jalagistrasi')),
            $total
        );
        ?>
    </p>

    <?php if (empty($rows)) : ?>
        <div class="notice notice-info"><p><?php esc_html_e('Belum ada akun pendaftar.', 'jalagistrasi'); ?></p></div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Nama', 'jalagistrasi'); ?></th>
                <th><?php esc_html_e('Email', 'jalagistrasi'); ?></th>
                <th style="width:140px;"><?php esc_html_e('No. WhatsApp', 'jalagistrasi'); ?></th>
                <th style="width:150px;"><?php esc_html_e('Tanggal Daftar', 'jalagistrasi'); ?></th>
                <th style="width:160px;"><?php esc_html_e('Status Keterlibatan', 'jalagistrasi'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><strong><?php echo esc_html($row->display_name); ?></strong></td>
                    <td><?php echo esc_html($row->user_email); ?></td>
                    <td><?php echo esc_html($row->nomor_wa ?: '—'); ?></td>
                    <td><?php echo esc_html(date_i18n('d M Y, H:i', strtotime($row->user_registered))); ?></td>
                    <td>
                        <?php if ($row->sudah_mendaftar) : ?>
                            <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:500;background:#dcfce7;color:#15803d;">
                                <?php esc_html_e('Sudah Mendaftar', 'jalagistrasi'); ?>
                            </span>
                        <?php else : ?>
                            <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:12px;font-weight:500;background:#f3f4f6;color:#6b7280;">
                                <?php esc_html_e('Baru Bikin Akun', 'jalagistrasi'); ?>
                            </span>
                        <?php endif; ?>
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
                        <?php $pUrl = add_query_arg(['page' => 'jg-akun-pendaftar', 'paged' => $i, 'status' => $statusFilter, 's' => $search], admin_url('admin.php')); ?>
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
