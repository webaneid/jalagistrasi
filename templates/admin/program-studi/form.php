<?php
/**
 * Template form tambah/edit program studi.
 *
 * @var object|null         $prodi   Row dari DB, null = tambah baru
 * @var list<string>        $errors  Pesan error dari transient
 * @var array<string,mixed> $saved   Data POST dari transient (repopulate setelah error)
 */

defined('ABSPATH') || exit;

$isEdit = $prodi !== null;
$title  = $isEdit
    ? __('Edit Program Studi', 'jalagistrasi')
    : __('Tambah Program Studi', 'jalagistrasi');

$val = function (string $field, string $default = '') use ($saved, $prodi): string {
    if (isset($saved[$field])) {
        return (string) $saved[$field];
    }
    if ($prodi !== null && isset($prodi->$field)) {
        return (string) $prodi->$field;
    }
    return $default;
};
?>
<div class="wrap">
    <h1><?php echo esc_html($title); ?></h1>

    <a href="<?php echo esc_url(menu_page_url('jg-program-studi', false)); ?>">
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
        <?php wp_nonce_field('jg_program_studi_nonce'); ?>
        <input type="hidden" name="action" value="jg_save_program_studi">
        <?php if ($isEdit) : ?>
            <input type="hidden" name="prodi_id" value="<?php echo (int) $prodi->id; ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="nama"><?php esc_html_e('Nama Program Studi', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
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
                        <?php esc_html_e('Contoh: Teknik Informatika, Manajemen', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="kode"><?php esc_html_e('Kode', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="kode"
                           name="kode"
                           class="small-text"
                           maxlength="20"
                           required
                           style="text-transform:uppercase"
                           placeholder="TI"
                           value="<?php echo esc_attr($val('kode')); ?>">
                    <p class="description">
                        <?php esc_html_e('Huruf kapital, angka, dan tanda hubung. Contoh: TI, MNJ, AKT-S1', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="deskripsi"><?php esc_html_e('Deskripsi', 'jalagistrasi'); ?></label>
                </th>
                <td>
                    <textarea id="deskripsi"
                              name="deskripsi"
                              rows="4"
                              class="large-text"><?php echo esc_textarea($val('deskripsi')); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Opsional. Deskripsi singkat program studi.', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="kuota"><?php esc_html_e('Kuota', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="number"
                           id="kuota"
                           name="kuota"
                           class="small-text"
                           min="0"
                           required
                           value="<?php echo esc_attr($val('kuota', '0')); ?>">
                    <p class="description">
                        <?php esc_html_e('Jumlah maksimal mahasiswa yang diterima. Isi 0 untuk tidak membatasi.', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="urutan"><?php esc_html_e('Urutan Tampil', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="number"
                           id="urutan"
                           name="urutan"
                           class="small-text"
                           min="0"
                           required
                           value="<?php echo esc_attr($val('urutan', '0')); ?>">
                    <p class="description">
                        <?php esc_html_e('Urutan tampil di form pendaftaran. Angka lebih kecil tampil lebih atas.', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="status"><?php esc_html_e('Status', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <select id="status" name="status" required>
                        <option value="aktif" <?php selected($val('status', 'aktif'), 'aktif'); ?>>
                            <?php esc_html_e('Aktif', 'jalagistrasi'); ?>
                        </option>
                        <option value="nonaktif" <?php selected($val('status', 'aktif'), 'nonaktif'); ?>>
                            <?php esc_html_e('Nonaktif', 'jalagistrasi'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Hanya program studi aktif yang tampil di formulir pendaftaran.', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button($isEdit ? __('Simpan Perubahan', 'jalagistrasi') : __('Tambah Program Studi', 'jalagistrasi')); ?>
    </form>
</div>
