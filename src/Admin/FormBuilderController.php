<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Enum\TipeField;
use Webane\Jalagistrasi\Repository\FormSchemaRepository;
use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Service\DefaultFormTemplate;

class FormBuilderController
{
    private FormSchemaRepository $repo;
    private GelombangRepository  $gelombangRepo;

    public function __construct()
    {
        $this->repo          = new FormSchemaRepository();
        $this->gelombangRepo = new GelombangRepository();
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options') && !current_user_can('jg_manage_form_builder')) {
            wp_die(esc_html__('Anda tidak punya akses ke halaman ini.', 'jalagistrasi'), 403);
        }

        $action      = sanitize_key($_GET['action'] ?? 'list');
        $gelombangId = (int) ($_GET['gelombang_id'] ?? 0);
        $fieldId     = (int) ($_GET['field_id'] ?? 0);

        if (in_array($action, ['add', 'edit'], true) && $gelombangId > 0) {
            $field     = $fieldId > 0 ? $this->repo->findById($fieldId) : null;
            $gelombang = $this->gelombangRepo->findById($gelombangId);

            if ($gelombangId > 0 && $gelombang === null) {
                wp_die(esc_html__('Gelombang tidak ditemukan.', 'jalagistrasi'));
            }

            if ($action === 'edit' && $field === null) {
                wp_die(esc_html__('Field tidak ditemukan.', 'jalagistrasi'));
            }

            $this->renderForm($gelombang, $field);
            return;
        }

        $this->renderList($gelombangId);
    }

    public function handleSave(): void
    {
        check_admin_referer('jg_form_field_nonce');

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_form_builder')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $gelombangId = (int) ($_POST['gelombang_id'] ?? 0);
        $fieldId     = (int) ($_POST['field_id'] ?? 0);
        $errors      = $this->validateInput($_POST, $gelombangId, $fieldId);

        $redirectBase = add_query_arg('gelombang_id', $gelombangId, admin_url('admin.php?page=jg-form-builder'));

        if (!empty($errors)) {
            set_transient('jg_field_errors_' . get_current_user_id(), $errors, 60);
            set_transient('jg_field_data_' . get_current_user_id(), $_POST, 60);
            $back = $fieldId > 0
                ? add_query_arg(['action' => 'edit', 'field_id' => $fieldId], $redirectBase)
                : add_query_arg(['action' => 'add'], $redirectBase);
            wp_safe_redirect($back);
            exit;
        }

        $tipe         = sanitize_key($_POST['tipe']);
        $konfigurasi  = $this->buildKonfigurasi($tipe, $_POST);

        $data = [
            'gelombang_id' => $gelombangId,
            'section_name' => sanitize_text_field($_POST['section_name'] ?? '') ?: null,
            'nama_field'   => sanitize_key($_POST['nama_field']),
            'label'        => sanitize_text_field($_POST['label']),
            'tipe'         => $tipe,
            'is_required'  => isset($_POST['is_required']) ? 1 : 0,
            'urutan'       => (int) ($_POST['urutan'] ?? 0),
            'konfigurasi'  => $konfigurasi,
        ];

        if ($fieldId > 0) {
            $existing = $this->repo->findById($fieldId);
            // is_core tidak bisa diubah via form
            if ($existing && (int) $existing->is_core === 1) {
                unset($data['nama_field'], $data['tipe']);
                $data['nama_field'] = $existing->nama_field;
                $data['tipe']       = $existing->tipe;
            }
            $ok      = $this->repo->update($fieldId, $data);
            $message = $ok ? 'updated' : 'error';
        } else {
            $data['is_core'] = 0;
            $newId           = $this->repo->insert($data);
            $message         = $newId !== false ? 'created' : 'error';
        }

        wp_safe_redirect(add_query_arg('jg_message', $message, $redirectBase));
        exit;
    }

    public function handleDelete(): void
    {
        $fieldId = (int) ($_POST['field_id'] ?? 0);

        check_admin_referer('jg_delete_field_' . $fieldId);

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_form_builder')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $field        = $this->repo->findById($fieldId);
        $gelombangId  = $field ? (int) $field->gelombang_id : 0;
        $redirectBase = add_query_arg('gelombang_id', $gelombangId, admin_url('admin.php?page=jg-form-builder'));

        if ($field && (int) $field->is_core === 1) {
            wp_safe_redirect(add_query_arg('jg_message', 'delete_core', $redirectBase));
            exit;
        }

        $ok = $this->repo->delete($fieldId);
        wp_safe_redirect(add_query_arg('jg_message', $ok ? 'deleted' : 'error', $redirectBase));
        exit;
    }

    public function handleReorder(): void
    {
        check_ajax_referer('jg_reorder_fields');

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_form_builder')) {
            wp_send_json_error(['message' => 'Akses ditolak'], 403);
        }

        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            wp_send_json_error(['message' => 'Data tidak valid']);
        }

        $urutanMap = [];
        foreach ($order as $index => $fieldId) {
            $urutanMap[(int) $fieldId] = $index + 1;
        }

        $this->repo->updateUrutan($urutanMap);
        wp_send_json_success(['message' => 'Urutan disimpan']);
    }

    public function handleSeedDefault(): void
    {
        $gelombangId = (int) ($_POST['gelombang_id'] ?? 0);

        check_admin_referer('jg_seed_default_form_' . $gelombangId);

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_form_builder')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $gelombang    = $this->gelombangRepo->findById($gelombangId);
        $redirectBase = add_query_arg('gelombang_id', $gelombangId, admin_url('admin.php?page=jg-form-builder'));

        if (!$gelombang) {
            wp_safe_redirect(add_query_arg('jg_message', 'error', $redirectBase));
            exit;
        }

        (new DefaultFormTemplate())->seedForGelombang($gelombangId);

        wp_safe_redirect(add_query_arg('jg_message', 'seeded', $redirectBase));
        exit;
    }

    private function renderList(int $gelombangId): void
    {
        $gelombangList = $this->gelombangRepo->findAll();
        $gelombang     = $gelombangId > 0 ? $this->gelombangRepo->findById($gelombangId) : null;
        $fields        = $gelombangId > 0 ? $this->repo->findByGelombang($gelombangId) : [];
        $message       = sanitize_key($_GET['jg_message'] ?? '');

        // Kelompokkan field per seksi
        $sections = [];
        foreach ($fields as $f) {
            $sectionKey              = $f->section_name ?? __('Tanpa Seksi', 'jalagistrasi');
            $sections[$sectionKey][] = $f;
        }

        $vars = compact('gelombangList', 'gelombang', 'gelombangId', 'sections', 'message');
        $this->loadTemplate('form-builder/index', $vars);
    }

    private function renderForm(?object $gelombang, ?object $field): void
    {
        $userId = get_current_user_id();
        $errors = get_transient('jg_field_errors_' . $userId) ?: [];
        $saved  = get_transient('jg_field_data_' . $userId) ?: [];
        delete_transient('jg_field_errors_' . $userId);
        delete_transient('jg_field_data_' . $userId);

        // Upload file kini selalu lewat Tipe Berkas (Step 3), bukan field formulir dinamis.
        $tipeOptions    = array_values(array_filter(
            TipeField::cases(),
            static fn (TipeField $t) => $t !== TipeField::FileUpload
        ));
        $sectionOptions = $this->getSectionOptions($gelombang ? (int) $gelombang->id : 0);

        $vars = compact('gelombang', 'field', 'errors', 'saved', 'tipeOptions', 'sectionOptions');
        $this->loadTemplate('form-builder/field-form', $vars);
    }

    /** @return list<string> */
    private function getSectionOptions(int $gelombangId): array
    {
        if ($gelombangId === 0) {
            return [];
        }

        $fields   = $this->repo->findByGelombang($gelombangId);
        $sections = [];
        foreach ($fields as $f) {
            if ($f->section_name && !in_array($f->section_name, $sections, true)) {
                $sections[] = $f->section_name;
            }
        }

        return $sections;
    }

    /** @param array<string,mixed> $vars */
    private function loadTemplate(string $name, array $vars = []): void
    {
        $file = JG_PLUGIN_DIR . 'templates/admin/' . $name . '.php';

        if (!file_exists($file)) {
            wp_die(esc_html__('Template tidak ditemukan: ', 'jalagistrasi') . esc_html($name));
        }

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($vars, EXTR_SKIP);
        include $file;
    }

    /** @param array<string,mixed> $post */
    private function validateInput(array $post, int $gelombangId, int $editId = 0): array
    {
        $errors = [];

        if ($gelombangId <= 0) {
            $errors[] = __('Gelombang tidak valid.', 'jalagistrasi');
            return $errors;
        }

        $namaField = sanitize_key($post['nama_field'] ?? '');
        if ($namaField === '') {
            $errors[] = __('Nama field wajib diisi (huruf kecil, angka, underscore).', 'jalagistrasi');
        } elseif ($this->repo->existsNamaField($gelombangId, $namaField, $editId)) {
            $errors[] = __('Nama field sudah digunakan di gelombang ini.', 'jalagistrasi');
        }

        $label = trim(sanitize_text_field($post['label'] ?? ''));
        if ($label === '') {
            $errors[] = __('Label field wajib diisi.', 'jalagistrasi');
        }

        $tipe = sanitize_key($post['tipe'] ?? '');
        if (!in_array($tipe, array_column(TipeField::cases(), 'value'), true)) {
            $errors[] = __('Tipe field tidak valid.', 'jalagistrasi');
        }

        if (in_array($tipe, ['select', 'radio', 'checkbox'], true)) {
            $options = array_filter(array_map(
                'sanitize_text_field',
                explode("\n", $post['options_text'] ?? '')
            ));
            if (empty($options)) {
                $errors[] = __('Tipe ' . $tipe . ' membutuhkan minimal 1 pilihan.', 'jalagistrasi');
            }
        }

        return $errors;
    }

    /**
     * Bangun array konfigurasi dari POST data berdasarkan tipe field.
     *
     * @param  array<string,mixed> $post
     * @return array<string,mixed>|null
     */
    private function buildKonfigurasi(string $tipe, array $post): ?array
    {
        if (in_array($tipe, ['select', 'radio', 'checkbox'], true)) {
            $options = array_values(array_filter(array_map(
                'sanitize_text_field',
                explode("\n", $post['options_text'] ?? '')
            )));
            return ['options' => $options];
        }

        if ($tipe === 'file_upload') {
            return [
                'accept'      => ['image/jpeg', 'image/png', 'application/pdf'],
                'max_size_kb' => (int) ($post['max_size_kb'] ?? 2048),
            ];
        }

        if ($tipe === 'text' || $tipe === 'textarea') {
            $cfg = [];
            if (!empty($post['placeholder'])) {
                $cfg['placeholder'] = sanitize_text_field($post['placeholder']);
            }
            if (!empty($post['max_length'])) {
                $cfg['max_length'] = (int) $post['max_length'];
            }
            return !empty($cfg) ? $cfg : null;
        }

        if ($tipe === 'number') {
            $cfg = [];
            if (isset($post['min_value']) && $post['min_value'] !== '') {
                $cfg['min'] = (int) $post['min_value'];
            }
            if (isset($post['max_value']) && $post['max_value'] !== '') {
                $cfg['max'] = (int) $post['max_value'];
            }
            return !empty($cfg) ? $cfg : null;
        }

        if ($tipe === 'date') {
            $cfg = [];
            if (!empty($post['min_date'])) {
                $cfg['min'] = sanitize_text_field($post['min_date']);
            }
            if (!empty($post['max_date'])) {
                $cfg['max'] = sanitize_text_field($post['max_date']);
            }
            return !empty($cfg) ? $cfg : null;
        }

        return null;
    }
}
