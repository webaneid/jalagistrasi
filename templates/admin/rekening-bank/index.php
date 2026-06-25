<?php
/**
 * Admin — kelola rekening tujuan transfer biaya pendaftaran.
 *
 * @var list<object>  $items
 * @var object|null    $editItem
 * @var string         $saved
 * @var string         $deleted
 * @var list<string>   $errors
 * @var array<string,mixed> $old
 */
defined('ABSPATH') || exit;

$formUrl = admin_url('admin-post.php');
$val     = fn (string $k, mixed $default = '') => $old[$k] ?? ($editItem?->{$k} ?? $default);
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Rekening Bank Tujuan', 'jalagistrasi'); ?></h1>
    <hr class="wp-header-end">

    <p class="description" style="margin-bottom:16px;">
        <?php esc_html_e('Rekening yang ditampilkan ke pendaftar saat upload bukti pembayaran biaya pendaftaran. Berlaku untuk semua gelombang.', 'jalagistrasi'); ?>
    </p>

    <?php if ($saved === '1') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Rekening berhasil disimpan.', 'jalagistrasi'); ?></p></div>
    <?php endif; ?>
    <?php if ($deleted === '1') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Rekening berhasil dihapus.', 'jalagistrasi'); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($errors)) : ?>
        <div class="notice notice-error"><ul style="margin:.5em 0 .5em 1.5em;list-style:disc;">
            <?php foreach ($errors as $e) : ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

        <!-- Tabel daftar rekening -->
        <div style="flex:1;min-width:400px;">
            <?php if (empty($items)) : ?>
                <p class="description"><?php esc_html_e('Belum ada rekening. Tambahkan via form di sebelah kanan.', 'jalagistrasi'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="table-layout:auto;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Bank', 'jalagistrasi'); ?></th>
                            <th><?php esc_html_e('Nomor Rekening', 'jalagistrasi'); ?></th>
                            <th><?php esc_html_e('Nama Pemilik', 'jalagistrasi'); ?></th>
                            <th style="width:80px;text-align:center;"><?php esc_html_e('Aktif', 'jalagistrasi'); ?></th>
                            <th style="width:130px;"><?php esc_html_e('Aksi', 'jalagistrasi'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($item->nama_bank); ?></strong></td>
                                <td><code><?php echo esc_html($item->nomor_rekening); ?></code></td>
                                <td><?php echo esc_html($item->nama_pemilik); ?></td>
                                <td style="text-align:center;">
                                    <?php echo $item->is_aktif
                                        ? '<span style="color:#16a34a;font-weight:700;">✓</span>'
                                        : '<span style="color:#9ca3af;">—</span>'; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'jg-rekening-bank', 'edit' => $item->id], admin_url('admin.php'))); ?>"
                                       class="button button-small"><?php esc_html_e('Edit', 'jalagistrasi'); ?></a>
                                    <a href="<?php echo esc_url(add_query_arg([
                                            'action'   => 'jg_delete_rekening_bank',
                                            'id'       => $item->id,
                                            '_wpnonce' => wp_create_nonce('jg_delete_rekening_bank_' . $item->id),
                                        ], $formUrl)); ?>"
                                       class="button button-small"
                                       style="color:#b91c1c;border-color:#fca5a5;"
                                       onclick="return confirm('<?php esc_attr_e('Hapus rekening ini?', 'jalagistrasi'); ?>')"><?php esc_html_e('Hapus', 'jalagistrasi'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Form tambah / edit -->
        <div style="width:320px;flex-shrink:0;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle" style="font-size:14px;">
                        <?php echo $editItem
                            ? esc_html__('Edit Rekening', 'jalagistrasi')
                            : esc_html__('Tambah Rekening', 'jalagistrasi'); ?>
                    </h2>
                </div>
                <div class="inside" style="padding:12px 16px 16px;">
                    <form method="post" action="<?php echo esc_url($formUrl); ?>">
                        <?php wp_nonce_field('jg_save_rekening_bank'); ?>
                        <input type="hidden" name="action" value="jg_save_rekening_bank">
                        <?php if ($editItem) : ?>
                            <input type="hidden" name="rekening_bank_id" value="<?php echo esc_attr($editItem->id); ?>">
                        <?php endif; ?>

                        <div style="margin-bottom:12px;">
                            <label for="rb-bank" style="display:block;font-weight:600;margin-bottom:4px;">
                                <?php esc_html_e('Nama Bank', 'jalagistrasi'); ?> <span style="color:#dc2626;">*</span>
                            </label>
                            <input type="text" id="rb-bank" name="nama_bank"
                                   value="<?php echo esc_attr((string) $val('nama_bank')); ?>"
                                   class="widefat" placeholder="BCA" required>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label for="rb-nomor" style="display:block;font-weight:600;margin-bottom:4px;">
                                <?php esc_html_e('Nomor Rekening', 'jalagistrasi'); ?> <span style="color:#dc2626;">*</span>
                            </label>
                            <input type="text" id="rb-nomor" name="nomor_rekening"
                                   value="<?php echo esc_attr((string) $val('nomor_rekening')); ?>"
                                   class="widefat" placeholder="1234567890" required>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label for="rb-pemilik" style="display:block;font-weight:600;margin-bottom:4px;">
                                <?php esc_html_e('Nama Pemilik', 'jalagistrasi'); ?> <span style="color:#dc2626;">*</span>
                            </label>
                            <input type="text" id="rb-pemilik" name="nama_pemilik"
                                   value="<?php echo esc_attr((string) $val('nama_pemilik')); ?>"
                                   class="widefat" placeholder="<?php esc_attr_e('PT/Yayasan/nama institusi', 'jalagistrasi'); ?>" required>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="is_aktif" value="1"
                                       <?php checked((bool) $val('is_aktif', 1)); ?>>
                                <span style="font-weight:600;"><?php esc_html_e('Aktif (ditampilkan ke pendaftar)', 'jalagistrasi'); ?></span>
                            </label>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label for="rb-urutan" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Urutan', 'jalagistrasi'); ?></label>
                            <input type="number" id="rb-urutan" name="urutan"
                                   value="<?php echo esc_attr((string) $val('urutan', 0)); ?>"
                                   class="widefat" min="0">
                        </div>

                        <div style="display:flex;gap:8px;margin-top:4px;">
                            <input type="submit" class="button button-primary"
                                   value="<?php echo $editItem
                                       ? esc_attr__('Simpan Perubahan', 'jalagistrasi')
                                       : esc_attr__('Tambah', 'jalagistrasi'); ?>">
                            <?php if ($editItem) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=jg-rekening-bank')); ?>"
                                   class="button"><?php esc_html_e('Batal', 'jalagistrasi'); ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
