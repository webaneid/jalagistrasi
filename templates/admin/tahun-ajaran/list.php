<?php
/**
 * Template list tahun ajaran.
 *
 * @var list<object> $tahunAjaranList
 * @var string        $message
 */

defined('ABSPATH') || exit;

$messages = [
    'created'        => __('Tahun ajaran berhasil ditambahkan.', 'jalagistrasi'),
    'updated'        => __('Tahun ajaran berhasil diperbarui.', 'jalagistrasi'),
    'deleted'        => __('Tahun ajaran berhasil dihapus.', 'jalagistrasi'),
    'delete_blocked' => __('Tahun ajaran tidak dapat dihapus karena masih ada gelombang yang menggunakannya.', 'jalagistrasi'),
    'error'          => __('Terjadi kesalahan. Silakan coba lagi.', 'jalagistrasi'),
];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Tahun Ajaran', 'jalagistrasi'); ?></h1>
    <a href="<?php echo esc_url(add_query_arg('action', 'add', menu_page_url('jg-tahun-ajaran', false))); ?>"
       class="page-title-action">
        <?php esc_html_e('Tambah Tahun Ajaran', 'jalagistrasi'); ?>
    </a>
    <hr class="wp-header-end">

    <p class="description" style="margin-bottom:16px;">
        <?php esc_html_e('Buat tahun ajaran dulu sebelum membuat Gelombang Pendaftaran — tiap gelombang harus terhubung ke satu tahun ajaran.', 'jalagistrasi'); ?>
    </p>

    <?php if ($message !== '' && isset($messages[$message])) : ?>
        <?php $isError = in_array($message, ['delete_blocked', 'error'], true); ?>
        <div class="notice notice-<?php echo $isError ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Tahun Ajaran', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:15%;text-align:center"><?php esc_html_e('Status', 'jalagistrasi'); ?></th>
                <th scope="col" style="width:15%"><?php esc_html_e('Aksi', 'jalagistrasi'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tahunAjaranList)) : ?>
                <tr>
                    <td colspan="3">
                        <?php esc_html_e('Belum ada tahun ajaran. Klik "Tambah Tahun Ajaran" untuk mulai.', 'jalagistrasi'); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($tahunAjaranList as $ta) : ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $ta->id], menu_page_url('jg-tahun-ajaran', false))); ?>">
                                    <?php echo esc_html($ta->nama); ?>
                                </a>
                            </strong>
                        </td>
                        <td style="text-align:center">
                            <?php if ($ta->status === 'aktif') : ?>
                                <span style="color:#00a32a;font-weight:600"><?php esc_html_e('Aktif', 'jalagistrasi'); ?></span>
                            <?php else : ?>
                                <span style="color:#787c82"><?php esc_html_e('Nonaktif', 'jalagistrasi'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $ta->id], menu_page_url('jg-tahun-ajaran', false))); ?>">
                                <?php esc_html_e('Edit', 'jalagistrasi'); ?>
                            </a>
                            &nbsp;|&nbsp;
                            <form method="post"
                                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="display:inline"
                                  class="jg-delete-form">
                                <?php wp_nonce_field('jg_delete_tahun_ajaran_' . $ta->id); ?>
                                <input type="hidden" name="action" value="jg_delete_tahun_ajaran">
                                <input type="hidden" name="tahun_ajaran_id" value="<?php echo (int) $ta->id; ?>">
                                <button type="submit"
                                        class="button-link jg-delete-btn"
                                        style="color:#d63638"
                                        data-confirm="<?php esc_attr_e('Hapus tahun ajaran ini? Tindakan tidak bisa dibatalkan.', 'jalagistrasi'); ?>">
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
