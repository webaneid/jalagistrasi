<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Admin;

use Webane\Jalagistrasi\Repository\RekeningBankRepository;

final class RekeningBankController
{
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Anda tidak punya akses.', 'jalagistrasi'), 403);
        }

        $repo = new RekeningBankRepository();

        $items = $repo->findAll();

        $editId   = (int) ($_GET['edit'] ?? 0);
        $editItem = $editId > 0 ? $repo->findById($editId) : null;

        $saved   = sanitize_key($_GET['saved'] ?? '');
        $deleted = sanitize_key($_GET['deleted'] ?? '');
        $errors  = get_transient('jg_rekening_bank_errors');
        $old     = get_transient('jg_rekening_bank_old');
        delete_transient('jg_rekening_bank_errors');
        delete_transient('jg_rekening_bank_old');

        $path = JG_PLUGIN_DIR . 'templates/admin/rekening-bank/index.php';
        extract([
            'items'    => $items,
            'editItem' => $editItem,
            'saved'    => $saved,
            'deleted'  => $deleted,
            'errors'   => is_array($errors) ? $errors : [],
            'old'      => is_array($old) ? $old : [],
        ], EXTR_SKIP);
        include $path;
    }

    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Tidak punya akses.', 'jalagistrasi'), 403);
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'jg_save_rekening_bank')) {
            wp_die(esc_html__('Nonce tidak valid.', 'jalagistrasi'), 403);
        }

        $id            = (int) ($_POST['rekening_bank_id'] ?? 0);
        $namaBank      = sanitize_text_field(wp_unslash($_POST['nama_bank'] ?? ''));
        $nomorRekening = sanitize_text_field(wp_unslash($_POST['nomor_rekening'] ?? ''));
        $namaPemilik   = sanitize_text_field(wp_unslash($_POST['nama_pemilik'] ?? ''));
        $isAktif       = isset($_POST['is_aktif']) ? 1 : 0;
        $urutan        = (int) ($_POST['urutan'] ?? 0);

        $errors = [];
        if ($namaBank === '') {
            $errors[] = __('Nama bank wajib diisi.', 'jalagistrasi');
        }
        if ($nomorRekening === '') {
            $errors[] = __('Nomor rekening wajib diisi.', 'jalagistrasi');
        }
        if ($namaPemilik === '') {
            $errors[] = __('Nama pemilik rekening wajib diisi.', 'jalagistrasi');
        }

        $backUrl = admin_url('admin.php?page=jg-rekening-bank');

        if (!empty($errors)) {
            set_transient('jg_rekening_bank_errors', $errors, 60);
            set_transient('jg_rekening_bank_old', [
                'nama_bank'      => $namaBank,
                'nomor_rekening' => $nomorRekening,
                'nama_pemilik'   => $namaPemilik,
                'is_aktif'       => $isAktif,
                'urutan'         => $urutan,
            ], 60);
            wp_safe_redirect($id > 0 ? add_query_arg('edit', $id, $backUrl) : $backUrl);
            exit;
        }

        $data = [
            'nama_bank'      => $namaBank,
            'nomor_rekening' => $nomorRekening,
            'nama_pemilik'   => $namaPemilik,
            'is_aktif'       => $isAktif,
            'urutan'         => $urutan,
        ];

        $repo = new RekeningBankRepository();
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

        $id    = (int) ($_GET['id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if ($id <= 0 || !wp_verify_nonce($nonce, 'jg_delete_rekening_bank_' . $id)) {
            wp_die(esc_html__('Permintaan tidak valid.', 'jalagistrasi'), 403);
        }

        (new RekeningBankRepository())->delete($id);

        wp_safe_redirect(add_query_arg('deleted', '1', admin_url('admin.php?page=jg-rekening-bank')));
        exit;
    }
}
