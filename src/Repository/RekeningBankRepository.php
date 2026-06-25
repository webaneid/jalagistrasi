<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class RekeningBankRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_rekening_bank';
    }

    /** @return list<object> */
    public function findAll(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY urutan ASC, id ASC"
        ) ?: [];
    }

    /** @return list<object> */
    public function findAllAktif(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_aktif = 1 ORDER BY urutan ASC, id ASC"
        ) ?: [];
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        ) ?: null;
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'nama_bank'      => $data['nama_bank'],
                'nomor_rekening' => $data['nomor_rekening'],
                'nama_pemilik'   => $data['nama_pemilik'],
                'is_aktif'       => (int) ($data['is_aktif'] ?? 1),
                'urutan'         => (int) ($data['urutan'] ?? 0),
            ],
            ['%s', '%s', '%s', '%d', '%d']
        );

        return $result !== false ? (int) $wpdb->insert_id : false;
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            [
                'nama_bank'      => $data['nama_bank'],
                'nomor_rekening' => $data['nomor_rekening'],
                'nama_pemilik'   => $data['nama_pemilik'],
                'is_aktif'       => (int) ($data['is_aktif'] ?? 1),
                'urutan'         => (int) ($data['urutan'] ?? 0),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d', '%d'],
            ['%d']
        ) !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return $wpdb->delete($this->table, ['id' => $id], ['%d']) !== false;
    }
}
