<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

/**
 * Query SEMUA akun WordPress ber-role 'pendaftar' — beda dari PendaftaranRepository
 * (itu query tabel jg_pendaftaran, 1 baris = 1 pendaftaran ke gelombang tertentu).
 * Di sini 1 baris = 1 akun, terlepas pernah submit formulir atau belum. Lihat
 * docs/arsitektur-overview.md ("Autentikasi & Role" — menu "Role Pendaftar").
 */
final class AkunPendaftarRepository
{
    /**
     * @return array{total:int, rows:list<object>}
     */
    public function findAllWithFilter(string $statusFilter = '', string $search = '', int $page = 1, int $perPage = 20): array
    {
        global $wpdb;

        [$base, $args] = $this->buildBaseQuery($statusFilter, $search);

        $total = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare("SELECT COUNT(*) {$base}", ...$args)
        );

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT u.ID, u.display_name, u.user_email, u.user_registered,
                       pd.nomor_wa,
                       EXISTS(
                           SELECT 1 FROM {$wpdb->prefix}jg_pendaftaran p
                           WHERE p.user_id = u.ID AND p.status != 'draft'
                       ) AS sudah_mendaftar
                {$base}
                ORDER BY u.user_registered DESC
                LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...[...$args, $perPage, $offset]));

        return ['total' => $total, 'rows' => $rows ?: []];
    }

    /**
     * Sama seperti findAllWithFilter() tapi tanpa paginasi — dipakai export Excel.
     *
     * @return list<object>
     */
    public function findAllForExport(string $statusFilter = '', string $search = ''): array
    {
        global $wpdb;

        [$base, $args] = $this->buildBaseQuery($statusFilter, $search);

        $sql = "SELECT u.ID, u.display_name, u.user_email, u.user_registered,
                       pd.nomor_wa, pd.nik, pd.nisn,
                       EXISTS(
                           SELECT 1 FROM {$wpdb->prefix}jg_pendaftaran p
                           WHERE p.user_id = u.ID AND p.status != 'draft'
                       ) AS sudah_mendaftar
                {$base}
                ORDER BY u.user_registered DESC";

        if (empty($args)) {
            return $wpdb->get_results($sql) ?: [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($sql, ...$args)) ?: [];
    }

    /**
     * @return array{0:string,1:list<mixed>} [SQL FROM...WHERE tanpa SELECT/ORDER/LIMIT, args untuk prepare()]
     */
    private function buildBaseQuery(string $statusFilter, string $search): array
    {
        global $wpdb;

        $usersTable = $wpdb->users;
        $usermeta   = $wpdb->usermeta;
        $pendaftar  = $wpdb->prefix . 'jg_pendaftar';
        $pendaftaranTable = $wpdb->prefix . 'jg_pendaftaran';

        $where = [];
        $args  = [];

        // Role disimpan serialized di wp_usermeta.wp_capabilities — LIKE pattern
        // ini aman karena tidak ada role lain di plugin ini yang mengandung
        // substring "pendaftar" (panitia_pmb, verifikator_berkas, admin_pmb).
        $where[] = "um.meta_key = %s AND um.meta_value LIKE %s";
        $args[]  = $wpdb->prefix . 'capabilities';
        $args[]  = '%"pendaftar"%';

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
            $args[]  = $like;
            $args[]  = $like;
        }

        if ($statusFilter === 'sudah_mendaftar') {
            $where[] = "EXISTS (SELECT 1 FROM {$pendaftaranTable} p2 WHERE p2.user_id = u.ID AND p2.status != 'draft')";
        } elseif ($statusFilter === 'baru_akun') {
            $where[] = "NOT EXISTS (SELECT 1 FROM {$pendaftaranTable} p2 WHERE p2.user_id = u.ID AND p2.status != 'draft')";
        }

        $whereSql = implode(' AND ', $where);

        $base = "FROM {$usersTable} u
                  INNER JOIN {$usermeta} um ON um.user_id = u.ID
                  LEFT JOIN {$pendaftar} pd ON pd.user_id = u.ID
                  WHERE {$whereSql}";

        return [$base, $args];
    }
}
