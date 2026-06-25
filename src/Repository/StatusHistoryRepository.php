<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

/**
 * Audit trail perubahan status pendaftaran. Baris di sini tidak pernah diupdate/dihapus.
 */
final class StatusHistoryRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_status_history';
    }

    public function log(int $pendaftaranId, string $statusLama, string $statusBaru, int $actorUserId, string $catatan = ''): bool
    {
        global $wpdb;

        return $wpdb->insert(
            $this->table,
            [
                'pendaftaran_id' => $pendaftaranId,
                'status_lama'    => $statusLama,
                'status_baru'    => $statusBaru,
                'actor_user_id'  => $actorUserId,
                'catatan'        => $catatan ?: null,
            ],
            ['%d', '%s', '%s', '%d', '%s']
        ) !== false;
    }

    /** @return list<object> */
    public function findByPendaftaran(int $pendaftaranId): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE pendaftaran_id = %d ORDER BY created_at ASC",
                $pendaftaranId
            )
        ) ?: [];
    }
}
