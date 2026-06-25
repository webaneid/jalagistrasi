<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Repository\ProgramStudiRepository;

class ProgramStudiController
{
    private ProgramStudiRepository $repo;

    public function __construct()
    {
        $this->repo = new ProgramStudiRepository();
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options') && !current_user_can('jg_manage_program_studi')) {
            wp_die(esc_html__('Anda tidak punya akses ke halaman ini.', 'jalagistrasi'), 403);
        }

        $action = sanitize_key($_GET['action'] ?? 'list');
        $id     = (int) ($_GET['id'] ?? 0);

        if ($action === 'edit' && $id > 0) {
            $prodi = $this->repo->findById($id);
            if ($prodi === null) {
                wp_die(esc_html__('Program studi tidak ditemukan.', 'jalagistrasi'));
            }
            $this->renderForm($prodi);
            return;
        }

        if ($action === 'add') {
            $this->renderForm(null);
            return;
        }

        $this->renderList();
    }

    public function handleSave(): void
    {
        check_admin_referer('jg_program_studi_nonce');

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_program_studi')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $id     = (int) ($_POST['prodi_id'] ?? 0);
        $errors = $this->validateInput($_POST, $id);

        $redirectBase = admin_url('admin.php?page=jg-program-studi');

        if (!empty($errors)) {
            set_transient('jg_prodi_errors_' . get_current_user_id(), $errors, 60);
            set_transient('jg_prodi_data_' . get_current_user_id(), $_POST, 60);
            $back = $id > 0
                ? add_query_arg(['action' => 'edit', 'id' => $id], $redirectBase)
                : add_query_arg(['action' => 'add'], $redirectBase);
            wp_safe_redirect($back);
            exit;
        }

        $data = [
            'nama'      => sanitize_text_field($_POST['nama']),
            'kode'      => strtoupper(sanitize_text_field($_POST['kode'])),
            'deskripsi' => sanitize_textarea_field($_POST['deskripsi'] ?? ''),
            'kuota'     => (int) $_POST['kuota'],
            'urutan'    => (int) $_POST['urutan'],
            'status'    => sanitize_key($_POST['status']),
        ];

        if ($data['deskripsi'] === '') {
            $data['deskripsi'] = null;
        }

        if ($id > 0) {
            $ok      = $this->repo->update($id, $data);
            $message = $ok ? 'updated' : 'error';
        } else {
            $newId   = $this->repo->insert($data);
            $message = $newId !== false ? 'created' : 'error';
        }

        wp_safe_redirect(add_query_arg('jg_message', $message, $redirectBase));
        exit;
    }

    public function handleDelete(): void
    {
        $id = (int) ($_POST['prodi_id'] ?? 0);

        check_admin_referer('jg_delete_prodi_' . $id);

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_program_studi')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $redirectBase = admin_url('admin.php?page=jg-program-studi');

        if ($this->repo->countPilihan($id) > 0) {
            wp_safe_redirect(add_query_arg('jg_message', 'delete_blocked', $redirectBase));
            exit;
        }

        $ok = $this->repo->delete($id);
        wp_safe_redirect(add_query_arg('jg_message', $ok ? 'deleted' : 'error', $redirectBase));
        exit;
    }

    private function renderList(): void
    {
        $statusFilter = sanitize_key($_GET['status_filter'] ?? '');
        $prodiList    = $this->repo->findAll($statusFilter);
        $message      = sanitize_key($_GET['jg_message'] ?? '');

        $templateVars = compact('prodiList', 'message', 'statusFilter');
        $this->loadTemplate('program-studi/list', $templateVars);
    }

    private function renderForm(?object $prodi): void
    {
        $userId = get_current_user_id();
        $errors = get_transient('jg_prodi_errors_' . $userId) ?: [];
        $saved  = get_transient('jg_prodi_data_' . $userId) ?: [];
        delete_transient('jg_prodi_errors_' . $userId);
        delete_transient('jg_prodi_data_' . $userId);

        $templateVars = compact('prodi', 'errors', 'saved');
        $this->loadTemplate('program-studi/form', $templateVars);
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
    private function validateInput(array $post, int $editId = 0): array
    {
        $errors = [];

        $nama = trim(sanitize_text_field($post['nama'] ?? ''));
        if ($nama === '') {
            $errors[] = __('Nama program studi wajib diisi.', 'jalagistrasi');
        }

        $kode = trim(strtoupper(sanitize_text_field($post['kode'] ?? '')));
        if ($kode === '') {
            $errors[] = __('Kode program studi wajib diisi.', 'jalagistrasi');
        } elseif (!preg_match('/^[A-Z0-9\-]{1,20}$/', $kode)) {
            $errors[] = __('Kode hanya boleh berisi huruf kapital, angka, dan tanda hubung (maks. 20 karakter).', 'jalagistrasi');
        } elseif ($this->repo->findByKode($kode, $editId) !== null) {
            $errors[] = __('Kode program studi sudah digunakan.', 'jalagistrasi');
        }

        $kuota = (int) ($post['kuota'] ?? -1);
        if ($kuota < 0) {
            $errors[] = __('Kuota tidak valid (minimal 0).', 'jalagistrasi');
        }

        $urutan = (int) ($post['urutan'] ?? -1);
        if ($urutan < 0) {
            $errors[] = __('Urutan tidak valid (minimal 0).', 'jalagistrasi');
        }

        $status = sanitize_key($post['status'] ?? '');
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $errors[] = __('Status tidak valid.', 'jalagistrasi');
        }

        return $errors;
    }
}
