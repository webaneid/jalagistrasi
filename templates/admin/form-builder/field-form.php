<?php
/**
 * Template form tambah/edit field.
 *
 * @var object|null         $gelombang      Gelombang yang sedang diedit
 * @var object|null         $field          Field dari DB (null = tambah baru)
 * @var list<string>        $errors         Pesan error validasi
 * @var array<string,mixed> $saved          Data POST dari transient
 * @var list<\Webane\Jalagistrasi\Enum\TipeField> $tipeOptions
 * @var list<string>        $sectionOptions Daftar seksi yang sudah ada
 */

defined('ABSPATH') || exit;

$isEdit      = $field !== null;
$isCore      = $isEdit && (int) $field->is_core === 1;
$gelombangId = $gelombang ? (int) $gelombang->id : 0;
$title       = $isEdit ? __('Edit Field', 'jalagistrasi') : __('Tambah Field', 'jalagistrasi');
$baseUrl     = add_query_arg('gelombang_id', $gelombangId, admin_url('admin.php?page=jg-form-builder'));

// Helper: ambil nilai dari saved (setelah error) → field DB → default
$val = function (string $key, string $default = '') use ($saved, $field): string {
    if (isset($saved[$key])) {
        return (string) $saved[$key];
    }
    if ($field !== null && isset($field->$key)) {
        return (string) $field->$key;
    }
    return $default;
};

// Decode konfigurasi field
$konfig = [];
if ($field && $field->konfigurasi) {
    $konfig = json_decode($field->konfigurasi, true) ?? [];
}
if (!empty($saved['options_text'])) {
    $optionsText = $saved['options_text'];
} elseif (!empty($konfig['options'])) {
    $optionsText = implode("\n", $konfig['options']);
} else {
    $optionsText = '';
}

$currentTipe = $val('tipe');
$tipiDenganOptions = ['select', 'radio', 'checkbox'];
?>
<div class="wrap">
    <h1><?php echo esc_html($title); ?></h1>

    <a href="<?php echo esc_url($baseUrl); ?>">
        &larr; <?php esc_html_e('Kembali ke daftar field', 'jalagistrasi'); ?>
    </a>
    <?php if ($gelombang) : ?>
        <span style="margin-left:8px;color:#787c82">— <?php echo esc_html($gelombang->nama); ?></span>
    <?php endif; ?>

    <?php if ($isCore) : ?>
        <div class="notice notice-warning" style="margin-top:12px">
            <p><?php esc_html_e('Field inti: label, seksi, dan urutan dapat diubah. Nama field dan tipe tidak bisa diubah.', 'jalagistrasi'); ?></p>
        </div>
    <?php endif; ?>

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

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="jg-field-form">
        <?php wp_nonce_field('jg_form_field_nonce'); ?>
        <input type="hidden" name="action" value="jg_save_form_field">
        <input type="hidden" name="gelombang_id" value="<?php echo (int) $gelombangId; ?>">
        <?php if ($isEdit) : ?>
            <input type="hidden" name="field_id" value="<?php echo (int) $field->id; ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">

            <!-- Nama Field -->
            <tr>
                <th scope="row">
                    <label for="nama_field"><?php esc_html_e('Nama Field', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="nama_field"
                           name="nama_field"
                           class="regular-text"
                           maxlength="100"
                           <?php echo $isCore ? 'readonly style="background:#f6f7f7;color:#787c82"' : 'required'; ?>
                           placeholder="contoh: nama_lengkap"
                           value="<?php echo esc_attr($val('nama_field')); ?>">
                    <p class="description">
                        <?php esc_html_e('Huruf kecil, angka, dan underscore. Tidak bisa diubah setelah disimpan (kecuali hapus dan buat ulang).', 'jalagistrasi'); ?>
                    </p>
                </td>
            </tr>

            <!-- Label -->
            <tr>
                <th scope="row">
                    <label for="label"><?php esc_html_e('Label', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <input type="text"
                           id="label"
                           name="label"
                           class="regular-text"
                           maxlength="200"
                           required
                           value="<?php echo esc_attr($val('label')); ?>">
                    <p class="description"><?php esc_html_e('Teks yang ditampilkan kepada pendaftar.', 'jalagistrasi'); ?></p>
                </td>
            </tr>

            <!-- Tipe -->
            <tr>
                <th scope="row">
                    <label for="tipe"><?php esc_html_e('Tipe Field', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <select id="tipe" name="tipe" required <?php echo $isCore ? 'disabled' : ''; ?>>
                        <?php if ($isCore) : ?>
                            <input type="hidden" name="tipe" value="<?php echo esc_attr($val('tipe')); ?>">
                        <?php endif; ?>
                        <?php foreach ($tipeOptions as $tipeEnum) : ?>
                            <option value="<?php echo esc_attr($tipeEnum->value); ?>"
                                <?php selected($val('tipe'), $tipeEnum->value); ?>>
                                <?php echo esc_html($tipeEnum->value); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Pilihan (untuk select/radio/checkbox) -->
            <tr id="row-options" style="<?php echo in_array($currentTipe, $tipiDenganOptions, true) ? '' : 'display:none'; ?>">
                <th scope="row">
                    <label for="options_text"><?php esc_html_e('Pilihan', 'jalagistrasi'); ?> <span style="color:red">*</span></label>
                </th>
                <td>
                    <textarea id="options_text"
                              name="options_text"
                              rows="6"
                              class="large-text"
                              placeholder="<?php esc_attr_e('Satu pilihan per baris', 'jalagistrasi'); ?>"><?php echo esc_textarea($optionsText); ?></textarea>
                    <p class="description"><?php esc_html_e('Satu pilihan per baris.', 'jalagistrasi'); ?></p>
                </td>
            </tr>

            <!-- Placeholder (text/textarea) -->
            <tr id="row-placeholder" style="<?php echo in_array($currentTipe, ['text', 'textarea'], true) ? '' : 'display:none'; ?>">
                <th scope="row">
                    <label for="placeholder"><?php esc_html_e('Placeholder', 'jalagistrasi'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="placeholder"
                           name="placeholder"
                           class="regular-text"
                           value="<?php echo esc_attr($konfig['placeholder'] ?? ($saved['placeholder'] ?? '')); ?>">
                </td>
            </tr>

            <!-- Max Length (text/textarea) -->
            <tr id="row-max-length" style="<?php echo in_array($currentTipe, ['text', 'textarea'], true) ? '' : 'display:none'; ?>">
                <th scope="row">
                    <label for="max_length"><?php esc_html_e('Maks. Karakter', 'jalagistrasi'); ?></label>
                </th>
                <td>
                    <input type="number" id="max_length" name="max_length" class="small-text" min="1"
                           value="<?php echo esc_attr($konfig['max_length'] ?? ($saved['max_length'] ?? '')); ?>">
                </td>
            </tr>

            <!-- Min/Max (number) -->
            <tr id="row-number-range" style="<?php echo $currentTipe === 'number' ? '' : 'display:none'; ?>">
                <th scope="row"><?php esc_html_e('Rentang Nilai', 'jalagistrasi'); ?></th>
                <td>
                    <label><?php esc_html_e('Min:', 'jalagistrasi'); ?></label>
                    <input type="number" name="min_value" class="small-text"
                           value="<?php echo esc_attr($konfig['min'] ?? ($saved['min_value'] ?? '')); ?>">
                    &nbsp;&nbsp;
                    <label><?php esc_html_e('Max:', 'jalagistrasi'); ?></label>
                    <input type="number" name="max_value" class="small-text"
                           value="<?php echo esc_attr($konfig['max'] ?? ($saved['max_value'] ?? '')); ?>">
                </td>
            </tr>

            <!-- Min/Max Date -->
            <tr id="row-date-range" style="<?php echo $currentTipe === 'date' ? '' : 'display:none'; ?>">
                <th scope="row"><?php esc_html_e('Rentang Tanggal', 'jalagistrasi'); ?></th>
                <td>
                    <label><?php esc_html_e('Min:', 'jalagistrasi'); ?></label>
                    <input type="date" name="min_date" class="regular-text"
                           value="<?php echo esc_attr($konfig['min'] ?? ($saved['min_date'] ?? '')); ?>">
                    <br><br>
                    <label><?php esc_html_e('Max:', 'jalagistrasi'); ?></label>
                    <input type="date" name="max_date" class="regular-text"
                           value="<?php echo esc_attr($konfig['max'] ?? ($saved['max_date'] ?? '')); ?>">
                </td>
            </tr>

            <!-- Max Size (file_upload) -->
            <tr id="row-file-size" style="<?php echo $currentTipe === 'file_upload' ? '' : 'display:none'; ?>">
                <th scope="row">
                    <label for="max_size_kb"><?php esc_html_e('Ukuran Maks. (KB)', 'jalagistrasi'); ?></label>
                </th>
                <td>
                    <input type="number" id="max_size_kb" name="max_size_kb" class="small-text" min="100"
                           value="<?php echo esc_attr($konfig['max_size_kb'] ?? ($saved['max_size_kb'] ?? 2048)); ?>">
                    <p class="description"><?php esc_html_e('Default: 2048 KB (2 MB). Accept: JPEG, PNG, PDF.', 'jalagistrasi'); ?></p>
                </td>
            </tr>

            <!-- Seksi -->
            <tr>
                <th scope="row">
                    <label for="section_name"><?php esc_html_e('Seksi', 'jalagistrasi'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="section_name"
                           name="section_name"
                           class="regular-text"
                           list="jg-section-suggestions"
                           placeholder="<?php esc_attr_e('Contoh: Biodata Pribadi', 'jalagistrasi'); ?>"
                           value="<?php echo esc_attr($val('section_name')); ?>">
                    <datalist id="jg-section-suggestions">
                        <?php foreach ($sectionOptions as $s) : ?>
                            <option value="<?php echo esc_attr($s); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <p class="description"><?php esc_html_e('Opsional. Nama grup untuk mengelompokkan field. Ketik atau pilih dari yang sudah ada.', 'jalagistrasi'); ?></p>
                </td>
            </tr>

            <!-- Wajib -->
            <tr>
                <th scope="row"><?php esc_html_e('Wajib Diisi', 'jalagistrasi'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="is_required" value="1"
                               <?php checked((bool) $val('is_required', (string) ($field->is_required ?? 0))); ?>>
                        <?php esc_html_e('Field ini wajib diisi oleh pendaftar', 'jalagistrasi'); ?>
                    </label>
                </td>
            </tr>

            <!-- Urutan -->
            <tr>
                <th scope="row">
                    <label for="urutan"><?php esc_html_e('Urutan', 'jalagistrasi'); ?></label>
                </th>
                <td>
                    <input type="number" id="urutan" name="urutan" class="small-text" min="0"
                           value="<?php echo esc_attr($val('urutan', '0')); ?>">
                    <p class="description"><?php esc_html_e('Urutan tampil. Bisa diubah via drag & drop dari halaman daftar.', 'jalagistrasi'); ?></p>
                </td>
            </tr>

        </table>

        <?php submit_button($isEdit ? __('Simpan Perubahan', 'jalagistrasi') : __('Tambah Field', 'jalagistrasi')); ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tipeSelect = document.getElementById('tipe');
    if (!tipeSelect) return;

    var tipiDenganOptions = ['select', 'radio', 'checkbox'];
    var rows = {
        options:     document.getElementById('row-options'),
        placeholder: document.getElementById('row-placeholder'),
        maxLength:   document.getElementById('row-max-length'),
        numberRange: document.getElementById('row-number-range'),
        dateRange:   document.getElementById('row-date-range'),
        fileSize:    document.getElementById('row-file-size'),
    };

    function updateRows(tipe) {
        var show = {
            options:     tipiDenganOptions.includes(tipe),
            placeholder: ['text','textarea'].includes(tipe),
            maxLength:   ['text','textarea'].includes(tipe),
            numberRange: tipe === 'number',
            dateRange:   tipe === 'date',
            fileSize:    tipe === 'file_upload',
        };
        Object.keys(rows).forEach(function (key) {
            if (rows[key]) rows[key].style.display = show[key] ? '' : 'none';
        });
    }

    tipeSelect.addEventListener('change', function () { updateRows(this.value); });
    updateRows(tipeSelect.value);
});
</script>
