<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class PendaftaranProdiRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_pendaftaran_prodi';
    }

    /**
     * @return list<object>
     */
    public function findByPendaftaran(int $pendaftaranId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pp.*, ps.nama AS prodi_nama, ps.kode AS prodi_kode
                 FROM {$this->table} pp
                 LEFT JOIN {$wpdb->prefix}jg_program_studi ps ON ps.id = pp.program_studi_id
                 WHERE pp.pendaftaran_id = %d
                 ORDER BY pp.urutan ASC",
                $pendaftaranId
            )
        );

        return $rows ?: [];
    }

    /**
     * Ambil pilihan prodi untuk banyak pendaftaran sekaligus — dipakai export Excel.
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
                "SELECT pp.*, ps.nama AS prodi_nama, ps.kode AS prodi_kode
                 FROM {$this->table} pp
                 LEFT JOIN {$wpdb->prefix}jg_program_studi ps ON ps.id = pp.program_studi_id
                 WHERE pp.pendaftaran_id IN ({$placeholders})
                 ORDER BY pp.pendaftaran_id, pp.urutan ASC",
                ...$pendaftaranIds
            )
        );

        return $rows ?: [];
    }

    /**
     * Simpan pilihan prodi secara bulk. Setiap key = urutan prioritas (1-based).
     *
     * @param array<int,int> $prodiPilihan key = urutan (1,2,3,...), value = program_studi_id
     */
    public function insertAll(int $pendaftaranId, array $prodiPilihan): bool
    {
        global $wpdb;

        foreach ($prodiPilihan as $urutan => $prodiId) {
            $result = $wpdb->insert(
                $this->table,
                [
                    'pendaftaran_id'   => $pendaftaranId,
                    'program_studi_id' => $prodiId,
                    'urutan'           => $urutan,
                ],
                ['%d', '%d', '%d']
            );

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public function deleteByPendaftaran(int $pendaftaranId): bool
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table,
            ['pendaftaran_id' => $pendaftaranId],
            ['%d']
        ) !== false;
    }

    /**
     * Prodi terpopuler berdasarkan pilihan ke-1 — untuk dashboard admin
     * (lihat docs/arsitektur-dashboard-admin.md). Hanya pendaftaran non-draft dihitung.
     *
     * @return list<object{prodi_nama:string, jumlah:int}>
     */
    public function findProdiTerpopuler(int $gelombangId = 0, int $limit = 10, int $tahunAjaranId = 0): array
    {
        global $wpdb;

        $p     = $wpdb->prefix . 'jg_pendaftaran';
        $join  = '';
        $where = "WHERE pp.urutan = 1 AND p.status != 'draft'";
        $args  = [];

        if ($gelombangId > 0) {
            $where .= ' AND p.gelombang_id = %d';
            $args[] = $gelombangId;
        } elseif ($tahunAjaranId > 0) {
            $join   = "JOIN {$wpdb->prefix}jg_gelombang g ON g.id = p.gelombang_id";
            $where .= ' AND g.tahun_ajaran_id = %d';
            $args[] = $tahunAjaranId;
        }

        $sql = "SELECT ps.nama AS prodi_nama, COUNT(*) AS jumlah
                FROM {$this->table} pp
                JOIN {$p} p ON p.id = pp.pendaftaran_id
                {$join}
                JOIN {$wpdb->prefix}jg_program_studi ps ON ps.id = pp.program_studi_id
                {$where}
                GROUP BY pp.program_studi_id, ps.nama
                ORDER BY jumlah DESC
                LIMIT %d";
        $args[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($sql, ...$args)) ?: [];
    }
}
