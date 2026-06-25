<?php
/**
 * Template list program studi.
 *
 * @var list<object> $prodiList
 * @var string       $message
 * @var string       $statusFilter
 */

defined('ABSPATH') || exit;

$messages = [
    'created'       => __('Program studi berhasil ditambahkan.', 'jalagistrasi'),
    'updated'       => __('Program studi berhasil diperbarui.', 'jalagistrasi'),
    'deleted'       => __('Program studi berhasil dihapus.', 'jalagistrasi'),
    'delete_blocked'=> __('Program studi tidak dapat dihapus karena sudah dipilih oleh pendaftar.', 'jalagistrasi'),
    'error'         => __('Terjadi kesalahan. Silakan coba lagi.', 'jalagistrasi'),
];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Program Studi', 'jalagistrasi'); ?></h1>
    <a href="<?php echo esc_url(add_query_arg('action', 'add', menu_page_url('jg-program-studi', false))); ?>"
       class="page-title-action">
        <?php esc_html_e('Tambah Program Studi', 'jalagistrasi'); ?>
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
            <a href="<?php echo esc_url(menu_page_url('jg-program-studi', false)); ?>"
               <?php echo $statusFilter === '' ? 'class="current"' : ''; ?>>
                <?php esc_html_e('Semua', 'jalagistrasi'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('status_filter', 'aktif', menu_page_url('jg-program-studi', false))); ?>"
               <?php echo $statusFilter === 'aktif' ? 'class="current"' : ''; ?>>
                <?php esc_html_e('Aktif', 'jalagistrasi'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('status_filter', 'nonaktif', menu_page_url('jg-program-studi', false))); ?>"
               <?php echo $statusFilter === 'nonaktif' ? 'class="current"' : ''; ?>>
                <?php esc_html_e('Nonaktif', 'jalagistrasi'); ?>
            </a>
        </li>
    </ul>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" style="width:8%"><?php esc_html_e('Kode', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:32%"><?php esc_html_e('Nama Program Studi', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:8%;text-align:center"><?php esc_html_e('Kuota', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:8%;text-align:center"><?php esc_html_e('Urutan', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:10%;text-align:center"><?php esc_html_e('Status', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:20%"><?php esc_html_e('Deskripsi', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:10%"><?php esc_html_e('Aksi', 'jalagistrasi'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($prodiList)) : ?>
                <tr>
                    <td colspan="7">
                        <?php esc_html_e('Belum ada program studi. Klik "Tambah Program Studi" untuk mulai.', 'jalagistrasi'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($prodiList as $p) : ?>
                    <tr>
                        <td><code><?php echo esc_html($p->kode); ?></code></td>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $p->id], menu_page_url('jg-program-studi', false))); ?>">
                                    <?php echo esc_html($p->nama); ?>
                                </a>
                            </strong>
                        </td>
                        <td style="text-align:center"><?php echo (int) $p->kuota; ?></td>
                        <td style="text-align:center"><?php echo (int) $p->urutan; ?></td>
                        <td style="text-align:center">
                            <?php if ($p->status === 'aktif') : ?>
                                <span style="color:#00a32a;font-weight:600"><?php esc_html_e('Aktif', 'jalagistrasi'); ?></span>
                            <?php else : ?>
                                <span style="color:#787c82"><?php esc_html_e('Nonaktif', 'jalagistrasi'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p->deskripsi) : ?>
                                <span title="<?php echo esc_attr($p->deskripsi); ?>">
                                    <?php echo esc_html(wp_trim_words($p->deskripsi, 6)); ?>
                                </span>
                            <?php else : ?>
                                <span style="color:#999">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $p->id], menu_page_url('jg-program-studi', false))); ?>">
                                <?php esc_html_e('Edit', 'jalagistrasi'); ?>
                            </a>
                            &nbsp;|&nbsp;
                            <form method="post"
                                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="display:inline"
                                  class="jg-delete-form">
                                <?php wp_nonce_field('jg_delete_prodi_' . $p->id); ?>
                                <input type="hidden" name="action" value="jg_delete_program_studi">
                                <input type="hidden" name="prodi_id" value="<?php echo (int) $p->id; ?>">
                                <button type="submit"
                                        class="button-link jg-delete-btn"
                                        style="color:#d63638"
                                        data-confirm="<?php esc_attr_e('Hapus program studi ini? Tindakan tidak bisa dibatalkan.', 'jalagistrasi'); ?>">
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
