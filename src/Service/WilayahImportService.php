<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

/**
 * Import data master wilayah (provinsi/kabupaten/kecamatan/desa) dari
 * data/wilayah.csv ke tabel jg_wilayah. Lihat docs/arsitektur-alamat-wilayah.md.
 *
 * CSV sudah diproses sebelumnya (kolom level & nama_lengkap dihitung di muka
 * dari db/wilayah.sql resmi cahyadsn/wilayah) — service ini cuma bulk insert,
 * tidak ada parsing hierarki saat runtime.
 *
 * Dipanggil otomatis sekali saat aktivasi/migrasi versi DB (lihat Installer::activate()
 * dan Plugin::runMigrationsIfNeeded()), dan tersedia sebagai aksi manual "Sync Data
 * Wilayah" di halaman Pengaturan untuk re-import kalau dataset di-update di kemudian hari.
 */
class WilayahImportService
{
    private const BATCH_SIZE = 500;

    /**
     * @return int Jumlah baris yang berhasil diimport.
     */
    public function import(): int
    {
        global $wpdb;

        $csvPath = JG_PLUGIN_DIR . 'data/wilayah.csv';
        if (!is_readable($csvPath)) {
            return 0;
        }

        $table = $wpdb->prefix . 'jg_wilayah';

        // Re-sync: kosongkan dulu supaya idempotent (boleh dipanggil berkali-kali,
        // termasuk dari tombol manual "Sync Data Wilayah").
        $wpdb->query("TRUNCATE TABLE {$table}");

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return 0;
        }

        fgetcsv($handle, 0, ',', '"', '\\'); // skip header

        $batch = [];
        $total = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            [$kode, $nama, $level, $namaLengkap] = $row + [null, null, null, null];

            $batch[] = $wpdb->prepare(
                '(%s, %s, %d, %s)',
                $kode,
                $nama,
                (int) $level,
                $namaLengkap !== '' ? $namaLengkap : null
            );

            if (count($batch) >= self::BATCH_SIZE) {
                $this->flushBatch($table, $batch);
                $total += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->flushBatch($table, $batch);
            $total += count($batch);
        }

        fclose($handle);

        return $total;
    }

    /**
     * @param list<string> $rows Tuple SQL siap pakai, hasil $wpdb->prepare().
     */
    private function flushBatch(string $table, array $rows): void
    {
        global $wpdb;

        $wpdb->query(
            "INSERT INTO {$table} (kode, nama, level, nama_lengkap) VALUES " . implode(',', $rows)
        );
    }
}
