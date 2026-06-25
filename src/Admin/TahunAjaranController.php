<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Repository\TahunAjaranRepository;

/**
 * CRUD Tahun Ajaran. Lihat docs/arsitektur-tahun-ajaran.md.
 */
final class TahunAjaranController
{
    private TahunAjaranRepository $repo;

    public function __construct()
    {
        $this->repo = new TahunAjaranRepository();
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses ke halaman ini.', 'jalagistrasi'), 403);
        }

        $action = sanitize_key($_GET['action'] ?? 'list');
        $id     = (int) ($_GET['id'] ?? 0);

        if ($action === 'edit' && $id > 0) {
            $tahunAjaran = $this->repo->findById($id);
            if ($tahunAjaran === null) {
                wp_die(esc_html__('Tahun ajaran tidak ditemukan.', 'jalagistrasi'));
            }
            $this->renderForm($tahunAjaran);
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
        check_admin_referer('jg_tahun_ajaran_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $id     = (int) ($_POST['tahun_ajaran_id'] ?? 0);
        $errors = $this->validateInput($_POST, $id);

        $redirectBase = admin_url('admin.php?page=jg-tahun-ajaran');

        if (!empty($errors)) {
            set_transient('jg_tahun_ajaran_errors_' . get_current_user_id(), $errors, 60);
            set_transient('jg_tahun_ajaran_data_' . get_current_user_id(), $_POST, 60);
            $back = $id > 0
                ? add_query_arg(['action' => 'edit', 'id' => $id], $redirectBase)
                : add_query_arg(['action' => 'add'], $redirectBase);
            wp_safe_redirect($back);
            exit;
        }

        $data = [
            'nama'   => trim(sanitize_text_field($_POST['nama'])),
            'status' => sanitize_key($_POST['status']),
        ];

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
        $id = (int) ($_POST['tahun_ajaran_id'] ?? 0);

        check_admin_referer('jg_delete_tahun_ajaran_' . $id);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $redirectBase = admin_url('admin.php?page=jg-tahun-ajaran');

        if ($this->repo->countGelombang($id) > 0) {
            wp_safe_redirect(add_query_arg('jg_message', 'delete_blocked', $redirectBase));
            exit;
        }

        $ok = $this->repo->delete($id);
        wp_safe_redirect(add_query_arg('jg_message', $ok ? 'deleted' : 'error', $redirectBase));
        exit;
    }

    private function renderList(): void
    {
        $tahunAjaranList = $this->repo->findAll();
        $message         = sanitize_key($_GET['jg_message'] ?? '');

        $templateVars = compact('tahunAjaranList', 'message');
        $this->loadTemplate('tahun-ajaran/list', $templateVars);
    }

    private function renderForm(?object $tahunAjaran): void
    {
        $userId = get_current_user_id();
        $errors = get_transient('jg_tahun_ajaran_errors_' . $userId) ?: [];
        $saved  = get_transient('jg_tahun_ajaran_data_' . $userId) ?: [];
        delete_transient('jg_tahun_ajaran_errors_' . $userId);
        delete_transient('jg_tahun_ajaran_data_' . $userId);

        $templateVars = compact('tahunAjaran', 'errors', 'saved');
        $this->loadTemplate('tahun-ajaran/form', $templateVars);
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
            $errors[] = __('Nama tahun ajaran wajib diisi.', 'jalagistrasi');
        } elseif (!preg_match('/^\d{4}\/\d{4}$/', $nama)) {
            $errors[] = __('Format tahun ajaran harus YYYY/YYYY, contoh: 2026/2027.', 'jalagistrasi');
        } elseif ($this->repo->findByNama($nama, $editId) !== null) {
            $errors[] = __('Tahun ajaran ini sudah ada.', 'jalagistrasi');
        }

        $status = sanitize_key($post['status'] ?? '');
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $errors[] = __('Status tidak valid.', 'jalagistrasi');
        }

        return $errors;
    }
}
