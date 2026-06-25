<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\TipeBerkasRepository;
use Webane\Jalagistrasi\Service\DefaultTipeBerkasSeeder;

final class TipeBerkasController
{
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $gelombangRepo  = new GelombangRepository();
        $tipeBerkasRepo = new TipeBerkasRepository();

        $gelombangList = $gelombangRepo->findAll();
        $gelombangId   = (int) ($_GET['gelombang_id'] ?? ($gelombangList[0]->id ?? 0));

        if ($gelombangId > 0) {
            (new DefaultTipeBerkasSeeder())->ensureDefault($gelombangId);
        }

        $items   = $gelombangId > 0 ? $tipeBerkasRepo->findByGelombang($gelombangId) : [];
        $gelombang = $gelombangId > 0 ? $gelombangRepo->findById($gelombangId) : null;

        // Edit mode
        $editId   = (int) ($_GET['edit'] ?? 0);
        $editItem = $editId > 0 ? $tipeBerkasRepo->findById($editId) : null;

        $saved   = sanitize_key($_GET['saved'] ?? '');
        $deleted = sanitize_key($_GET['deleted'] ?? '');
        $errors  = get_transient('jg_tipe_berkas_errors');
        $old     = get_transient('jg_tipe_berkas_old');
        delete_transient('jg_tipe_berkas_errors');
        delete_transient('jg_tipe_berkas_old');

        $path = JG_PLUGIN_DIR . 'templates/admin/tipe-berkas/index.php';
        extract([
            'gelombangList' => $gelombangList,
            'gelombangId'   => $gelombangId,
            'gelombang'     => $gelombang,
            'items'         => $items,
            'editItem'      => $editItem,
            'saved'         => $saved,
            'deleted'       => $deleted,
            'errors'        => is_array($errors) ? $errors : [],
            'old'           => is_array($old) ? $old : [],
        ], EXTR_SKIP);
        include $path;
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_save_tipe_berkas')) {
            wp_die(esc_html__('Nonce tidak valid.', 'jalagistrasi'), 403);
        }

        $id          = (int) ($_POST['tipe_berkas_id'] ?? 0);
        $gelombangId = (int) ($_POST['gelombang_id'] ?? 0);
        $kode        = sanitize_key(wp_unslash($_POST['kode'] ?? ''));
        $label       = sanitize_text_field(wp_unslash($_POST['label'] ?? ''));
        $keterangan  = sanitize_textarea_field(wp_unslash($_POST['keterangan'] ?? ''));
        $isRequired  = isset($_POST['is_required']) ? 1 : 0;
        $maxSizeKb   = max(100, (int) ($_POST['max_size_kb'] ?? 2048));
        $urutan      = (int) ($_POST['urutan'] ?? 0);

        $errors = [];
        if ($gelombangId <= 0) {
            $errors[] = __('Gelombang tidak valid.', 'jalagistrasi');
        }
        if ($kode === '') {
            $errors[] = __('Kode berkas wajib diisi.', 'jalagistrasi');
        }
        if ($label === '') {
            $errors[] = __('Label berkas wajib diisi.', 'jalagistrasi');
        }

        $repo = new TipeBerkasRepository();
        if (empty($errors) && $repo->kodeExists($gelombangId, $kode, $id)) {
            $errors[] = __('Kode berkas sudah digunakan pada gelombang ini.', 'jalagistrasi');
        }

        $backUrl = add_query_arg(
            ['page' => 'jg-tipe-berkas', 'gelombang_id' => $gelombangId],
            admin_url('admin.php')
        );

        if (!empty($errors)) {
            set_transient('jg_tipe_berkas_errors', $errors, 60);
            set_transient('jg_tipe_berkas_old', [
                'kode'        => $kode,
                'label'       => $label,
                'keterangan'  => $keterangan,
                'is_required' => $isRequired,
                'max_size_kb' => $maxSizeKb,
                'urutan'      => $urutan,
            ], 60);
            wp_safe_redirect($id > 0 ? add_query_arg('edit', $id, $backUrl) : $backUrl);
            exit;
        }

        $data = [
            'gelombang_id' => $gelombangId,
            'kode'         => $kode,
            'label'        => $label,
            'keterangan'   => $keterangan ?: null,
            'is_required'  => $isRequired,
            'max_size_kb'  => $maxSizeKb,
            'urutan'       => $urutan,
        ];

        if ($id > 0) {
            $repo->update($id, $data);
        } else {
            $repo->insert($data);
        }

        wp_safe_redirect(add_query_arg('saved', '1', $backUrl));
        exit;
    }

    public function handleDelete(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce       = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        $id          = (int) ($_GET['id'] ?? 0);
        $gelombangId = (int) ($_GET['gelombang_id'] ?? 0);

        if ($id <= 0 || !wp_verify_nonce($nonce, 'jg_delete_tipe_berkas_' . $id)) {
            wp_die(esc_html__('Permintaan tidak valid.', 'jalagistrasi'), 403);
        }

        (new TipeBerkasRepository())->delete($id);

        $backUrl = add_query_arg(
            ['page' => 'jg-tipe-berkas', 'gelombang_id' => $gelombangId, 'deleted' => '1'],
            admin_url('admin.php')
        );
        wp_safe_redirect($backUrl);
        exit;
    }
}
