<?php
/**
 * Form pendaftaran utama.
 *
 * @var object                     $gelombang   Gelombang yang dipilih
 * @var array<string,list<object>> $sections    Field dikelompokkan per seksi
 * @var list<object>               $prodiList   Program studi aktif
 * @var object|null                $pendaftar   Profil jg_pendaftar (nullable)
 * @var \WP_User                   $wpUser      User WordPress yang sedang login
 * @var list<string>               $errors             Pesan error validasi
 * @var array<string,mixed>        $savedData          Data POST tersimpan (untuk repopulate)
 * @var int|null                   $draftPendaftaranId ID pendaftaran draft jika ada
 * @var bool                       $draftSaved         Baru saja simpan draft
 * @var array<string,object>       $draftBerkas        tipe_berkas => berkas object (file sudah diupload ke draft)
 * @var bool                       $isEditMode         true = edit pendaftaran yang sudah disubmit, bukan draft baru
 */

defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
jg_theme_colors();

$maxPilihan    = (int) $gelombang->max_pilihan_prodi;
$dashboardUrl  = remove_query_arg(['action', 'gelombang_id'], (string) get_permalink());
$savedProdi    = is_array($savedData['prodi_pilihan'] ?? null) ? $savedData['prodi_pilihan'] : [];

// Edit pendaftaran yang sudah disubmit → tombol kembali ke halaman detail, bukan dashboard.
$backUrl = ($isEditMode && $draftPendaftaranId)
    ? add_query_arg(['action' => 'detail', 'pendaftaran_id' => $draftPendaftaranId], $dashboardUrl)
    : $dashboardUrl;

// Helper: ambil nilai tersimpan atau fallback
$val = function (string $namaField, string $default = '') use ($savedData): string {
    $v = $savedData[$namaField] ?? $default;
    return (string) $v;
};

// Mapping tipe field ke HTML input type
$htmlTypeMap = [
    'text'  => 'text',
    'email' => 'email',
    'phone' => 'tel',
    'nik'   => 'text',
    'nisn'  => 'text',
    'number' => 'number',
    'date'  => 'date',
];

// Modal/lightbox HARUS dirender di luar .jg-card (backdrop-filter pada .jg-card
// membuat containing block baru untuk descendant position:fixed di Chrome/Firefox,
// sehingga z-index modal jadi terjebak di dalam stacking context kartu itu dan
// bisa tertutup kartu lain yang muncul belakangan). Dikumpulkan di sini, dicetak
// setelah form ditutup.
$pendingModals = '';
?>
<div id="jalagistrasi-wrap">
<div class="jg-page">

    <div class="jg-topbar">
        <div class="jg-topbar-inner">
            <div class="jg-topbar-left">
                <a href="<?php echo esc_url($backUrl); ?>" class="jg-back" aria-label="<?php esc_attr_e('Kembali', 'jalagistrasi'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="jg-brand"><?php echo esc_html($gelombang->nama); ?></span>
            </div>
        </div>
    </div>

    <div class="jg-container jg-container--narrow">

        <p class="jg-card-sub" style="margin-bottom:20px;">
            <?php echo esc_html($gelombang->tahun_akademik); ?> &middot;
            <?php esc_html_e('Tutup:', 'jalagistrasi'); ?>
            <?php echo esc_html(date_i18n('d M Y', strtotime($gelombang->tanggal_tutup))); ?>
        </p>

        <!-- Draft tersimpan -->
        <?php if ($draftSaved) : ?>
            <div class="jg-notif jg-notif--success">
                ✓ <?php esc_html_e('Draft berhasil disimpan. Anda bisa melanjutkan pengisian kapan saja.', 'jalagistrasi'); ?>
            </div>
        <?php endif; ?>

        <!-- Error messages -->
        <?php if (!empty($errors)) : ?>
            <div class="jg-notif jg-notif--danger">
                <p style="font-weight:600;margin-bottom:4px;"><?php esc_html_e('Mohon perbaiki kesalahan berikut:', 'jalagistrasi'); ?></p>
                <ul style="margin:0;padding-left:18px;">
                    <?php foreach ($errors as $err) : ?>
                        <li><?php echo esc_html($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="jg-pendaftaran-form"
              method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              enctype="multipart/form-data"
              novalidate>

            <?php wp_nonce_field('jg_submit_pendaftaran'); ?>
            <input type="hidden" name="action" value="jg_submit_pendaftaran">
            <input type="hidden" name="gelombang_id" value="<?php echo (int) $gelombang->id; ?>">

            <!-- ============================================================
                 SEKSI: PILIHAN PROGRAM STUDI (selalu muncul, tidak dari form builder)
                 ============================================================ -->
            <div class="jg-card">
                <p class="jg-card-title"><?php esc_html_e('Pilihan Program Studi', 'jalagistrasi'); ?></p>
                <p class="jg-card-sub" style="margin-bottom:16px;">
                    <?php
                    printf(
                        /* translators: %d: maks pilihan */
                        esc_html__('Pilihan ke-1 wajib. Anda dapat memilih hingga %d program studi.', 'jalagistrasi'),
                        $maxPilihan
                    );
                    ?>
                </p>

                <?php for ($i = 1; $i <= $maxPilihan; $i++) : ?>
                    <?php
                    $isWajib    = $i === 1;
                    $selectedId = (int) ($savedProdi[$i] ?? 0);
                    ?>
                    <div class="jg-field">
                        <label>
                            <?php
                            printf(
                                /* translators: %d: nomor pilihan */
                                esc_html__('Pilihan ke-%d', 'jalagistrasi'),
                                $i
                            );
                            if ($isWajib) : ?>
                                <span class="req">*</span>
                            <?php endif; ?>
                        </label>
                        <select name="prodi_pilihan[<?php echo $i; ?>]" class="jg-input" <?php echo $isWajib ? 'required' : ''; ?>>
                            <option value="">— <?php echo $isWajib ? esc_html__('Pilih program studi', 'jalagistrasi') : esc_html__('Tidak memilih', 'jalagistrasi'); ?> —</option>
                            <?php foreach ($prodiList as $prodi) : ?>
                                <option value="<?php echo (int) $prodi->id; ?>"
                                    <?php selected((int) $prodi->id, $selectedId); ?>>
                                    <?php echo esc_html($prodi->nama . ' (' . $prodi->kode . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- ============================================================
                 SEKSI-SEKSI DARI FORM BUILDER
                 ============================================================ -->
            <?php foreach ($sections as $sectionName => $fields) : ?>
                <div class="jg-card">
                    <p class="jg-card-title" style="margin-bottom:16px;"><?php echo esc_html($sectionName); ?></p>

                    <?php foreach ($fields as $field) : ?>
                        <?php
                        $namaField  = $field->nama_field;
                        $label      = $field->label;
                        $tipe       = $field->tipe;
                        $isRequired = (bool) $field->is_required;
                        $konfig     = $field->konfigurasi ? (json_decode($field->konfigurasi, true) ?? []) : [];
                        $placeholder = $konfig['placeholder'] ?? '';
                        $isAutofill = in_array($namaField, ['email', 'nomor_hp'], true);

                        // Nilai awal untuk repopulate
                        if ($namaField === 'email') {
                            $prefilledVal = $wpUser->user_email;
                        } elseif ($namaField === 'nomor_hp') {
                            $prefilledVal = $pendaftar->nomor_wa ?? '';
                        } elseif ($namaField === 'nik') {
                            $prefilledVal = $val('nik', $pendaftar->nik ?? '');
                        } elseif ($namaField === 'nisn') {
                            $prefilledVal = $val('nisn', $pendaftar->nisn ?? '');
                        } elseif ($namaField === 'nama_lengkap') {
                            $prefilledVal = $val('nama_lengkap', $wpUser->display_name);
                        } else {
                            $prefilledVal = $val($namaField);
                        }

                        // Untuk repopulate field wilayah: hidden input perlu kode, tapi
                        // kotak pencarian perlu teks breadcrumb-nya, bukan kode mentah.
                        $prefilledLabel = '';
                        if ($tipe === 'wilayah_autocomplete' && $prefilledVal !== '') {
                            $wilayahRow = (new \Webane\Jalagistrasi\Repository\WilayahRepository())->findByKode($prefilledVal);
                            $prefilledLabel = $wilayahRow->nama_lengkap ?? '';
                        }
                        ?>
                        <div class="jg-field">
                            <?php if ($tipe !== 'checkbox') : ?>
                                <label for="field_<?php echo esc_attr($namaField); ?>">
                                    <?php echo esc_html($label); ?>
                                    <?php if ($isRequired) : ?><span class="req">*</span><?php endif; ?>
                                    <?php if ($isAutofill) : ?>
                                        <span style="font-size:11px;color:rgba(255,255,255,0.35);font-weight:400;margin-left:4px;">(<?php esc_html_e('terisi otomatis', 'jalagistrasi'); ?>)</span>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>

                            <?php
                            // ── Auto-fill (read-only) ──────────────────────────────
                            if ($isAutofill) :
                            ?>
                                <p class="jg-input jg-input--readonly"><?php echo esc_html($prefilledVal); ?></p>

                            <?php
                            // ── Wilayah (provinsi/kabupaten/kecamatan/desa) autocomplete ──
                            elseif ($tipe === 'wilayah_autocomplete') :
                            ?>
                                <div class="jg-wilayah"
                                     x-data="{
                                        kode: '<?php echo esc_js($prefilledVal); ?>',
                                        query: '<?php echo esc_js($prefilledLabel); ?>',
                                        terkunci: <?php echo $prefilledVal !== '' ? 'true' : 'false'; ?>,
                                        hasil: [],
                                        loading: false,
                                        cari() {
                                            this.kode = '';
                                            if (this.query.trim().length < 3) { this.hasil = []; return; }
                                            this.loading = true;
                                            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=jg_search_wilayah&q=' + encodeURIComponent(this.query) + '&_wpnonce=<?php echo esc_js(wp_create_nonce('jg_search_wilayah')); ?>')
                                                .then(r => r.json())
                                                .then(res => { this.hasil = (res && res.success) ? res.data : []; })
                                                .finally(() => { this.loading = false; });
                                        },
                                        pilih(item) {
                                            this.kode = item.kode;
                                            this.query = item.label;
                                            this.hasil = [];
                                            this.terkunci = true;
                                        },
                                        ubah() {
                                            this.terkunci = false;
                                            this.kode = '';
                                            this.query = '';
                                        }
                                     }">
                                    <input type="hidden" name="<?php echo esc_attr($namaField); ?>" :value="kode">
                                    <template x-if="!terkunci">
                                        <div style="position:relative;">
                                            <input type="text" class="jg-input" x-model="query"
                                                   @input.debounce.400ms="cari()" autocomplete="off"
                                                   placeholder="<?php esc_attr_e('Ketik nama desa/kelurahan...', 'jalagistrasi'); ?>"
                                                   <?php echo $isRequired ? 'required' : ''; ?>>
                                            <div class="jg-wilayah-suggestions" x-show="hasil.length > 0" x-cloak>
                                                <template x-for="item in hasil" :key="item.kode">
                                                    <button type="button" class="jg-wilayah-suggestion" @click="pilih(item)" x-text="item.label"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="terkunci">
                                        <div class="jg-wilayah-chosen">
                                            <span x-text="query"></span>
                                            <button type="button" class="jg-link" @click="ubah()"><?php esc_html_e('Ganti', 'jalagistrasi'); ?></button>
                                        </div>
                                    </template>
                                </div>
                                <p class="jg-field-hint"><?php esc_html_e('Cukup ketik nama desa/kelurahan, provinsi & kabupaten/kota akan ikut tersimpan otomatis.', 'jalagistrasi'); ?></p>

                            <?php
                            // ── Textarea ───────────────────────────────────────────
                            elseif ($tipe === 'textarea') :
                                $maxLength = (int) ($konfig['max_length'] ?? 0);
                            ?>
                                <textarea id="field_<?php echo esc_attr($namaField); ?>"
                                          name="<?php echo esc_attr($namaField); ?>"
                                          rows="4"
                                          class="jg-input"
                                          placeholder="<?php echo esc_attr($placeholder); ?>"
                                          <?php echo $isRequired ? 'required' : ''; ?>
                                          <?php echo $maxLength > 0 ? 'maxlength="' . $maxLength . '"' : ''; ?>
                                ><?php echo esc_textarea($prefilledVal); ?></textarea>

                            <?php
                            // ── Select ─────────────────────────────────────────────
                            elseif ($tipe === 'select') :
                                $options = $konfig['options'] ?? [];
                            ?>
                                <select id="field_<?php echo esc_attr($namaField); ?>"
                                        name="<?php echo esc_attr($namaField); ?>"
                                        class="jg-input"
                                        <?php echo $isRequired ? 'required' : ''; ?>>
                                    <option value="">— <?php esc_html_e('Pilih', 'jalagistrasi'); ?> —</option>
                                    <?php foreach ($options as $opt) : ?>
                                        <option value="<?php echo esc_attr($opt); ?>"
                                            <?php selected($opt, $prefilledVal); ?>>
                                            <?php echo esc_html($opt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php
                            // ── Radio ──────────────────────────────────────────────
                            elseif ($tipe === 'radio') :
                                $options = $konfig['options'] ?? [];
                            ?>
                                <?php foreach ($options as $opt) : ?>
                                    <label class="jg-radio-row">
                                        <input type="radio"
                                               name="<?php echo esc_attr($namaField); ?>"
                                               value="<?php echo esc_attr($opt); ?>"
                                               <?php echo $isRequired ? 'required' : ''; ?>
                                               <?php checked($opt, $prefilledVal); ?>>
                                        <?php echo esc_html($opt); ?>
                                    </label>
                                <?php endforeach; ?>

                            <?php
                            // ── Checkbox ───────────────────────────────────────────
                            elseif ($tipe === 'checkbox') :
                                $options      = $konfig['options'] ?? [];
                                $checkedVals  = is_array($savedData[$namaField] ?? null)
                                    ? $savedData[$namaField]
                                    : [];
                            ?>
                                <fieldset style="border:0;padding:0;margin:0;">
                                    <legend style="font-size:13px;font-weight:500;color:rgba(255,255,255,0.85);margin-bottom:6px;padding:0;">
                                        <?php echo esc_html($label); ?>
                                        <?php if ($isRequired) : ?><span class="req">*</span><?php endif; ?>
                                    </legend>
                                    <?php foreach ($options as $opt) : ?>
                                        <label class="jg-checkbox-row">
                                            <input type="checkbox"
                                                   name="<?php echo esc_attr($namaField); ?>[]"
                                                   value="<?php echo esc_attr($opt); ?>"
                                                   <?php echo in_array($opt, $checkedVals, true) ? 'checked' : ''; ?>>
                                            <?php echo esc_html($opt); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>

                            <?php
                            // ── File upload ────────────────────────────────────────
                            elseif ($tipe === 'file_upload') :
                                $maxKb          = (int) ($konfig['max_size_kb'] ?? 2048);
                                $existingBerkas = $draftBerkas[$namaField] ?? null;
                                $isImage        = $existingBerkas && in_array($existingBerkas->mime_type, ['image/jpeg', 'image/png'], true);
                                $previewUrl     = $existingBerkas
                                    ? esc_url(add_query_arg([
                                        'action'    => 'jg_preview_berkas',
                                        'berkas_id' => $existingBerkas->id,
                                        '_wpnonce'  => wp_create_nonce('jg_preview_berkas_' . $existingBerkas->id),
                                    ], admin_url('admin-ajax.php')))
                                    : '';
                                $modalId = 'jg-preview-' . esc_attr($namaField);
                            ?>
                                <?php if ($existingBerkas) : ?>
                                    <?php if ($isImage) : ?>
                                        <!-- Thumbnail yang bisa diklik -->
                                        <div class="jg-file-thumb"
                                             role="button" tabindex="0"
                                             onclick="document.getElementById('<?php echo $modalId; ?>').classList.remove('jg-hidden')"
                                             onkeydown="if(event.key==='Enter'||event.key===' ')document.getElementById('<?php echo $modalId; ?>').classList.remove('jg-hidden')">
                                            <img src="<?php echo $previewUrl; ?>"
                                                 alt="<?php echo esc_attr($existingBerkas->file_name_original); ?>"
                                                 loading="lazy">
                                            <div class="jg-file-thumb-overlay">
                                                <span><?php esc_html_e('Lihat foto', 'jalagistrasi'); ?></span>
                                            </div>
                                        </div>
                                        <!-- Modal lightbox — dikumpulkan, dirender di luar .jg-card (lihat $pendingModals) -->
                                        <?php
                                        ob_start();
                                        ?>
                                        <div id="<?php echo $modalId; ?>"
                                             class="jg-lightbox jg-hidden"
                                             onclick="if(event.target===this)this.classList.add('jg-hidden')">
                                            <div class="jg-lightbox-inner">
                                                <button type="button"
                                                        onclick="document.getElementById('<?php echo $modalId; ?>').classList.add('jg-hidden')"
                                                        class="jg-lightbox-close">✕</button>
                                                <img src="<?php echo $previewUrl; ?>"
                                                     alt="<?php echo esc_attr($existingBerkas->file_name_original); ?>">
                                                <p class="jg-lightbox-caption">
                                                    <?php echo esc_html($existingBerkas->file_name_original); ?>
                                                    &middot;
                                                    <?php echo esc_html(number_format((int) $existingBerkas->file_size / 1024, 0)); ?> KB
                                                </p>
                                            </div>
                                        </div>
                                        <?php
                                        $pendingModals .= ob_get_clean();
                                        ?>
                                    <?php else : ?>
                                        <!-- PDF / non-image -->
                                        <a href="<?php echo $previewUrl; ?>" target="_blank" rel="noopener" class="jg-file-doc">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                            <span class="jg-file-doc-name"><?php echo esc_html($existingBerkas->file_name_original); ?></span>
                                            <span class="jg-file-doc-size"><?php echo esc_html(number_format((int) $existingBerkas->file_size / 1024, 0)); ?> KB</span>
                                        </a>
                                    <?php endif; ?>
                                    <p class="jg-field-hint"><?php esc_html_e('Upload file baru untuk mengganti.', 'jalagistrasi'); ?></p>
                                <?php endif; ?>
                                <input type="file"
                                       id="field_<?php echo esc_attr($namaField); ?>"
                                       name="<?php echo esc_attr($namaField); ?>"
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       <?php echo ($isRequired && !$existingBerkas) ? 'required' : ''; ?>
                                       class="jg-file-input">
                                <p class="jg-field-hint">
                                    <?php
                                    printf(
                                        /* translators: %s: ukuran maks */
                                        esc_html__('JPEG, PNG, atau PDF. Maks %s KB.', 'jalagistrasi'),
                                        number_format($maxKb)
                                    );
                                    ?>
                                </p>

                            <?php
                            // ── Number ─────────────────────────────────────────────
                            elseif ($tipe === 'number') :
                                $min = $konfig['min'] ?? '';
                                $max = $konfig['max'] ?? '';
                            ?>
                                <input type="number"
                                       id="field_<?php echo esc_attr($namaField); ?>"
                                       name="<?php echo esc_attr($namaField); ?>"
                                       value="<?php echo esc_attr($prefilledVal); ?>"
                                       class="jg-input"
                                       placeholder="<?php echo esc_attr($placeholder); ?>"
                                       <?php echo $isRequired ? 'required' : ''; ?>
                                       <?php echo $min !== '' ? 'min="' . esc_attr((string) $min) . '"' : ''; ?>
                                       <?php echo $max !== '' ? 'max="' . esc_attr((string) $max) . '"' : ''; ?>>

                            <?php
                            // ── Date ───────────────────────────────────────────────
                            elseif ($tipe === 'date') :
                                $min = $konfig['min'] ?? '';
                                $max = $konfig['max'] ?? '';
                            ?>
                                <input type="date"
                                       id="field_<?php echo esc_attr($namaField); ?>"
                                       name="<?php echo esc_attr($namaField); ?>"
                                       value="<?php echo esc_attr($prefilledVal); ?>"
                                       class="jg-input"
                                       <?php echo $isRequired ? 'required' : ''; ?>
                                       <?php echo $min !== '' ? 'min="' . esc_attr((string) $min) . '"' : ''; ?>
                                       <?php echo $max !== '' ? 'max="' . esc_attr((string) $max) . '"' : ''; ?>>

                            <?php
                            // ── Text / phone / nik / nisn / email-tipe (default) ──
                            else :
                                $htmlType  = $htmlTypeMap[$tipe] ?? 'text';
                                $maxLength = (int) ($konfig['max_length'] ?? 0);
                            ?>
                                <input type="<?php echo esc_attr($htmlType); ?>"
                                       id="field_<?php echo esc_attr($namaField); ?>"
                                       name="<?php echo esc_attr($namaField); ?>"
                                       value="<?php echo esc_attr($prefilledVal); ?>"
                                       class="jg-input"
                                       placeholder="<?php echo esc_attr($placeholder); ?>"
                                       <?php echo $isRequired ? 'required' : ''; ?>
                                       <?php echo $maxLength > 0 ? 'maxlength="' . $maxLength . '"' : ''; ?>>

                            <?php endif; ?>

                        </div><!-- end .jg-field -->
                    <?php endforeach; ?>

                </div><!-- end section card -->
            <?php endforeach; ?>

            <!-- Tombol aksi -->
            <div class="jg-card">
                <?php if ($isEditMode) : ?>
                    <p class="jg-field-hint" style="margin-bottom:16px;">
                        <?php esc_html_e('Pastikan semua data sudah benar sebelum menyimpan perubahan.', 'jalagistrasi'); ?>
                    </p>
                    <button type="submit" name="jg_action" value="submit" class="jg-btn jg-btn--block">
                        <?php esc_html_e('Simpan Perubahan', 'jalagistrasi'); ?>
                    </button>
                <?php else : ?>
                    <p class="jg-field-hint" style="margin-bottom:16px;">
                        <?php esc_html_e('Pastikan semua data sudah benar sebelum mengirim. Anda masih bisa mengedit formulir ini sampai dokumen Anda diverifikasi panitia.', 'jalagistrasi'); ?>
                    </p>
                    <button type="submit" name="jg_action" value="submit" class="jg-btn jg-btn--block" style="margin-bottom:10px;">
                        <?php esc_html_e('Kirim Pendaftaran', 'jalagistrasi'); ?>
                    </button>
                    <button type="submit" id="jg-btn-draft" class="jg-btn jg-btn--outline jg-btn--block">
                        <?php esc_html_e('Simpan Draft', 'jalagistrasi'); ?>
                    </button>
                    <script>
                    document.getElementById('jg-btn-draft').addEventListener('click', function () {
                        var actionInput = document.querySelector('#jg-pendaftaran-form input[name="action"]');
                        if (actionInput) {
                            actionInput.value = 'jg_save_draft_pendaftaran';
                        }
                        // Draft tidak wajib upload file — hapus required agar HTML5 validation
                        // tidak memblokir submit ketika file belum dipilih.
                        document.querySelectorAll('#jg-pendaftaran-form input[type="file"]').forEach(function (el) {
                            el.removeAttribute('required');
                        });
                    });
                    </script>
                <?php endif; ?>
            </div>

        </form>

        <?php echo $pendingModals; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML sudah di-escape per-field saat dibuat di atas ?>

    </div>
</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-wilayah-suggestions {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 30;
    max-height: 220px; overflow-y: auto;
    background: rgba(20, 24, 34, 0.97); border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px; box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4); padding: 6px;
}
#jalagistrasi-wrap .jg-wilayah-suggestion {
    display: block; width: 100%; text-align: left; padding: 8px 10px; border-radius: 8px;
    background: transparent; border: 0; color: rgba(255, 255, 255, 0.8); font-size: 13px; cursor: pointer;
}
#jalagistrasi-wrap .jg-wilayah-suggestion:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
#jalagistrasi-wrap .jg-wilayah-chosen {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    background: rgba(255, 255, 255, 0.07); border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px; padding: 11px 14px; font-size: 13px; color: #fff;
}

#jalagistrasi-wrap .jg-input--readonly {
    margin: 0;
    color: rgba(255, 255, 255, 0.5);
    cursor: not-allowed;
}

#jalagistrasi-wrap .jg-file-input {
    display: block;
    width: 100%;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.6);
}
#jalagistrasi-wrap .jg-file-input::file-selector-button {
    margin-right: 12px;
    padding: 7px 14px;
    border-radius: 9px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    cursor: pointer;
    transition: background-color .15s;
}
#jalagistrasi-wrap .jg-file-input::file-selector-button:hover { background: rgba(255, 255, 255, 0.14); }

#jalagistrasi-wrap .jg-file-thumb {
    position: relative;
    height: 200px;
    margin-bottom: 8px;
    overflow: hidden;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    cursor: pointer;
}
#jalagistrasi-wrap .jg-file-thumb img {
    position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain;
}
#jalagistrasi-wrap .jg-file-thumb-overlay {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0, 0, 0, 0); transition: background-color .15s;
    opacity: 0;
}
#jalagistrasi-wrap .jg-file-thumb:hover .jg-file-thumb-overlay { background: rgba(0, 0, 0, 0.35); opacity: 1; }
#jalagistrasi-wrap .jg-file-thumb-overlay span {
    padding: 5px 14px; border-radius: 9999px; background: rgba(255, 255, 255, 0.9);
    color: #111; font-size: 12px; font-weight: 600;
}

#jalagistrasi-wrap .jg-file-doc {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; margin-bottom: 8px;
    border-radius: 12px; text-decoration: none;
    background: rgba(<?php echo esc_html(jg_theme_colors()['brandRgb']); ?>, 0.12);
    border: 1px solid rgba(<?php echo esc_html(jg_theme_colors()['brandRgb']); ?>, 0.3);
    color: #93c5fd;
}
#jalagistrasi-wrap .jg-file-doc-name { font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#jalagistrasi-wrap .jg-file-doc-size { margin-left: auto; flex-shrink: 0; font-size: 11px; opacity: 0.8; }

#jalagistrasi-wrap .jg-lightbox {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 16px; background: rgba(0, 0, 0, 0.8);
}
#jalagistrasi-wrap .jg-lightbox.jg-hidden { display: none; }
#jalagistrasi-wrap .jg-lightbox-inner { position: relative; max-width: 540px; width: 100%; }
#jalagistrasi-wrap .jg-lightbox-inner img { width: 100%; border-radius: 14px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5); }
#jalagistrasi-wrap .jg-lightbox-caption { margin: 10px 0 0; text-align: center; font-size: 12px; color: rgba(255, 255, 255, 0.6); }
#jalagistrasi-wrap .jg-lightbox-close {
    position: absolute; top: -14px; right: -14px;
    width: 30px; height: 30px; border-radius: 9999px; border: 0;
    background: #fff; color: #111; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
}
</style>
