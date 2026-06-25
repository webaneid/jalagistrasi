<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class TahunAjaranRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_tahun_ajaran';
    }

    /** @return list<object> */
    public function findAll(): array
    {
        global $wpdb;

        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY nama DESC") ?: [];
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        ) ?: null;
    }

    public function findAktif(): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            "SELECT * FROM {$this->table} WHERE status = 'aktif' ORDER BY nama DESC LIMIT 1"
        ) ?: null;
    }

    public function findByNama(string $nama, int $excludeId = 0): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE nama = %s AND id != %d",
                $nama,
                $excludeId
            )
        ) ?: null;
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            ['nama' => $data['nama'], 'status' => $data['status']],
            ['%s', '%s']
        );

        return $result !== false ? (int) $wpdb->insert_id : false;
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['nama' => $data['nama'], 'status' => $data['status']],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return $wpdb->delete($this->table, ['id' => $id], ['%d']) !== false;
    }

    public function countGelombang(int $id): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}jg_gelombang WHERE tahun_ajaran_id = %d",
                $id
            )
        );
    }
}
