<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class BerkasRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_berkas';
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );

        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert($this->table, $data);

        return $result !== false ? (int) $wpdb->insert_id : false;
    }

    /**
     * @return list<object>
     */
    public function findByPendaftaran(int $pendaftaranId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE pendaftaran_id = %d ORDER BY uploaded_at ASC",
                $pendaftaranId
            )
        );

        return $rows ?: [];
    }

    /**
     * Ambil semua berkas untuk banyak pendaftaran sekaligus — dipakai export Excel
     * (hindari N+1 query). Hasil TIDAK dikelompokkan, urut sesuai DB; pemanggil yang
     * mengelompokkan per pendaftaran_id.
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
                "SELECT * FROM {$this->table} WHERE pendaftaran_id IN ({$placeholders}) ORDER BY pendaftaran_id, uploaded_at ASC",
                ...$pendaftaranIds
            )
        );

        return $rows ?: [];
    }

    public function findByPendaftaranAndTipe(int $pendaftaranId, string $tipeBerkas): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE pendaftaran_id = %d AND tipe_berkas = %s
                 ORDER BY uploaded_at DESC LIMIT 1",
                $pendaftaranId,
                $tipeBerkas
            )
        );

        return $row ?: null;
    }

    public function deleteByPendaftaranAndTipe(int $pendaftaranId, string $tipeBerkas): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table,
            ['pendaftaran_id' => $pendaftaranId, 'tipe_berkas' => $tipeBerkas],
            ['%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Set hasil review admin atas satu dokumen: diterima / ditolak / pending.
     * Catatan hanya relevan untuk 'ditolak' tapi kolom tetap diupdate (null jika kosong)
     * agar catatan lama tidak nyangkut saat dokumen di-set ulang ke status lain.
     */
    public function updateVerifikasi(int $id, string $status, ?string $catatan, int $verifiedBy): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'status'      => $status,
                'catatan'     => $catatan ?: null,
                'verified_at' => current_time('mysql'),
                'verified_by' => $verifiedBy,
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d'],
            ['%d']
        );

        return $result !== false;
    }
}
