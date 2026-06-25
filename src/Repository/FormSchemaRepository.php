<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Repository;

class FormSchemaRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'jg_form_field';
    }

    /** @return list<object> */
    public function findByGelombang(int $gelombangId): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE gelombang_id = %d ORDER BY urutan ASC, id ASC",
                $gelombangId
            )
        );

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

    public function existsNamaField(int $gelombangId, string $namaField, int $excludeId = 0): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE gelombang_id = %d AND nama_field = %s AND id != %d",
                $gelombangId,
                $namaField,
                $excludeId
            )
        );

        return $count > 0;
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

    /** @param array<string,mixed> $data */
    public function insert(array $data): int|false
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'gelombang_id' => (int) $data['gelombang_id'],
                'section_name' => $data['section_name'] ?? null,
                'nama_field'   => $data['nama_field'],
                'label'        => $data['label'],
                'tipe'         => $data['tipe'],
                'is_required'  => (int) ($data['is_required'] ?? 0),
                'is_core'      => (int) ($data['is_core'] ?? 0),
                'urutan'       => (int) ($data['urutan'] ?? 0),
                'konfigurasi'  => isset($data['konfigurasi']) ? wp_json_encode($data['konfigurasi']) : null,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
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
                'section_name' => $data['section_name'] ?? null,
                'label'        => $data['label'],
                'tipe'         => $data['tipe'],
                'is_required'  => (int) ($data['is_required'] ?? 0),
                'urutan'       => (int) ($data['urutan'] ?? 0),
                'konfigurasi'  => isset($data['konfigurasi']) ? wp_json_encode($data['konfigurasi']) : null,
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

        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

        return $result !== false && $result > 0;
    }

    /**
     * Bulk update urutan field setelah drag & drop.
     *
     * @param array<int,int> $urutanMap [field_id => urutan_baru]
     */
    public function updateUrutan(array $urutanMap): bool
    {
        global $wpdb;

        foreach ($urutanMap as $fieldId => $urutan) {
            $wpdb->update(
                $this->table,
                ['urutan' => (int) $urutan],
                ['id'     => (int) $fieldId],
                ['%d'],
                ['%d']
            );
        }

        return true;
    }
}
