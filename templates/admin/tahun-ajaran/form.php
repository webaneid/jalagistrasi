<?php
/**
 * Template form tambah/edit tahun ajaran.
 *
 * @var object|null         $tahunAjaran  Row dari DB, null = tambah baru
 * @var list<string>        $errors
 * @var array<string,mixed> $saved
 */

defined('ABSPATH') || exit;

$isEdit = $tahunAjaran !== null;
$title  = $isEdit
    ? __('Edit Tahun Ajaran', 'jalagistrasi')
    : __('Tambah Tahun Ajaran', 'jalagistrasi');

$val = function (string $field, string $default = '') use ($saved, $tahunAjaran): string {
    if (isset($saved[$field])) {
        return (string) $saved[$field];
    }
    if ($tahunAjaran !== null && isset($tahunAjaran->$field)) {
        return (string) $tahunAjaran->$field;
    }
    return $default;
};
?>
<div class="wrap">
    <h1><?php echo esc_html($title); ?></h1>

    <a href="<?php echo esc_url(menu_page_url('jg-tahun-ajaran', false)); ?>">
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
        <?php wp_nonce_field('jg_tahun_ajaran_nonce'); ?>
        <input type="hidden" name="action" value="jg_save_tahun_ajaran">
        <?php if ($isEdit) : ?>
            <input type="hidden" name="tahun_ajaran_id" value="<?php echo (int) $tahunAjaran->id; ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="nama"><?php esc_html_e('Tahun Ajaran', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="nama"
                           name="nama"
                           class="regular-text"
                           maxlength="20"
                           placeholder="2026/2027"
                           required
                           pattern="\d{4}\/\d{4}"
                           value="<?php echo esc_attr($val('nama')); ?>">
                    <p class="description"><?php esc_html_e('Format: YYYY/YYYY', 'jalagistrasi'); ?></p>
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
                    <p class="description">
                        <?php esc_html_e('Tahun ajaran "Aktif" ditampilkan di halaman info publik. Tandai hanya satu tahun ajaran sebagai aktif pada satu waktu.', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button($isEdit ? __('Simpan Perubahan', 'jalagistrasi') : __('Tambah Tahun Ajaran', 'jalagistrasi')); ?>
    </form>
</div>
