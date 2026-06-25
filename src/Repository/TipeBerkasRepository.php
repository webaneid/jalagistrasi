<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class TipeBerkasRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_tipe_berkas';
    }

    /** @return list<object> */
    public function findByGelombang(int $gelombangId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE gelombang_id = %d ORDER BY urutan ASC, id ASC",
                $gelombangId
            )
        );

        return $rows ?: [];
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        ) ?: null;
    }

    public function kodeExists(int $gelombangId, string $kode, int $excludeId = 0): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE gelombang_id = %d AND kode = %s AND id != %d",
                $gelombangId, $kode, $excludeId
            )
        ) > 0;
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int|false
    {
        global $wpdb;
        $result = $wpdb->insert($this->table, $data);
        return $result !== false ? (int) $wpdb->insert_id : false;
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        global $wpdb;
        return $wpdb->update($this->table, $data, ['id' => $id]) !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => $id], ['%d']) !== false;
    }

    public function countByGelombang(int $gelombangId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE gelombang_id = %d",
                $gelombangId
            )
        );
    }
}
