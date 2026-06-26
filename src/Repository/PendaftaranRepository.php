<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

final class PendaftaranRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_pendaftaran';
    }

    public function findById(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $id
            )
        );

        return $row ?: null;
    }

    public function findByUserGelombang(int $userId, int $gelombangId): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d AND gelombang_id = %d LIMIT 1",
                $userId,
                $gelombangId
            )
        );

        return $row ?: null;
    }

    /**
     * @return list<object>
     */
    public function findByUser(int $userId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, g.nama AS gelombang_nama, ta.nama AS tahun_akademik
                 FROM {$this->table} p
                 LEFT JOIN {$wpdb->prefix}jg_gelombang g ON g.id = p.gelombang_id
                 LEFT JOIN {$wpdb->prefix}jg_tahun_ajaran ta ON ta.id = g.tahun_ajaran_id
                 WHERE p.user_id = %d
                 ORDER BY p.created_at DESC",
                $userId
            )
        );

        return $rows ?: [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert($this->table, $data);

        return $result !== false ? (int) $wpdb->insert_id : false;
    }

    public function updateStatus(int $id, string $status, ?string $submittedAt = null): bool
    {
        global $wpdb;

        $data   = ['status' => $status];
        $format = ['%s'];

        if ($submittedAt !== null) {
            $data['submitted_at'] = $submittedAt;
            $format[]             = '%s';
        }

        return $wpdb->update($this->table, $data, ['id' => $id], $format, ['%d']) !== false;
    }

    public function updateNomor(int $id, string $nomor): bool
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['nomor_pendaftaran' => $nomor],
            ['id' => $id],
            ['%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Token rahasia untuk URL verifikasi QR (/verifikasi/<nomor>/<token>/) —
     * lihat docs/arsitektur-verifikasi-qr.md. Dibuat sekali saat submit pertama,
     * tidak pernah berubah lagi setelahnya.
     */
    public function updateVerifikasiToken(int $id, string $token): bool
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['verifikasi_token' => $token],
            ['id' => $id],
            ['%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Cari berdasarkan nomor_pendaftaran SAJA (belum cek token) — dipakai
     * VerifikasiController yang lalu memvalidasi token-nya sendiri dengan
     * hash_equals() supaya konstan-waktu (hindari timing attack).
     */
    public function findByNomor(string $nomor): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE nomor_pendaftaran = %s", $nomor)
        );

        return $row ?: null;
    }

    public function countByGelombang(int $gelombangId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE gelombang_id = %d",
                $gelombangId
            )
        );
    }

    /**
     * Daftar pendaftar untuk halaman admin dengan filter & paginasi.
     * Draft selalu dikecualikan kecuali status diset eksplisit ke 'draft'.
     *
     * @return array{total:int, rows:list<object>}
     */
    public function findAllWithFilter(
        int    $gelombangId = 0,
        string $status      = '',
        string $search      = '',
        int    $page        = 1,
        int    $perPage     = 20
    ): array {
        global $wpdb;

        $g    = $wpdb->prefix . 'jg_gelombang';
        $ta   = $wpdb->prefix . 'jg_tahun_ajaran';
        $u    = $wpdb->users;
        $where = ["p.status != 'draft'"];
        $args  = [];

        if ($gelombangId > 0) {
            $where[] = 'p.gelombang_id = %d';
            $args[]  = $gelombangId;
        }

        if ($status !== '') {
            // Override: jika status diset, terapkan langsung (termasuk 'draft' jika admin mau)
            array_shift($where);
            $where[] = 'p.status = %s';
            $args[]  = $status;
        }

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.display_name LIKE %s OR p.nomor_pendaftaran LIKE %s)';
            $args[]  = $like;
            $args[]  = $like;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $base = "FROM {$this->table} p
                 JOIN {$u} u ON u.ID = p.user_id
                 JOIN {$g} g ON g.id = p.gelombang_id
                 LEFT JOIN {$ta} ta ON ta.id = g.tahun_ajaran_id
                 {$whereSql}";

        $total = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare("SELECT COUNT(*) {$base}", ...$args)
        );

        $offset = ($page - 1) * $perPage;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*,
                        u.display_name AS nama_pendaftar,
                        u.user_email,
                        g.nama         AS gelombang_nama,
                        ta.nama        AS tahun_akademik
                 {$base}
                 ORDER BY p.submitted_at DESC, p.created_at DESC
                 LIMIT %d OFFSET %d",
                ...[...$args, $perPage, $offset]
            )
        );

        return ['total' => $total, 'rows' => $rows ?: []];
    }

    /**
     * Sama seperti findAllWithFilter() tapi TANPA paginasi — dipakai export Excel
     * (export harus ambil semua baris yang cocok filter, bukan satu halaman saja).
     *
     * @return list<object>
     */
    public function findAllForExport(int $gelombangId = 0, string $status = '', string $search = ''): array
    {
        global $wpdb;

        $g    = $wpdb->prefix . 'jg_gelombang';
        $ta   = $wpdb->prefix . 'jg_tahun_ajaran';
        $u    = $wpdb->users;
        $where = ["p.status != 'draft'"];
        $args  = [];

        if ($gelombangId > 0) {
            $where[] = 'p.gelombang_id = %d';
            $args[]  = $gelombangId;
        }

        if ($status !== '') {
            array_shift($where);
            $where[] = 'p.status = %s';
            $args[]  = $status;
        }

        if ($search !== '') {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.display_name LIKE %s OR p.nomor_pendaftaran LIKE %s)';
            $args[]  = $like;
            $args[]  = $like;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT p.*,
                       u.display_name AS nama_pendaftar,
                       u.user_email,
                       g.nama         AS gelombang_nama,
                       ta.nama        AS tahun_akademik
                FROM {$this->table} p
                JOIN {$u} u ON u.ID = p.user_id
                JOIN {$g} g ON g.id = p.gelombang_id
                LEFT JOIN {$ta} ta ON ta.id = g.tahun_ajaran_id
                {$whereSql}
                ORDER BY p.submitted_at DESC, p.created_at DESC";

        if (empty($args)) {
            return $wpdb->get_results($sql) ?: [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($wpdb->prepare($sql, ...$args)) ?: [];
    }

    public function updateStatusWithCatatan(int $id, string $status, string $catatan): bool
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['status' => $status, 'catatan_panitia' => $catatan],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    public function updateKodeUnikPembayaran(int $id, int $kodeUnik): bool
    {
        global $wpdb;

        return $wpdb->update(
            $this->table,
            ['kode_unik_pembayaran' => $kodeUnik],
            ['id' => $id],
            ['%d'],
            ['%d']
        ) !== false;
    }

    /**
     * Jumlah pendaftar per status — untuk dashboard admin (lihat docs/arsitektur-dashboard-admin.md).
     * Exclude 'draft' karena belum dianggap benar-benar "mendaftar".
     *
     * @return array<string,int> status_value => jumlah
     */
    public function countByStatusGrouped(int $gelombangId = 0, int $tahunAjaranId = 0): array
    {
        global $wpdb;

        $join  = '';
        $where = "WHERE p.status != 'draft'";
        $args  = [];

        if ($gelombangId > 0) {
            $where .= ' AND p.gelombang_id = %d';
            $args[] = $gelombangId;
        } elseif ($tahunAjaranId > 0) {
            $join   = "JOIN {$wpdb->prefix}jg_gelombang g ON g.id = p.gelombang_id";
            $where .= ' AND g.tahun_ajaran_id = %d';
            $args[] = $tahunAjaranId;
        }

        $sql = "SELECT p.status, COUNT(*) AS jumlah FROM {$this->table} p {$join} {$where} GROUP BY p.status";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args)) : $wpdb->get_results($sql);

        $result = [];
        foreach ($rows ?: [] as $row) {
            $result[$row->status] = (int) $row->jumlah;
        }

        return $result;
    }

    public function countTotal(int $gelombangId = 0, int $tahunAjaranId = 0): int
    {
        global $wpdb;

        if ($gelombangId > 0) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE status != 'draft' AND gelombang_id = %d",
                $gelombangId
            ));
        }

        if ($tahunAjaranId > 0) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} p
                 JOIN {$wpdb->prefix}jg_gelombang g ON g.id = p.gelombang_id
                 WHERE p.status != 'draft' AND g.tahun_ajaran_id = %d",
                $tahunAjaranId
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status != 'draft'");
    }

    /**
     * Kode unik yang sudah dipakai gelombang ini — untuk cek collision saat generate baru.
     *
     * @return list<int>
     */
    public function findKodeUnikTerpakai(int $gelombangId): array
    {
        global $wpdb;

        $kode = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT kode_unik_pembayaran FROM {$this->table}
                 WHERE gelombang_id = %d AND kode_unik_pembayaran IS NOT NULL",
                $gelombangId
            )
        );

        return array_map('intval', $kode);
    }
}
