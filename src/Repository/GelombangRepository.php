<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

class GelombangRepository
{
    private string $table;
    private string $tahunTable;

    public function __construct()
    {
        global $wpdb;
        $this->table     = $wpdb->prefix . 'jg_gelombang';
        $this->tahunTable = $wpdb->prefix . 'jg_tahun_ajaran';
    }

    /**
     * SELECT dasar dengan JOIN ke tahun ajaran — `tahun_akademik` dikembalikan sebagai
     * alias hasil JOIN supaya template yang sudah ada (banyak) tidak perlu diubah.
     * Lihat docs/arsitektur-tahun-ajaran.md.
     */
    private function selectWithTahunAjaran(): string
    {
        return "SELECT g.*, ta.nama AS tahun_akademik
                FROM {$this->table} g
                LEFT JOIN {$this->tahunTable} ta ON ta.id = g.tahun_ajaran_id";
    }

    /** @return list<object> */
    public function findAll(string $status = ''): array
    {
        global $wpdb;

        $base = $this->selectWithTahunAjaran();

        if ($status !== '') {
            $results = $wpdb->get_results(
                $wpdb->prepare("{$base} WHERE g.status = %s ORDER BY g.tanggal_buka DESC", $status)
            );
        } else {
            $results = $wpdb->get_results("{$base} ORDER BY g.tanggal_buka DESC");
        }

        return $results ?? [];
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("{$this->selectWithTahunAjaran()} WHERE g.id = %d", $id)
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
                'nama'               => $data['nama'],
                'tahun_ajaran_id'    => (int) $data['tahun_ajaran_id'],
                'tanggal_buka'       => $data['tanggal_buka'],
                'tanggal_tutup'      => $data['tanggal_tutup'],
                'max_pilihan_prodi'  => (int) $data['max_pilihan_prodi'],
                'biaya_pendaftaran'  => (float) ($data['biaya_pendaftaran'] ?? 0),
                'status'             => $data['status'],
                'created_by'         => (int) $data['created_by'],
            ],
            ['%s', '%d', '%s', '%s', '%d', '%f', '%s', '%d']
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
                'nama'               => $data['nama'],
                'tahun_ajaran_id'    => (int) $data['tahun_ajaran_id'],
                'tanggal_buka'       => $data['tanggal_buka'],
                'tanggal_tutup'      => $data['tanggal_tutup'],
                'max_pilihan_prodi'  => (int) $data['max_pilihan_prodi'],
                'biaya_pendaftaran'  => (float) ($data['biaya_pendaftaran'] ?? 0),
                'status'             => $data['status'],
            ],
            ['id' => $id],
            ['%s', '%d', '%s', '%s', '%d', '%f', '%s'],
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

    /**
     * Gelombang aktif yang masih dalam periode pendaftaran (tanggal_buka ≤ now ≤ tanggal_tutup).
     *
     * @return list<object>
     */
    public function findAktifTerbuka(): array
    {
        global $wpdb;

        $now     = current_time('mysql');
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "{$this->selectWithTahunAjaran()}
                 WHERE g.status = 'aktif'
                   AND g.tanggal_buka <= %s
                   AND g.tanggal_tutup >= %s
                 ORDER BY g.tanggal_buka ASC",
                $now,
                $now
            )
        );

        return $results ?? [];
    }

    /** @return list<object> */
    public function findByTahunAjaran(int $tahunAjaranId): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "{$this->selectWithTahunAjaran()} WHERE g.tahun_ajaran_id = %d ORDER BY g.tanggal_buka DESC",
                $tahunAjaranId
            )
        );

        return $results ?? [];
    }

    public function countPendaftaran(int $gelombangId): int
    {
        global $wpdb;

        $pendaftaranTable = $wpdb->prefix . 'jg_pendaftaran';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$pendaftaranTable} WHERE gelombang_id = %d",
                $gelombangId
            )
        );
    }
}
