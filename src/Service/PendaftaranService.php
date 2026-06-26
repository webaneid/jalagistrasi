<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Webane\Jalagistrasi\Enum\StatusPendaftaran;
use Webane\Jalagistrasi\Repository\BerkasRepository;
use Webane\Jalagistrasi\Repository\FormJawabanRepository;
use Webane\Jalagistrasi\Repository\FormSchemaRepository;
use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\PendaftaranProdiRepository;
use Webane\Jalagistrasi\Repository\PendaftaranRepository;
use Webane\Jalagistrasi\Repository\PendaftarRepository;
use Webane\Jalagistrasi\Repository\ProgramStudiRepository;
use Webane\Jalagistrasi\Repository\StatusHistoryRepository;
use Webane\Jalagistrasi\Repository\WilayahRepository;

/**
 * Orkestra proses submit formulir pendaftaran.
 *
 * Urutan operasi:
 *   1. Validasi gelombang (aktif, dalam periode)
 *   2. Cek duplikat pendaftaran
 *   3. Validasi pilihan prodi
 *   4. Validasi field formulir + file
 *   5. Insert jg_pendaftaran (draft)
 *   6. Upload file → jg_berkas
 *   7. Insert jg_form_jawaban
 *   8. Insert jg_pendaftaran_prodi
 *   9. Sync NIK/NISN ke jg_pendaftar
 *  10. Generate nomor + update status ke submitted + catat audit trail
 */
final class PendaftaranService
{
    public function __construct(
        private readonly PendaftaranRepository     $pendaftaranRepo,
        private readonly FormJawabanRepository     $jawabanRepo,
        private readonly PendaftaranProdiRepository $prodiRepo,
        private readonly BerkasRepository          $berkasRepo,
        private readonly FormSchemaRepository      $formSchemaRepo,
        private readonly GelombangRepository       $gelombangRepo,
        private readonly ProgramStudiRepository    $prodiStudiRepo,
        private readonly PendaftarRepository       $pendaftarRepo,
        private readonly FileUploadService         $fileService,
        private readonly NomorPendaftaranService   $nomorService,
    ) {}

    /**
     * Submit formulir pendaftaran.
     *
     * @param array<string,mixed> $postData  Sanitized $_POST
     * @param array<string,mixed> $filesData $_FILES
     * @return array{success:bool,errors?:list<string>,nomor?:string,pendaftaran_id?:int}
     */
    public function submit(int $userId, int $gelombangId, array $postData, array $filesData): array
    {
        $errors = [];

        // --- 1. Validasi gelombang ---
        $gelombang = $this->gelombangRepo->findById($gelombangId);
        if (!$gelombang) {
            return ['success' => false, 'errors' => [__('Gelombang tidak ditemukan.', 'jalagistrasi')]];
        }
        if ($gelombang->status !== 'aktif') {
            return ['success' => false, 'errors' => [__('Gelombang pendaftaran ini tidak aktif.', 'jalagistrasi')]];
        }

        $now       = current_time('mysql');
        $bukaTgl   = $gelombang->tanggal_buka;
        $tutupTgl  = $gelombang->tanggal_tutup;

        if ($now < $bukaTgl || $now > $tutupTgl) {
            return ['success' => false, 'errors' => [__('Pendaftaran belum dibuka atau sudah ditutup.', 'jalagistrasi')]];
        }

        // --- 2. Cek duplikat / ambil draft yang ada / cek edit pendaftaran existing ---
        // Lihat StatusPendaftaran::isEditable() & docs/arsitektur-frontend-pendaftaran.md #13.
        $existing = $this->pendaftaranRepo->findByUserGelombang($userId, $gelombangId);
        $isEditingSubmitted = false;

        if ($existing && $existing->status !== StatusPendaftaran::Draft->value) {
            if (!StatusPendaftaran::from($existing->status)->isEditable()) {
                return ['success' => false, 'errors' => [__('Pendaftaran ini sudah tidak bisa diedit lagi.', 'jalagistrasi')]];
            }
            $isEditingSubmitted = true;
        }

        $existingDraftId = $existing ? (int) $existing->id : null;

        // --- 3. Validasi pilihan prodi ---
        $maxPilihan   = (int) $gelombang->max_pilihan_prodi;
        $prodiRaw     = is_array($postData['prodi_pilihan'] ?? null)
            ? $postData['prodi_pilihan']
            : [];

        $prodiErrors  = $this->validateProdi($prodiRaw, $maxPilihan);
        $errors       = array_merge($errors, $prodiErrors);

        // --- 4. Validasi field formulir ---
        $fields    = $this->formSchemaRepo->findByGelombang($gelombangId);
        $pendaftar = $this->pendaftarRepo->findByUserId($userId);
        $wpUser    = get_userdata($userId);

        // Berkas yang sudah tersimpan dari draft — diizinkan menggantikan upload baru
        $existingBerkasMap = [];
        if ($existingDraftId !== null) {
            foreach ($this->berkasRepo->findByPendaftaran($existingDraftId) as $b) {
                $existingBerkasMap[$b->tipe_berkas] = $b;
            }
        }

        $fieldErrors = $this->validateFields($fields, $postData, $filesData, $pendaftar, $wpUser, $existingBerkasMap);
        $errors      = array_merge($errors, $fieldErrors);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // --- 5. Buat atau pakai draft yang sudah ada ---
        if ($existingDraftId !== null) {
            $pendaftaranId = $existingDraftId;
            // Hapus jawaban & prodi lama agar bisa ditulis ulang
            $this->prodiRepo->deleteByPendaftaran($pendaftaranId);
        } else {
            $pendaftaranId = $this->pendaftaranRepo->insert([
                'user_id'           => $userId,
                'gelombang_id'      => $gelombangId,
                'nomor_pendaftaran' => 'DRAFT-' . $userId . '-' . time(),
                'status'            => StatusPendaftaran::Draft->value,
            ]);

            if (!$pendaftaranId) {
                return ['success' => false, 'errors' => [__('Gagal menyimpan pendaftaran. Silakan coba lagi.', 'jalagistrasi')]];
            }
        }

        // --- 6 & 7. Upload file + build jawaban map ---
        $jawabanMap = [];
        $uploadErrors = [];

        foreach ($fields as $field) {
            $namaField = $field->nama_field;
            $fieldId   = (int) $field->id;
            $tipe      = $field->tipe;
            $konfig    = $field->konfigurasi ? (json_decode($field->konfigurasi, true) ?? []) : [];

            if ($tipe === 'file_upload') {
                $fileData = $filesData[$namaField] ?? null;

                if ($fileData && $fileData['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $fileInfo = $this->fileService->store($fileData, $pendaftaranId, $namaField);
                        $this->berkasRepo->deleteByPendaftaranAndTipe($pendaftaranId, $namaField);
                        $berkasId = $this->berkasRepo->insert([
                            'pendaftaran_id'     => $pendaftaranId,
                            'tipe_berkas'        => $namaField,
                            'file_path'          => $fileInfo['file_path'],
                            'file_name_original' => $fileInfo['file_name_original'],
                            'file_name_stored'   => $fileInfo['file_name_stored'],
                            'file_size'          => $fileInfo['file_size'],
                            'mime_type'          => $fileInfo['mime_type'],
                            'status'             => 'pending',
                        ]);

                        if ($berkasId) {
                            $jawabanMap[$fieldId] = ['text' => (string) $berkasId, 'json' => null];
                        }
                    } catch (\RuntimeException $e) {
                        $uploadErrors[] = $e->getMessage();
                    }
                }
                continue;
            }

            // Auto-fill untuk field inti
            if ($namaField === 'email' && $wpUser) {
                $jawabanMap[$fieldId] = ['text' => $wpUser->user_email, 'json' => null];
                continue;
            }

            if ($namaField === 'nomor_hp' && $pendaftar) {
                $jawabanMap[$fieldId] = ['text' => $pendaftar->nomor_wa, 'json' => null];
                continue;
            }

            // Checkbox → json
            if ($tipe === 'checkbox') {
                $val = is_array($postData[$namaField] ?? null)
                    ? array_map('sanitize_text_field', $postData[$namaField])
                    : [];
                $jawabanMap[$fieldId] = ['text' => '', 'json' => $val];
                continue;
            }

            // Semua tipe lain → text
            $jawabanMap[$fieldId] = [
                'text' => sanitize_text_field((string) ($postData[$namaField] ?? '')),
                'json' => null,
            ];
        }

        if (!empty($uploadErrors)) {
            return ['success' => false, 'errors' => $uploadErrors];
        }

        // --- 7. Insert jawaban ---
        $this->jawabanRepo->bulkInsert($pendaftaranId, $jawabanMap);

        // --- 8. Insert prodi (hanya yang terisi) ---
        $prodiTersimpan = [];
        foreach ($prodiRaw as $urutan => $prodiId) {
            $pid = (int) $prodiId;
            if ($pid > 0) {
                $prodiTersimpan[(int) $urutan] = $pid;
            }
        }
        $this->prodiRepo->insertAll($pendaftaranId, $prodiTersimpan);

        // --- 9. Sync NIK / NISN ---
        $this->pendaftarRepo->updateNikNisn($userId, [
            'nik'  => sanitize_text_field((string) ($postData['nik'] ?? '')),
            'nisn' => sanitize_text_field((string) ($postData['nisn'] ?? '')),
        ]);

        // --- 10. Generate nomor + update status (HANYA untuk submit pertama kali) ---
        if ($isEditingSubmitted) {
            // Edit formulir biodata pada pendaftaran yang sudah disubmit — nomor &
            // status TIDAK berubah (bukan pendaftaran baru, dan status dokumen/
            // pembayaran adalah keputusan terpisah dari isi biodata).
            $nomor = (string) $existing->nomor_pendaftaran;

            (new StatusHistoryRepository())->log(
                $pendaftaranId,
                $existing->status,
                $existing->status,
                $userId,
                __('Formulir biodata diedit oleh pendaftar.', 'jalagistrasi')
            );
        } else {
            $nomor = $this->nomorService->generate($gelombangId, $gelombang->tahun_akademik);
            $this->pendaftaranRepo->updateNomor($pendaftaranId, $nomor);
            // Token rahasia untuk QR/URL verifikasi (/verifikasi/<nomor>/<token>/) —
            // dibuat sekali di sini, tidak pernah berubah lagi. Lihat
            // docs/arsitektur-verifikasi-qr.md.
            $this->pendaftaranRepo->updateVerifikasiToken($pendaftaranId, bin2hex(random_bytes(16)));
            $this->pendaftaranRepo->updateStatus($pendaftaranId, StatusPendaftaran::Submitted->value, current_time('mysql'));

            (new StatusHistoryRepository())->log(
                $pendaftaranId,
                StatusPendaftaran::Draft->value,
                StatusPendaftaran::Submitted->value,
                $userId
            );
        }

        return [
            'success'         => true,
            'nomor'           => $nomor,
            'pendaftaran_id'  => $pendaftaranId,
            'isEdit'          => $isEditingSubmitted,
        ];
    }

    /**
     * Simpan atau perbarui draft pendaftaran tanpa validasi wajib.
     * File diupload jika disertakan; berkas lama diganti bila ada.
     *
     * @param array<string,mixed> $postData  Sanitized $_POST
     * @param array<string,mixed> $filesData $_FILES
     * @return array{success:bool,errors?:list<string>,pendaftaran_id?:int}
     */
    public function saveDraft(int $userId, int $gelombangId, array $postData, array $filesData = []): array
    {
        $gelombang = $this->gelombangRepo->findById($gelombangId);
        if (!$gelombang || $gelombang->status !== 'aktif') {
            return ['success' => false, 'errors' => [__('Gelombang tidak valid.', 'jalagistrasi')]];
        }

        $existing = $this->pendaftaranRepo->findByUserGelombang($userId, $gelombangId);

        // Hanya boleh simpan draft jika belum ada atau masih draft
        if ($existing && $existing->status !== StatusPendaftaran::Draft->value) {
            return ['success' => false, 'errors' => [__('Pendaftaran ini sudah dikirim dan tidak bisa diedit.', 'jalagistrasi')]];
        }

        if ($existing) {
            $pendaftaranId = (int) $existing->id;
            $this->prodiRepo->deleteByPendaftaran($pendaftaranId);
        } else {
            $pendaftaranId = $this->pendaftaranRepo->insert([
                'user_id'           => $userId,
                'gelombang_id'      => $gelombangId,
                'nomor_pendaftaran' => 'DRAFT-' . $userId . '-' . time(),
                'status'            => StatusPendaftaran::Draft->value,
            ]);

            if (!$pendaftaranId) {
                return ['success' => false, 'errors' => [__('Gagal menyimpan draft.', 'jalagistrasi')]];
            }
        }

        // Simpan jawaban teks (skip file_upload — diproses saat submit final)
        $fields    = $this->formSchemaRepo->findByGelombang($gelombangId);
        $pendaftar = $this->pendaftarRepo->findByUserId($userId);
        $wpUser    = get_userdata($userId);
        $jawabanMap = [];

        foreach ($fields as $field) {
            $namaField = $field->nama_field;
            $fieldId   = (int) $field->id;
            $tipe      = $field->tipe;

            if ($tipe === 'file_upload') {
                $fileData = $filesData[$namaField] ?? null;
                if ($fileData && $fileData['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $fileInfo = $this->fileService->store($fileData, $pendaftaranId, $namaField);
                        $this->berkasRepo->deleteByPendaftaranAndTipe($pendaftaranId, $namaField);
                        $berkasId = $this->berkasRepo->insert([
                            'pendaftaran_id'     => $pendaftaranId,
                            'tipe_berkas'        => $namaField,
                            'file_path'          => $fileInfo['file_path'],
                            'file_name_original' => $fileInfo['file_name_original'],
                            'file_name_stored'   => $fileInfo['file_name_stored'],
                            'file_size'          => $fileInfo['file_size'],
                            'mime_type'          => $fileInfo['mime_type'],
                            'status'             => 'pending',
                        ]);
                        if ($berkasId) {
                            $jawabanMap[$fieldId] = ['text' => (string) $berkasId, 'json' => null];
                        }
                    } catch (\RuntimeException $e) {
                        // Non-fatal: file gagal diupload, draft teks tetap tersimpan
                    }
                }
                continue;
            }

            if ($namaField === 'email' && $wpUser) {
                $jawabanMap[$fieldId] = ['text' => $wpUser->user_email, 'json' => null];
                continue;
            }

            if ($namaField === 'nomor_hp' && $pendaftar) {
                $jawabanMap[$fieldId] = ['text' => $pendaftar->nomor_wa, 'json' => null];
                continue;
            }

            if ($tipe === 'checkbox') {
                $val = is_array($postData[$namaField] ?? null)
                    ? array_map('sanitize_text_field', $postData[$namaField])
                    : [];
                $jawabanMap[$fieldId] = ['text' => '', 'json' => $val];
                continue;
            }

            $jawabanMap[$fieldId] = [
                'text' => sanitize_text_field((string) ($postData[$namaField] ?? '')),
                'json' => null,
            ];
        }

        $this->jawabanRepo->bulkInsert($pendaftaranId, $jawabanMap);

        // Simpan pilihan prodi (hanya yang terisi)
        $prodiRaw     = is_array($postData['prodi_pilihan'] ?? null) ? $postData['prodi_pilihan'] : [];
        $prodiTersimpan = [];
        foreach ($prodiRaw as $urutan => $prodiId) {
            $pid = (int) $prodiId;
            if ($pid > 0) {
                $prodiTersimpan[(int) $urutan] = $pid;
            }
        }
        if (!empty($prodiTersimpan)) {
            $this->prodiRepo->insertAll($pendaftaranId, $prodiTersimpan);
        }

        return ['success' => true, 'pendaftaran_id' => $pendaftaranId];
    }

    /**
     * @param array<int|string,mixed> $prodiRaw
     * @return list<string>
     */
    private function validateProdi(array $prodiRaw, int $maxPilihan): array
    {
        $errors = [];

        $pilihan1 = (int) ($prodiRaw[1] ?? 0);
        if ($pilihan1 <= 0) {
            $errors[] = __('Pilihan Program Studi ke-1 wajib dipilih.', 'jalagistrasi');
            return $errors;
        }

        $seen = [];
        for ($i = 1; $i <= $maxPilihan; $i++) {
            $prodiId = (int) ($prodiRaw[$i] ?? 0);

            if ($prodiId === 0) {
                continue; // pilihan 2+ opsional
            }

            // Cek duplikat
            if (in_array($prodiId, $seen, true)) {
                $errors[] = sprintf(
                    __('Pilihan prodi ke-%d duplikat dengan pilihan sebelumnya.', 'jalagistrasi'),
                    $i
                );
                continue;
            }

            // Cek exist & aktif
            $prodi = $this->prodiStudiRepo->findById($prodiId);
            if (!$prodi || $prodi->status !== 'aktif') {
                $errors[] = sprintf(
                    __('Pilihan prodi ke-%d tidak valid atau tidak aktif.', 'jalagistrasi'),
                    $i
                );
                continue;
            }

            $seen[] = $prodiId;
        }

        return $errors;
    }

    /**
     * @param list<object>        $fields
     * @param array<string,mixed> $postData
     * @param array<string,mixed> $filesData
     * @param array<string,object> $existingBerkasMap  tipe_berkas => berkas object (dari draft)
     * @return list<string>
     */
    private function validateFields(
        array $fields,
        array $postData,
        array $filesData,
        ?object $pendaftar,
        \WP_User|false $wpUser,
        array $existingBerkasMap = []
    ): array {
        $errors = [];

        foreach ($fields as $field) {
            $namaField  = $field->nama_field;
            $label      = $field->label;
            $tipe       = $field->tipe;
            $isRequired = (bool) $field->is_required;
            $konfig     = $field->konfigurasi ? (json_decode($field->konfigurasi, true) ?? []) : [];

            // Auto-fill: email & nomor_hp tidak perlu divalidasi dari POST
            if ($namaField === 'email' || $namaField === 'nomor_hp') {
                continue;
            }

            if ($tipe === 'file_upload') {
                $fileData     = $filesData[$namaField] ?? null;
                $hasFile      = $fileData && $fileData['error'] !== UPLOAD_ERR_NO_FILE;
                $hasDraftFile = isset($existingBerkasMap[$namaField]);

                if ($isRequired && !$hasFile && !$hasDraftFile) {
                    $errors[] = sprintf(__('%s wajib diupload.', 'jalagistrasi'), $label);
                    continue;
                }

                if ($hasFile) {
                    $maxKb      = (int) ($konfig['max_size_kb'] ?? 2048);
                    $fileErrors = $this->fileService->validate($fileData, $maxKb, $label);
                    $errors     = array_merge($errors, $fileErrors);
                }

                continue;
            }

            if ($tipe === 'checkbox') {
                $val = is_array($postData[$namaField] ?? null) ? $postData[$namaField] : [];
                if ($isRequired && empty($val)) {
                    $errors[] = sprintf(__('%s wajib dipilih.', 'jalagistrasi'), $label);
                }
                continue;
            }

            if ($tipe === 'wilayah_autocomplete') {
                $val = trim((string) ($postData[$namaField] ?? ''));

                if ($val === '') {
                    if ($isRequired) {
                        $errors[] = sprintf(__('%s wajib diisi.', 'jalagistrasi'), $label);
                    }
                    continue;
                }

                // Kode wajib benar-benar ada di data wilayah — mencegah nilai
                // sembarangan yang dikirim manual lewat devtools (hidden input
                // ini diisi otomatis oleh JS hasil pilih dari autocomplete).
                if ((new WilayahRepository())->findByKode($val) === null) {
                    $errors[] = sprintf(__('%s tidak valid. Pilih dari saran pencarian.', 'jalagistrasi'), $label);
                }
                continue;
            }

            $val = trim((string) ($postData[$namaField] ?? ''));
            if ($isRequired && $val === '') {
                $errors[] = sprintf(__('%s wajib diisi.', 'jalagistrasi'), $label);
            }
        }

        return $errors;
    }
}
