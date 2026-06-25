<?php
/**
 * Template form tambah/edit gelombang.
 *
 * @var object|null       $gelombang  Row dari DB, null = tambah baru
 * @var list<string>      $errors     Pesan error validasi dari transient
 * @var array<string,mixed> $saved    Data POST yang disimpan transient (untuk repopulate)
 * @var list<object>      $tahunAjaranList Daftar tahun ajaran untuk dropdown
 */

defined('ABSPATH') || exit;

$isEdit = $gelombang !== null;
$title  = $isEdit
    ? __('Edit Gelombang', 'jalagistrasi')
    : __('Tambah Gelombang', 'jalagistrasi');

// Nilai field: prioritas saved (setelah error) → db row → default
$val = function (string $field, string $default = '') use ($saved, $gelombang): string {
    if (isset($saved[$field])) {
        return (string) $saved[$field];
    }
    if ($gelombang !== null && isset($gelombang->$field)) {
        return (string) $gelombang->$field;
    }
    return $default;
};

// Konversi MySQL DATETIME ke nilai datetime-local input (YYYY-MM-DDTHH:MM)
$toDatetimeLocal = function (string $mysqlDatetime): string {
    if ($mysqlDatetime === '') {
        return '';
    }
    return str_replace(' ', 'T', substr($mysqlDatetime, 0, 16));
};

$bukaNilai  = isset($saved['tanggal_buka']) ? $saved['tanggal_buka'] : $toDatetimeLocal($val('tanggal_buka'));
$tutupNilai = isset($saved['tanggal_tutup']) ? $saved['tanggal_tutup'] : $toDatetimeLocal($val('tanggal_tutup'));
?>
<div class="wrap">
    <h1><?php echo esc_html($title); ?></h1>

    <a href="<?php echo esc_url(menu_page_url('jg-gelombang', false)); ?>">
        &larr; <?php esc_html_e('Kembali ke daftar', 'jalagistrasi'); ?>
    </a>

    <?php if (!empty($errors)) : ?>
        <div class="notice notice-error">
            <p><strong><?php esc_html_e('Mohon perbaiki kesalahan berikut:', 'jalagistrasi'); ?></strong></p>
            <ul style="list-style:disc;margin-left:20px">
                <?php foreach ($errors as $err) : ?>
                    <li><?php echo esc_html($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('jg_gelombang_nonce'); ?>
        <input type="hidden" name="action" value="jg_save_gelombang">
        <?php if ($isEdit) : ?>
            <input type="hidden" name="gelombang_id" value="<?php echo (int) $gelombang->id; ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="nama"><?php esc_html_e('Nama Gelombang', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="nama"
                           name="nama"
                           class="regular-text"
                           maxlength="200"
                           required
                           value="<?php echo esc_attr($val('nama')); ?>">
                    <p class="description">
                        <?php esc_html_e('Contoh: Gelombang 1 - 2026/2027', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tahun_ajaran_id"><?php esc_html_e('Tahun Ajaran', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <?php if (empty($tahunAjaranList)) : ?>
                        <p class="description" style="color:#d63638;">
                            <?php esc_html_e('Belum ada Tahun Ajaran.', 'jalagistrasi'); ?>
                            <a href="<?php echo esc_url(add_query_arg('action', 'add', menu_page_url('jg-tahun-ajaran', false))); ?>">
                                <?php esc_html_e('Buat Tahun Ajaran dulu →', 'jalagistrasi'); ?>
                            </a>
                        </p>
                    <?php else : ?>
                        <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="regular-text" required>
                            <option value="">— <?php esc_html_e('Pilih Tahun Ajaran', 'jalagistrasi'); ?> —</option>
                            <?php foreach ($tahunAjaranList as $ta) : ?>
                                <option value="<?php echo esc_attr($ta->id); ?>" <?php selected((int) $val('tahun_ajaran_id', '0'), (int) $ta->id); ?>>
                                    <?php echo esc_html($ta->nama); ?>
                                    <?php if ($ta->status === 'aktif') : ?> (<?php esc_html_e('Aktif', 'jalagistrasi'); ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Belum ada tahun ajaran yang sesuai?', 'jalagistrasi'); ?>
                            <a href="<?php echo esc_url(add_query_arg('action', 'add', menu_page_url('jg-tahun-ajaran', false))); ?>" target="_blank">
                                <?php esc_html_e('Tambah Tahun Ajaran baru', 'jalagistrasi'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tanggal_buka"><?php esc_html_e('Tanggal Buka', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="datetime-local"
                           id="tanggal_buka"
                           name="tanggal_buka"
                           required
                           value="<?php echo esc_attr($bukaNilai); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tanggal_tutup"><?php esc_html_e('Tanggal Tutup', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="datetime-local"
                           id="tanggal_tutup"
                           name="tanggal_tutup"
                           required
                           value="<?php echo esc_attr($tutupNilai); ?>">
                    <p class="description">
                        <?php esc_html_e('Harus setelah tanggal buka.', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="max_pilihan_prodi"><?php esc_html_e('Maks. Pilihan Program Studi', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="number"
                           id="max_pilihan_prodi"
                           name="max_pilihan_prodi"
                           class="small-text"
                           min="1"
                           max="10"
                           required
                           value="<?php echo esc_attr($val('max_pilihan_prodi', '2')); ?>">
                    <p class="description">
                        <?php esc_html_e('Berapa pilihan prodi yang boleh dipilih pendaftar (1–10).', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="biaya_pendaftaran"><?php esc_html_e('Biaya Pendaftaran (Rp)', 'jalagistrasi'); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="biaya_pendaftaran"
                           name="biaya_pendaftaran"
                           class="regular-text"
                           min="0"
                           step="1000"
                           value="<?php echo esc_attr($val('biaya_pendaftaran', '0')); ?>">
                    <p class="description">
                        <?php esc_html_e('Nominal yang harus ditransfer pendaftar (belum termasuk kode unik otomatis). Isi 0 jika tidak ada biaya.', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="status"><?php esc_html_e('Status', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <select id="status" name="status" required>
                        <option value="nonaktif" <?php selected($val('status', 'nonaktif'), 'nonaktif'); ?>>
                            <?php esc_html_e('Nonaktif', 'jalagistrasi'); ?>
                        </option>
                        <option value="aktif" <?php selected($val('status', 'nonaktif'), 'aktif'); ?>>
                            <?php esc_html_e('Aktif', 'jalagistrasi'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button($isEdit ? __('Simpan Perubahan', 'jalagistrasi') : __('Tambah Gelombang', 'jalagistrasi')); ?>
    </form>
</div>
