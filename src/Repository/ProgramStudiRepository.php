<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

class ProgramStudiRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_program_studi';
    }

    /** @return list<object> */
    public function findAll(string $status = ''): array
    {
        global $wpdb;

        if ($status !== '') {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE status = %s ORDER BY urutan ASC, nama ASC",
                    $status
                )
            );
        } else {
            $results = $wpdb->get_results(
                "SELECT * FROM {$this->table} ORDER BY urutan ASC, nama ASC"
            );
        }

        return $results ?? [];
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            )
        );

        return $row ?: null;
    }

    public function findByKode(string $kode, int $excludeId = 0): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE kode = %s AND id != %d",
                $kode,
                $excludeId
            )
        );

        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'nama'      => $data['nama'],
                'kode'      => $data['kode'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'kuota'     => (int) $data['kuota'],
                'urutan'    => (int) $data['urutan'],
                'status'    => $data['status'],
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s']
        );

        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'nama'      => $data['nama'],
                'kode'      => $data['kode'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'kuota'     => (int) $data['kuota'],
                'urutan'    => (int) $data['urutan'],
                'status'    => $data['status'],
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d', '%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );

        return $result !== false && $result > 0;
    }

    public function countPilihan(int $prodiId): int
    {
        global $wpdb;

        $pilihanTable = $wpdb->prefix . 'jg_pendaftaran_prodi';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$pilihanTable} WHERE program_studi_id = %d",
                $prodiId
            )
        );
    }
}
