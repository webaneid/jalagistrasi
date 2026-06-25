<?php
/**
 * Admin — konfigurasi tipe berkas per gelombang.
 *
 * @var list<object>  $gelombangList
 * @var int           $gelombangId
 * @var object|null   $gelombang
 * @var list<object>  $items
 * @var object|null   $editItem
 * @var string        $saved
 * @var string        $deleted
 * @var list<string>  $errors
 * @var array<string,mixed> $old
 */
defined('ABSPATH') || exit;

$pageUrl = admin_url('admin.php?page=jg-tipe-berkas');
$formUrl = admin_url('admin-post.php');
$val     = fn(string $k, mixed $default = '') => $old[$k] ?? ($editItem?->{$k} ?? $default);
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Tipe Berkas Upload', 'jalagistrasi'); ?></h1>
    <hr class="wp-header-end">

    <p class="description" style="margin-bottom:16px;">
        <?php esc_html_e('Konfigurasi dokumen yang wajib diupload pendaftar pada Step 3 (setelah mengisi formulir pendaftaran).', 'jalagistrasi'); ?>
    </p>

    <?php if ($saved === '1') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Tipe berkas berhasil disimpan.', 'jalagistrasi'); ?></p></div>
    <?php endif; ?>
    <?php if ($deleted === '1') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Tipe berkas berhasil dihapus.', 'jalagistrasi'); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($errors)) : ?>
        <div class="notice notice-error"><ul style="margin:.5em 0 .5em 1.5em;list-style:disc;">
            <?php foreach ($errors as $e) : ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <?php if (empty($gelombangList)) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e('Buat gelombang terlebih dahulu sebelum mengkonfigurasi tipe berkas.', 'jalagistrasi'); ?></p></div>
    <?php else : ?>

    <!-- Pilih gelombang -->
    <form method="get" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="jg-tipe-berkas">
        <label for="sel-gelombang" style="font-weight:600;margin-right:8px;"><?php esc_html_e('Gelombang:', 'jalagistrasi'); ?></label>
        <select id="sel-gelombang" name="gelombang_id" onchange="this.form.submit()" style="max-width:300px;">
            <?php foreach ($gelombangList as $g) : ?>
                <option value="<?php echo esc_attr($g->id); ?>" <?php selected((int) $g->id, $gelombangId); ?>>
                    <?php echo esc_html($g->nama . ' — ' . $g->tahun_akademik); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><input type="submit" class="button" value="<?php esc_attr_e('Pilih', 'jalagistrasi'); ?>"></noscript>
    </form>

    <!-- Layout: tabel kiri, form kanan -->
    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">

        <!-- Tabel daftar tipe berkas -->
        <div style="flex:1;min-width:400px;">
            <h2 style="margin-top:0;font-size:14px;font-weight:600;color:#1d2327;">
                <?php echo esc_html($gelombang?->nama . ' — ' . $gelombang?->tahun_akademik); ?>
            </h2>

            <?php if (empty($items)) : ?>
                <p class="description"><?php esc_html_e('Belum ada tipe berkas untuk gelombang ini. Tambahkan via form di sebelah kanan.', 'jalagistrasi'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="table-layout:auto;">
                    <thead>
                        <tr>
                            <th style="width:36px;">#</th>
                            <th style="width:110px;"><?php esc_html_e('Kode', 'jalagistrasi'); ?></th>
                            <th><?php esc_html_e('Label', 'jalagistrasi'); ?></th>
                            <th style="width:70px;text-align:center;"><?php esc_html_e('Wajib', 'jalagistrasi'); ?></th>
                            <th style="width:85px;text-align:right;"><?php esc_html_e('Maks KB', 'jalagistrasi'); ?></th>
                            <th style="width:120px;"><?php esc_html_e('Aksi', 'jalagistrasi'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item) : ?>
                            <tr>
                                <td><?php echo $item->urutan ?: ($i + 1); ?></td>
                                <td><code><?php echo esc_html($item->kode); ?></code></td>
                                <td>
                                    <strong><?php echo esc_html($item->label); ?></strong>
                                    <?php if ($item->keterangan) : ?>
                                        <br><span class="description"><?php echo esc_html($item->keterangan); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($item->is_required) : ?>
                                        <span style="color:#16a34a;font-weight:700;">✓</span>
                                    <?php else : ?>
                                        <span style="color:#9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;"><?php echo esc_html(number_format((int) $item->max_size_kb)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'jg-tipe-berkas', 'gelombang_id' => $gelombangId, 'edit' => $item->id], admin_url('admin.php'))); ?>"
                                       class="button button-small"><?php esc_html_e('Edit', 'jalagistrasi'); ?></a>
                                    <a href="<?php echo esc_url(add_query_arg([
                                            'action'      => 'jg_delete_tipe_berkas',
                                            'id'          => $item->id,
                                            'gelombang_id' => $gelombangId,
                                            '_wpnonce'    => wp_create_nonce('jg_delete_tipe_berkas_' . $item->id),
                                        ], $formUrl)); ?>"
                                       class="button button-small"
                                       style="color:#b91c1c;border-color:#fca5a5;"
                                       onclick="return confirm('<?php esc_attr_e('Hapus tipe berkas ini?', 'jalagistrasi'); ?>')"><?php esc_html_e('Hapus', 'jalagistrasi'); ?></a>
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
                            ? esc_html__('Edit Tipe Berkas', 'jalagistrasi')
                            : esc_html__('Tambah Tipe Berkas', 'jalagistrasi'); ?>
                    </h2>
                </div>
                <div class="inside" style="padding:12px 16px 16px;">
                    <form method="post" action="<?php echo esc_url($formUrl); ?>">
                        <?php wp_nonce_field('jg_save_tipe_berkas'); ?>
                        <input type="hidden" name="action" value="jg_save_tipe_berkas">
                        <input type="hidden" name="gelombang_id" value="<?php echo esc_attr($gelombangId); ?>">
                        <?php if ($editItem) : ?>
                            <input type="hidden" name="tipe_berkas_id" value="<?php echo esc_attr($editItem->id); ?>">
                        <?php endif; ?>

                        <div style="margin-bottom:12px;">
                            <label for="tb-kode" style="display:block;font-weight:600;margin-bottom:4px;">
                                <?php esc_html_e('Kode', 'jalagistrasi'); ?> <span style="color:#dc2626;">*</span>
                            </label>
                            <input type="text" id="tb-kode" name="kode"
                                   value="<?php echo esc_attr((string) $val('kode')); ?>"
                                   class="widefat" placeholder="ktp"
                                   required pattern="[a-z0-9_\-]+"
                                   <?php echo $editItem ? 'readonly style="background:#f9fafb;"' : ''; ?>>
                            <p class="description" style="margin-top:4px;"><?php esc_html_e('Huruf kecil, angka, underscore. Contoh: ktp, kk, ijazah, foto', 'jalagistrasi'); ?></p>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label for="tb-label" style="display:block;font-weight:600;margin-bottom:4px;">
                                <?php esc_html_e('Label', 'jalagistrasi'); ?> <span style="color:#dc2626;">*</span>
                            </label>
                            <input type="text" id="tb-label" name="label"
                                   value="<?php echo esc_attr((string) $val('label')); ?>"
                                   class="widefat" placeholder="<?php esc_attr_e('Kartu Tanda Penduduk', 'jalagistrasi'); ?>" required>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label for="tb-ket" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Keterangan', 'jalagistrasi'); ?></label>
                            <textarea id="tb-ket" name="keterangan" rows="3" class="widefat"
                                      placeholder="<?php esc_attr_e('Instruksi upload untuk pendaftar (opsional)', 'jalagistrasi'); ?>"><?php echo esc_textarea((string) $val('keterangan')); ?></textarea>
                        </div>

                        <div style="margin-bottom:12px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="is_required" value="1"
                                       <?php checked((bool) $val('is_required', 1)); ?>>
                                <span style="font-weight:600;"><?php esc_html_e('Wajib diupload', 'jalagistrasi'); ?></span>
                            </label>
                        </div>

                        <div style="display:flex;gap:12px;margin-bottom:12px;">
                            <div style="flex:1;">
                                <label for="tb-max" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Maks Ukuran (KB)', 'jalagistrasi'); ?></label>
                                <input type="number" id="tb-max" name="max_size_kb"
                                       value="<?php echo esc_attr((string) $val('max_size_kb', 2048)); ?>"
                                       class="widefat" min="100" max="10240">
                            </div>
                            <div style="flex:1;">
                                <label for="tb-urutan" style="display:block;font-weight:600;margin-bottom:4px;"><?php esc_html_e('Urutan', 'jalagistrasi'); ?></label>
                                <input type="number" id="tb-urutan" name="urutan"
                                       value="<?php echo esc_attr((string) $val('urutan', 0)); ?>"
                                       class="widefat" min="0">
                            </div>
                        </div>

                        <div style="display:flex;gap:8px;margin-top:4px;">
                            <input type="submit" class="button button-primary"
                                   value="<?php echo $editItem
                                       ? esc_attr__('Simpan Perubahan', 'jalagistrasi')
                                       : esc_attr__('Tambah', 'jalagistrasi'); ?>">
                            <?php if ($editItem) : ?>
                                <a href="<?php echo esc_url(add_query_arg(['page' => 'jg-tipe-berkas', 'gelombang_id' => $gelombangId], admin_url('admin.php'))); ?>"
                                   class="button"><?php esc_html_e('Batal', 'jalagistrasi'); ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /flex -->
    <?php endif; ?>
</div>
