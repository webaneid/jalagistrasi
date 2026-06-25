<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

/**
 * Semua query ke tabel jg_pendaftar ada di sini.
 * Tidak ada SQL di luar file Repository.
 */
final class PendaftarRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_pendaftar';
    }

    /**
     * Cek apakah nomor WA sudah terdaftar.
     */
    public function existsByNomorWa(string $nomorWa): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE nomor_wa = %s",
                $nomorWa
            )
        );

        return (int) $count > 0;
    }

    /**
     * Cek apakah user_id sudah punya baris di jg_pendaftar.
     */
    public function existsByUserId(int $userId): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d",
                $userId
            )
        );

        return (int) $count > 0;
    }

    /**
     * Buat baris profil pendaftar baru.
     * NIK dan NISN nullable — diisi saat mengisi formulir pendaftaran.
     *
     * @throws \RuntimeException jika insert gagal
     */
    public function insert(int $userId, string $nomorWa): int
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'user_id'  => $userId,
                'nomor_wa' => $nomorWa,
            ],
            ['%d', '%s']
        );

        if ($result === false) {
            throw new \RuntimeException(
                sprintf(
                    'Gagal menyimpan profil pendaftar untuk user_id %d: %s',
                    $userId,
                    $wpdb->last_error
                )
            );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Ambil baris profil berdasarkan user_id.
     *
     * @return object|null
     */
    public function findByUserId(int $userId): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d LIMIT 1",
                $userId
            )
        );

        return $row ?: null;
    }

    /**
     * Ambil profil untuk banyak user sekaligus, keyed by user_id — dipakai export Excel.
     *
     * @param list<int> $userIds
     * @return array<int,object>
     */
    public function findByUserIds(array $userIds): array
    {
        global $wpdb;

        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id IN ({$placeholders})",
                ...$userIds
            )
        );

        $byUserId = [];
        foreach ($rows ?: [] as $row) {
            $byUserId[(int) $row->user_id] = $row;
        }

        return $byUserId;
    }

    /**
     * Sync NIK/NISN ke profil pendaftar setelah submit formulir.
     * Hanya update kolom yang diberikan (tidak menimpa yang sudah ada dengan null).
     *
     * @param array<string,string> $data Key: 'nik' dan/atau 'nisn'
     */
    public function updateNikNisn(int $userId, array $data): bool
    {
        global $wpdb;

        $toUpdate = [];
        $formats  = [];

        if (!empty($data['nik'])) {
            $toUpdate['nik'] = $data['nik'];
            $formats[]       = '%s';
        }

        if (!empty($data['nisn'])) {
            $toUpdate['nisn'] = $data['nisn'];
            $formats[]        = '%s';
        }

        if (empty($toUpdate)) {
            return true;
        }

        return $wpdb->update(
            $this->table,
            $toUpdate,
            ['user_id' => $userId],
            $formats,
            ['%d']
        ) !== false;
    }
}
