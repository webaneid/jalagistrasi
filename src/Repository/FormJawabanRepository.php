<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class FormJawabanRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_form_jawaban';
    }

    /**
     * @return list<object>
     */
    public function findByPendaftaran(int $pendaftaranId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE pendaftaran_id = %d",
                $pendaftaranId
            )
        );

        return $rows ?: [];
    }

    /**
     * Ambil semua jawaban untuk banyak pendaftaran sekaligus — dipakai export Excel.
     *
     * @param list<int> $pendaftaranIds
     * @return list<object>
     */
    public function findByPendaftaranIds(array $pendaftaranIds): array
    {
        global $wpdb;

        if (empty($pendaftaranIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pendaftaranIds), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE pendaftaran_id IN ({$placeholders})",
                ...$pendaftaranIds
            )
        );

        return $rows ?: [];
    }

    /**
     * Simpan satu jawaban. Gunakan INSERT ... ON DUPLICATE KEY UPDATE
     * agar aman dipanggil berulang (upsert).
     */
    public function upsert(int $pendaftaranId, int $fieldId, string $nilaiText = '', ?array $nilaiJson = null): bool
    {
        global $wpdb;

        $nilaiJsonEncoded = $nilaiJson !== null ? wp_json_encode($nilaiJson) : null;

        // NULL harus ditulis sebagai literal SQL NULL, bukan '' (empty string tidak valid untuk kolom JSON).
        if ($nilaiJsonEncoded !== null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$this->table}
                 (pendaftaran_id, field_id, nilai_text, nilai_json)
                 VALUES (%d, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE
                   nilai_text = VALUES(nilai_text),
                   nilai_json = VALUES(nilai_json),
                   updated_at = CURRENT_TIMESTAMP",
                $pendaftaranId,
                $fieldId,
                $nilaiText,
                $nilaiJsonEncoded
            );
        } else {
            $sql = $wpdb->prepare(
                "INSERT INTO {$this->table}
                 (pendaftaran_id, field_id, nilai_text, nilai_json)
                 VALUES (%d, %d, %s, NULL)
                 ON DUPLICATE KEY UPDATE
                   nilai_text = VALUES(nilai_text),
                   nilai_json = NULL,
                   updated_at = CURRENT_TIMESTAMP",
                $pendaftaranId,
                $fieldId,
                $nilaiText
            );
        }

        return $wpdb->query($sql) !== false;
    }

    /**
     * Insert semua jawaban dalam satu batch. Dipanggil saat submit form.
     *
     * @param array<int,array{text:string,json:array<mixed>|null}> $jawabanMap key = field_id
     */
    public function bulkInsert(int $pendaftaranId, array $jawabanMap): bool
    {
        foreach ($jawabanMap as $fieldId => $jawaban) {
            $ok = $this->upsert(
                $pendaftaranId,
                (int) $fieldId,
                $jawaban['text'] ?? '',
                $jawaban['json'] ?? null
            );

            if (!$ok) {
                return false;
            }
        }

        return true;
    }
}
