<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class PembayaranRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_pembayaran';
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        ) ?: null;
    }

    public function findByPendaftaran(int $pendaftaranId): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE pendaftaran_id = %d", $pendaftaranId)
        ) ?: null;
    }

    /**
     * Ambil pembayaran untuk banyak pendaftaran sekaligus, keyed by pendaftaran_id
     * (relasi 1:1, lihat UNIQUE KEY uq_pendaftaran) — dipakai export Excel.
     *
     * @param list<int> $pendaftaranIds
     * @return array<int,object>
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

        $byPendaftaranId = [];
        foreach ($rows ?: [] as $row) {
            $byPendaftaranId[(int) $row->pendaftaran_id] = $row;
        }

        return $byPendaftaranId;
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert($this->table, $data);

        return $result !== false ? (int) $wpdb->insert_id : false;
    }

    public function deleteByPendaftaran(int $pendaftaranId): bool
    {
        global $wpdb;

        return $wpdb->delete($this->table, ['pendaftaran_id' => $pendaftaranId], ['%d']) !== false;
    }
}
