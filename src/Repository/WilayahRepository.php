<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

/**
 * Data master wilayah (provinsi/kabupaten/kecamatan/desa). Tabel diisi oleh
 * WilayahImportService, bukan lewat insert/update manual — repository ini
 * read-only. Lihat docs/arsitektur-alamat-wilayah.md.
 */
class WilayahRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_wilayah';
    }

    public function findByKode(string $kode): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE kode = %s", $kode)
        );

        return $row ?: null;
    }

    /**
     * Cari desa/kelurahan (level 4) berdasarkan teks bebas, match terhadap
     * breadcrumb lengkapnya (nama desa + kecamatan + kabupaten + provinsi).
     *
     * @return list<object>
     */
    public function search(string $query, int $limit = 10): array
    {
        global $wpdb;

        $like = '%' . $wpdb->esc_like($query) . '%';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT kode, nama_lengkap FROM {$this->table}
                 WHERE level = 4 AND nama_lengkap LIKE %s
                 ORDER BY nama_lengkap ASC
                 LIMIT %d",
                $like,
                $limit
            )
        );

        return $rows ?: [];
    }

    public function countAll(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }
}
