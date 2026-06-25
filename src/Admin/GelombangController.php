<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\TahunAjaranRepository;
use Webane\Jalagistrasi\Service\DefaultFormTemplate;
use Webane\Jalagistrasi\Service\DefaultTipeBerkasSeeder;

class GelombangController
{
    private GelombangRepository $repo;
    private TahunAjaranRepository $tahunAjaranRepo;

    public function __construct()
    {
        $this->repo = new GelombangRepository();
        $this->tahunAjaranRepo = new TahunAjaranRepository();
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options') && !current_user_can('jg_manage_gelombang')) {
            wp_die(esc_html__('Anda tidak punya akses ke halaman ini.', 'jalagistrasi'), 403);
        }

        $action = sanitize_key($_GET['action'] ?? 'list');
        $id     = (int) ($_GET['id'] ?? 0);

        if ($action === 'edit' && $id > 0) {
            $gelombang = $this->repo->findById($id);
            if ($gelombang === null) {
                wp_die(esc_html__('Gelombang tidak ditemukan.', 'jalagistrasi'));
            }
            $this->renderForm($gelombang);
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
        check_admin_referer('jg_gelombang_nonce');

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_gelombang')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $id     = (int) ($_POST['gelombang_id'] ?? 0);
        $errors = $this->validateInput($_POST);

        $redirectBase = admin_url('admin.php?page=jg-gelombang');

        if (!empty($errors)) {
            set_transient('jg_form_errors_' . get_current_user_id(), $errors, 60);
            set_transient('jg_form_data_' . get_current_user_id(), $_POST, 60);
            $back = $id > 0
                ? add_query_arg(['action' => 'edit', 'id' => $id], $redirectBase)
                : add_query_arg(['action' => 'add'], $redirectBase);
            wp_safe_redirect($back);
            exit;
        }

        $data = [
            'nama'              => sanitize_text_field($_POST['nama']),
            'tahun_ajaran_id'   => (int) $_POST['tahun_ajaran_id'],
            'tanggal_buka'      => sanitize_text_field($_POST['tanggal_buka']),
            'tanggal_tutup'     => sanitize_text_field($_POST['tanggal_tutup']),
            'max_pilihan_prodi' => (int) $_POST['max_pilihan_prodi'],
            'biaya_pendaftaran' => (float) ($_POST['biaya_pendaftaran'] ?? 0),
            'status'            => sanitize_key($_POST['status']),
            'created_by'        => get_current_user_id(),
        ];

        // Konversi datetime-local (YYYY-MM-DDTHH:MM) ke MySQL DATETIME (YYYY-MM-DD HH:MM:SS)
        $data['tanggal_buka']  = str_replace('T', ' ', $data['tanggal_buka']) . ':00';
        $data['tanggal_tutup'] = str_replace('T', ' ', $data['tanggal_tutup']) . ':00';

        if ($id > 0) {
            $ok      = $this->repo->update($id, $data);
            $message = $ok ? 'updated' : 'error';
        } else {
            $newId = $this->repo->insert($data);
            if ($newId !== false) {
                (new DefaultFormTemplate())->seedForGelombang($newId);
                (new DefaultTipeBerkasSeeder())->ensureDefault($newId);
                $message = 'created';
            } else {
                $message = 'error';
            }
        }

        wp_safe_redirect(add_query_arg('jg_message', $message, $redirectBase));
        exit;
    }

    public function handleDelete(): void
    {
        $id = (int) ($_POST['gelombang_id'] ?? 0);

        check_admin_referer('jg_delete_gelombang_' . $id);

        if (!current_user_can('manage_options') && !current_user_can('jg_manage_gelombang')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $redirectBase = admin_url('admin.php?page=jg-gelombang');

        if ($this->repo->countPendaftaran($id) > 0) {
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
        $gelombangList = $this->repo->findAll($statusFilter);
        $message       = sanitize_key($_GET['jg_message'] ?? '');

        $templateVars = compact('gelombangList', 'message', 'statusFilter');
        $this->loadTemplate('gelombang/list', $templateVars);
    }

    private function renderForm(?object $gelombang): void
    {
        $userId = get_current_user_id();
        $errors = get_transient('jg_form_errors_' . $userId) ?: [];
        $saved  = get_transient('jg_form_data_' . $userId) ?: [];
        delete_transient('jg_form_errors_' . $userId);
        delete_transient('jg_form_data_' . $userId);

        $tahunAjaranList = $this->tahunAjaranRepo->findAll();

        $templateVars = compact('gelombang', 'errors', 'saved', 'tahunAjaranList');
        $this->loadTemplate('gelombang/form', $templateVars);
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
    private function validateInput(array $post): array
    {
        $errors = [];

        $nama = trim(sanitize_text_field($post['nama'] ?? ''));
        if ($nama === '') {
            $errors[] = __('Nama gelombang wajib diisi.', 'jalagistrasi');
        }

        $tahunAjaranId = (int) ($post['tahun_ajaran_id'] ?? 0);
        if ($tahunAjaranId <= 0) {
            $errors[] = __('Tahun ajaran wajib dipilih.', 'jalagistrasi');
        } elseif ($this->tahunAjaranRepo->findById($tahunAjaranId) === null) {
            $errors[] = __('Tahun ajaran tidak valid.', 'jalagistrasi');
        }

        $buka  = trim(sanitize_text_field($post['tanggal_buka'] ?? ''));
        $tutup = trim(sanitize_text_field($post['tanggal_tutup'] ?? ''));

        if ($buka === '') {
            $errors[] = __('Tanggal buka wajib diisi.', 'jalagistrasi');
        }

        if ($tutup === '') {
            $errors[] = __('Tanggal tutup wajib diisi.', 'jalagistrasi');
        }

        if ($buka !== '' && $tutup !== '' && $tutup <= $buka) {
            $errors[] = __('Tanggal tutup harus setelah tanggal buka.', 'jalagistrasi');
        }

        $maxPilihan = (int) ($post['max_pilihan_prodi'] ?? 0);
        if ($maxPilihan < 1 || $maxPilihan > 10) {
            $errors[] = __('Maksimal pilihan prodi harus antara 1 dan 10.', 'jalagistrasi');
        }

        $biaya = (float) ($post['biaya_pendaftaran'] ?? 0);
        if ($biaya < 0) {
            $errors[] = __('Biaya pendaftaran tidak boleh negatif.', 'jalagistrasi');
        }

        $status = sanitize_key($post['status'] ?? '');
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $errors[] = __('Status tidak valid.', 'jalagistrasi');
        }

        return $errors;
    }
}
