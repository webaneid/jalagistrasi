<?php
/**
 * Template list gelombang pendaftaran.
 *
 * @var list<object> $gelombangList
 * @var string       $message
 * @var string       $statusFilter
 */

defined('ABSPATH') || exit;

$messages = [
    'created'       => __('Gelombang berhasil ditambahkan.', 'jalagistrasi'),
    'updated'       => __('Gelombang berhasil diperbarui.', 'jalagistrasi'),
    'deleted'       => __('Gelombang berhasil dihapus.', 'jalagistrasi'),
    'delete_blocked'=> __('Gelombang tidak dapat dihapus karena sudah ada data pendaftaran.', 'jalagistrasi'),
    'error'         => __('Terjadi kesalahan. Silakan coba lagi.', 'jalagistrasi'),
];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Gelombang Pendaftaran', 'jalagistrasi'); ?></h1>
    <a href="<?php echo esc_url(add_query_arg('action', 'add', menu_page_url('jg-gelombang', false))); ?>"
       class="page-title-action">
        <?php esc_html_e('Tambah Gelombang', 'jalagistrasi'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ($message !== '' && isset($messages[$message])) : ?>
        <?php $isError = in_array($message, ['delete_blocked', 'error'], true); ?>
        <div class="notice notice-<?php echo $isError ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url(menu_page_url('jg-gelombang', false)); ?>"
               <?php echo $statusFilter === '' ? 'class="current"' : ''; ?>>
                <?php esc_html_e('Semua', 'jalagistrasi'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('status_filter', 'aktif', menu_page_url('jg-gelombang', false))); ?>"
               <?php echo $statusFilter === 'aktif' ? 'class="current"' : ''; ?>>
                <?php esc_html_e('Aktif', 'jalagistrasi'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('status_filter', 'nonaktif', menu_page_url('jg-gelombang', false))); ?>"
               <?php echo $statusFilter === 'nonaktif' ? 'class="current"' : ''; ?>>
                <?php esc_html_e('Nonaktif', 'jalagistrasi'); ?>
            </a>
        </li>
    </ul>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" style="width:30%"><?php esc_html_e('Nama Gelombang', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:12%"><?php esc_html_e('Tahun Akademik', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:16%"><?php esc_html_e('Tgl Buka', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:16%"><?php esc_html_e('Tgl Tutup', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:8%;text-align:center"><?php esc_html_e('Max Prodi', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:8%;text-align:center"><?php esc_html_e('Status', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:10%"><?php esc_html_e('Aksi', 'jalagistrasi'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($gelombangList)) : ?>
                <tr>
                    <td colspan="7">
                        <?php esc_html_e('Belum ada gelombang. Klik "Tambah Gelombang" untuk mulai.', 'jalagistrasi'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($gelombangList as $g) : ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $g->id], menu_page_url('jg-gelombang', false))); ?>">
                                    <?php echo esc_html($g->nama); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html($g->tahun_akademik); ?></td>
                        <td><?php echo esc_html(date_i18n('d M Y H:i', strtotime($g->tanggal_buka))); ?></td>
                        <td><?php echo esc_html(date_i18n('d M Y H:i', strtotime($g->tanggal_tutup))); ?></td>
                        <td style="text-align:center"><?php echo (int) $g->max_pilihan_prodi; ?></td>
                        <td style="text-align:center">
                            <?php if ($g->status === 'aktif') : ?>
                                <span style="color:#00a32a;font-weight:600"><?php esc_html_e('Aktif', 'jalagistrasi'); ?></span>
                            <?php else : ?>
                                <span style="color:#787c82"><?php esc_html_e('Nonaktif', 'jalagistrasi'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $g->id], menu_page_url('jg-gelombang', false))); ?>">
                                <?php esc_html_e('Edit', 'jalagistrasi'); ?>
                            </a>
                            &nbsp;|&nbsp;
                            <form method="post"
                                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="display:inline"
                                  class="jg-delete-form">
                                <?php wp_nonce_field('jg_delete_gelombang_' . $g->id); ?>
                                <input type="hidden" name="action" value="jg_delete_gelombang">
                                <input type="hidden" name="gelombang_id" value="<?php echo (int) $g->id; ?>">
                                <button type="submit"
                                        class="button-link jg-delete-btn"
                                        style="color:#d63638"
                                        data-confirm="<?php esc_attr_e('Hapus gelombang ini? Tindakan tidak bisa dibatalkan.', 'jalagistrasi'); ?>">
                                    <?php esc_html_e('Hapus', 'jalagistrasi'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
