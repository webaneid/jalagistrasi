<?php
/**
 * Template halaman utama Form Builder.
 *
 * @var list<object>      $gelombangList  Semua gelombang
 * @var object|null       $gelombang      Gelombang yang sedang dipilih
 * @var int               $gelombangId    ID gelombang aktif
 * @var array<string,list<object>> $sections  Field dikelompokkan per seksi
 * @var string            $message        Pesan sukses/error
 */

defined('ABSPATH') || exit;

$messages = [
    'created'      => __('Field berhasil ditambahkan.', 'jalagistrasi'),
    'updated'      => __('Field berhasil diperbarui.', 'jalagistrasi'),
    'deleted'      => __('Field berhasil dihapus.', 'jalagistrasi'),
    'delete_core'  => __('Field inti tidak dapat dihapus.', 'jalagistrasi'),
    'seeded'       => __('Template default (34 field) berhasil dimuat.', 'jalagistrasi'),
    'error'        => __('Terjadi kesalahan. Silakan coba lagi.', 'jalagistrasi'),
];

$baseUrl = admin_url('admin.php?page=jg-form-builder');
?>
<div class="wrap">
    <h1><?php esc_html_e('Form Builder', 'jalagistrasi'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($message !== '' && isset($messages[$message])) : ?>
        <?php $isError = in_array($message, ['delete_core', 'error'], true); ?>
        <div class="notice notice-<?php echo $isError ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <!-- Pilih Gelombang -->
    <div class="postbox" style="padding:16px 20px;margin-bottom:20px">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <input type="hidden" name="page" value="jg-form-builder">
            <label for="gelombang_id" style="font-weight:600">
                <?php esc_html_e('Pilih Gelombang:', 'jalagistrasi'); ?>
            </label>
            <select id="gelombang_id" name="gelombang_id" style="min-width:280px">
                <option value=""><?php esc_html_e('— Pilih Gelombang —', 'jalagistrasi'); ?></option>
                <?php foreach ($gelombangList as $g) : ?>
                    <option value="<?php echo (int) $g->id; ?>" <?php selected((int) $g->id, $gelombangId); ?>>
                        <?php echo esc_html($g->nama . ' (' . $g->tahun_akademik . ')'); ?>
                        <?php if ($g->status === 'aktif') : ?>
                            — <?php esc_html_e('Aktif', 'jalagistrasi'); ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php esc_html_e('Pilih', 'jalagistrasi'); ?></button>
        </form>
    </div>

    <?php if ($gelombangId <= 0) : ?>
        <p class="description"><?php esc_html_e('Pilih gelombang untuk melihat dan mengelola form fields.', 'jalagistrasi'); ?></p>
        <?php return; ?>
    <?php endif; ?>

    <!-- Header + tombol tambah -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <h2 style="margin:0">
            <?php echo esc_html($gelombang ? $gelombang->nama : ''); ?>
            <span style="font-size:13px;font-weight:400;color:#787c82">
                — <?php echo array_sum(array_map('count', $sections)); ?> <?php esc_html_e('field', 'jalagistrasi'); ?>
            </span>
        </h2>
        <a href="<?php echo esc_url(add_query_arg(['action' => 'add', 'gelombang_id' => $gelombangId], $baseUrl)); ?>"
           class="button button-primary">
            + <?php esc_html_e('Tambah Field', 'jalagistrasi'); ?>
        </a>
    </div>

    <p class="description" style="margin-bottom:16px">
        <?php esc_html_e('Drag & drop baris untuk mengubah urutan tampil. Urutan disimpan otomatis.', 'jalagistrasi'); ?>
    </p>

    <?php if (empty($sections)) : ?>
        <div class="notice notice-info" style="margin:0 0 16px;padding:16px 20px;display:flex;align-items:flex-start;gap:16px;border-left-color:#2271b1">
            <div style="flex:1">
                <p style="margin:0 0 4px;font-weight:600">
                    <?php esc_html_e('Gelombang ini belum memiliki field formulir.', 'jalagistrasi'); ?>
                </p>
                <p style="margin:0;color:#50575e;font-size:13px">
                    <?php esc_html_e('Muat template default untuk langsung mendapatkan 34 field standar PMB (biodata, sekolah, orang tua, pertanyaan tambahan), atau tambah field secara manual.', 'jalagistrasi'); ?>
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;align-items:center">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('jg_seed_default_form_' . $gelombangId); ?>
                    <input type="hidden" name="action" value="jg_seed_default_form">
                    <input type="hidden" name="gelombang_id" value="<?php echo (int) $gelombangId; ?>">
                    <button type="submit" class="button button-primary">
                        &#10022; <?php esc_html_e('Muat Template Default', 'jalagistrasi'); ?>
                    </button>
                </form>
                <a href="<?php echo esc_url(add_query_arg(['action' => 'add', 'gelombang_id' => $gelombangId], $baseUrl)); ?>"
                   class="button">
                    <?php esc_html_e('Tambah Manual', 'jalagistrasi'); ?>
                </a>
            </div>
        </div>
    <?php else : ?>

        <table class="wp-list-table widefat fixed striped" id="jg-field-table">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th style="width:5%"><?php esc_html_e('#', 'jalagistrasi'); ?></th>
                    <th style="width:22%"><?php esc_html_e('Label', 'jalagistrasi'); ?></th>
                    <th style="width:18%"><?php esc_html_e('Nama Field', 'jalagistrasi'); ?></th>
                    <th style="width:10%"><?php esc_html_e('Tipe', 'jalagistrasi'); ?></th>
                    <th style="width:18%"><?php esc_html_e('Seksi', 'jalagistrasi'); ?></th>
                    <th style="width:7%;text-align:center"><?php esc_html_e('Wajib', 'jalagistrasi'); ?></th>
                    <th style="width:7%;text-align:center"><?php esc_html_e('Inti', 'jalagistrasi'); ?></th>
                    <th style="width:12%"><?php esc_html_e('Aksi', 'jalagistrasi'); ?></th>
                </tr>
            </thead>
            <tbody id="jg-sortable-fields">
                <?php foreach ($sections as $sectionName => $sectionFields) : ?>
                    <tr class="jg-section-header" data-nodrag="1">
                        <td colspan="9" style="background:#f0f0f0;font-weight:700;padding:6px 12px;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#50575e">
                            <?php echo esc_html($sectionName); ?>
                        </td>
                    </tr>
                    <?php foreach ($sectionFields as $f) : ?>
                        <tr data-field-id="<?php echo (int) $f->id; ?>">
                            <td style="cursor:grab;color:#b4b9be;text-align:center;font-size:18px" class="jg-drag-handle" title="Drag untuk ubah urutan">&#9776;</td>
                            <td><?php echo (int) $f->urutan; ?></td>
                            <td><strong><?php echo esc_html($f->label); ?></strong></td>
                            <td><code><?php echo esc_html($f->nama_field); ?></code></td>
                            <td><span class="jg-badge jg-badge-<?php echo esc_attr($f->tipe); ?>"><?php echo esc_html($f->tipe); ?></span></td>
                            <td style="color:#787c82"><?php echo $f->section_name ? esc_html($f->section_name) : '—'; ?></td>
                            <td style="text-align:center">
                                <?php echo (int) $f->is_required ? '<span style="color:#00a32a">✓</span>' : '<span style="color:#ccc">—</span>'; ?>
                            </td>
                            <td style="text-align:center">
                                <?php if ((int) $f->is_core) : ?>
                                    <span title="<?php esc_attr_e('Field inti — tidak bisa dihapus', 'jalagistrasi'); ?>" style="color:#d63638">🔒</span>
                                <?php else : ?>
                                    <span style="color:#ccc">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'gelombang_id' => $gelombangId, 'field_id' => $f->id], $baseUrl)); ?>">
                                    <?php esc_html_e('Edit', 'jalagistrasi'); ?>
                                </a>
                                <?php if (!(int) $f->is_core) : ?>
                                    &nbsp;|&nbsp;
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" class="jg-delete-form">
                                        <?php wp_nonce_field('jg_delete_field_' . $f->id); ?>
                                        <input type="hidden" name="action" value="jg_delete_form_field">
                                        <input type="hidden" name="field_id" value="<?php echo (int) $f->id; ?>">
                                        <button type="submit" class="button-link jg-delete-btn" style="color:#d63638"
                                            data-confirm="<?php esc_attr_e('Hapus field ini?', 'jalagistrasi'); ?>">
                                            <?php esc_html_e('Hapus', 'jalagistrasi'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        jQuery(function($) {
            var $tbody = $('#jg-sortable-fields');

            $tbody.sortable({
                items: 'tr:not([data-nodrag])',
                handle: '.jg-drag-handle',
                axis: 'y',
                placeholder: 'jg-sort-placeholder',
                tolerance: 'pointer',
                update: function() {
                    var order = [];
                    $tbody.find('tr[data-field-id]').each(function() {
                        order.push($(this).data('field-id'));
                    });

                    $.post(ajaxurl, {
                        action: 'jg_reorder_fields',
                        _ajax_nonce: '<?php echo esc_js(wp_create_nonce('jg_reorder_fields')); ?>',
                        order: order
                    }, function(res) {
                        if (!res.success) {
                            alert('Gagal menyimpan urutan.');
                        }
                    });
                }
            });

            // Update nomor urutan setelah drag
            $tbody.on('sortstop', function() {
                var i = 1;
                $tbody.find('tr[data-field-id] td:nth-child(2)').each(function() {
                    $(this).text(i++);
                });
            });
        });
        </script>

        <style>
        .jg-sort-placeholder { background: #f0f6fc; height: 40px; }
        .jg-badge { display:inline-block;padding:2px 6px;border-radius:3px;font-size:11px;background:#f0f0f0;color:#3c434a; }
        </style>

    <?php endif; ?>
</div>
