<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Webane\Jalagistrasi\Enum\StatusPendaftaran;
use Webane\Jalagistrasi\Repository\BerkasRepository;
use Webane\Jalagistrasi\Repository\FormJawabanRepository;
use Webane\Jalagistrasi\Repository\FormSchemaRepository;
use Webane\Jalagistrasi\Repository\PembayaranRepository;
use Webane\Jalagistrasi\Repository\PendaftaranProdiRepository;
use Webane\Jalagistrasi\Repository\PendaftaranRepository;
use Webane\Jalagistrasi\Repository\PendaftarRepository;
use Webane\Jalagistrasi\Repository\TipeBerkasRepository;
use Webane\Jalagistrasi\Repository\WilayahRepository;

/**
 * Export data pendaftar ke .xlsx — satu baris per pendaftaran, kolom biodata
 * dinamis (mengikuti form builder gelombang yang terlibat) + link dokumen.
 *
 * Link dokumen mengarah ke endpoint admin-only TANPA nonce (lihat
 * PendaftarController::handleExportPreviewBerkas()) — sengaja didesain supaya
 * link tidak pernah expired, proteksi murni dari login + capability admin.
 * Keputusan diambil setelah diskusi eksplisit soal risiko dokumen sensitif
 * (KTP/KK) kalau dibuat benar-benar publik tanpa proteksi apapun.
 *
 * Field nama_field 'email', 'nomor_hp', 'nik', 'nisn', 'nama_lengkap' SENGAJA
 * dikecualikan dari kolom dinamis karena sudah punya kolom inti tersendiri
 * (sumbernya wp_users / jg_pendaftar, bukan jg_form_jawaban) — exclude di sini
 * supaya tidak duplikat kolom.
 */
class PendaftarExportService
{
    private const NAMA_FIELD_DIKECUALIKAN = ['email', 'nomor_hp', 'nik', 'nisn', 'nama_lengkap'];

    private const VERIFIKASI_LABEL = [
        'pending'  => 'Menunggu Verifikasi',
        'diterima' => 'Diterima',
        'ditolak'  => 'Ditolak',
    ];

    /** @var array<string,string> cache kode wilayah => nama_lengkap, hindari query berulang */
    private array $wilayahCache = [];

    public function build(int $gelombangId, string $status, string $search): Spreadsheet
    {
        $pendaftaranRepo = new PendaftaranRepository();
        $rows = $pendaftaranRepo->findAllForExport($gelombangId, $status, $search);

        $pendaftaranIds = array_map(static fn ($r) => (int) $r->id, $rows);
        $userIds        = array_unique(array_map(static fn ($r) => (int) $r->user_id, $rows));
        $gelombangIds   = array_unique(array_map(static fn ($r) => (int) $r->gelombang_id, $rows));

        $pendaftarByUser = (new PendaftarRepository())->findByUserIds($userIds);

        $prodiByPendaftaran = $this->groupBy(
            (new PendaftaranProdiRepository())->findByPendaftaranIds($pendaftaranIds),
            'pendaftaran_id'
        );
        $jawabanByPendaftaran = $this->groupByField(
            (new FormJawabanRepository())->findByPendaftaranIds($pendaftaranIds)
        );
        $berkasByPendaftaran = $this->groupByTipe(
            (new BerkasRepository())->findByPendaftaranIds($pendaftaranIds)
        );
        $pembayaranByPendaftaran = (new PembayaranRepository())->findByPendaftaranIds($pendaftaranIds);

        // --- Skema dinamis: union field biodata & tipe berkas dari semua gelombang terlibat ---
        $formSchemaRepo = new FormSchemaRepository();
        $tipeBerkasRepo = new TipeBerkasRepository();

        $dynamicFields = []; // nama_field => label
        $tipeBerkasAll = []; // kode => label
        $maxPilihanProdi = 1;
        // nama_field => [gelombang_id => field object] — dibangun sekali di sini,
        // dipakai formatJawaban() supaya tidak query ulang findByGelombang() per baris.
        $fieldByGelombang = [];

        foreach ($gelombangIds as $gid) {
            foreach ($formSchemaRepo->findByGelombang($gid) as $f) {
                if (in_array($f->nama_field, self::NAMA_FIELD_DIKECUALIKAN, true)) {
                    continue;
                }
                if (!isset($dynamicFields[$f->nama_field])) {
                    $dynamicFields[$f->nama_field] = $f->label;
                }
                $fieldByGelombang[$f->nama_field][$gid] = $f;
            }
            foreach ($tipeBerkasRepo->findByGelombang($gid) as $t) {
                if (!isset($tipeBerkasAll[$t->kode])) {
                    $tipeBerkasAll[$t->kode] = $t->label;
                }
            }
        }
        foreach ($prodiByPendaftaran as $list) {
            foreach ($list as $pp) {
                $maxPilihanProdi = max($maxPilihanProdi, (int) $pp->urutan);
            }
        }

        // --- Bangun header ---
        $headers = [
            'No', 'Nomor Pendaftaran', 'Nama Lengkap', 'Email', 'NIK', 'NISN', 'No. WhatsApp',
            'Gelombang', 'Tahun Akademik', 'Status', 'Tanggal Submit',
        ];
        for ($i = 1; $i <= $maxPilihanProdi; $i++) {
            $headers[] = "Pilihan Prodi {$i}";
        }
        foreach ($dynamicFields as $label) {
            $headers[] = $label;
        }
        $headers[] = 'Bukti Pembayaran';
        foreach ($tipeBerkasAll as $label) {
            $headers[] = "Dok: {$label}";
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pendaftar');
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1');
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->freezePane('A2');

        // --- Isi baris ---
        $rowNum = 2;
        $no = 1;
        foreach ($rows as $p) {
            $pendaftaranId = (int) $p->id;
            $userId        = (int) $p->user_id;
            $pendaftar     = $pendaftarByUser[$userId] ?? null;

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $no++);
            $sheet->setCellValue([$col++, $rowNum], $p->nomor_pendaftaran);
            $sheet->setCellValue([$col++, $rowNum], $p->nama_pendaftar);
            $sheet->setCellValue([$col++, $rowNum], $p->user_email);
            $sheet->setCellValue([$col++, $rowNum], $pendaftar->nik ?? '');
            $sheet->setCellValue([$col++, $rowNum], $pendaftar->nisn ?? '');
            $sheet->setCellValue([$col++, $rowNum], $pendaftar->nomor_wa ?? '');
            $sheet->setCellValue([$col++, $rowNum], $p->gelombang_nama);
            $sheet->setCellValue([$col++, $rowNum], $p->tahun_akademik);
            $sheet->setCellValue([$col++, $rowNum], StatusPendaftaran::from($p->status)->label());
            $sheet->setCellValue([$col++, $rowNum], $p->submitted_at ? date_i18n('d M Y H:i', strtotime($p->submitted_at)) : '');

            // Pilihan prodi (kolom dinamis sebanyak $maxPilihanProdi)
            $prodiByUrutan = [];
            foreach ($prodiByPendaftaran[$pendaftaranId] ?? [] as $pp) {
                $prodiByUrutan[(int) $pp->urutan] = $pp->prodi_nama ?? '';
            }
            for ($i = 1; $i <= $maxPilihanProdi; $i++) {
                $sheet->setCellValue([$col++, $rowNum], $prodiByUrutan[$i] ?? '');
            }

            // Field biodata dinamis
            $jawabanByField = $jawabanByPendaftaran[$pendaftaranId] ?? [];
            $gid            = (int) $p->gelombang_id;
            foreach ($dynamicFields as $namaField => $label) {
                $sheet->setCellValue(
                    [$col++, $rowNum],
                    $this->formatJawaban($fieldByGelombang[$namaField][$gid] ?? null, $jawabanByField)
                );
            }

            // Bukti pembayaran (1 kolom, hyperlink kalau ada)
            $pembayaran = $pembayaranByPendaftaran[$pendaftaranId] ?? null;
            $payCellCoord = $this->coordFor($col++, $rowNum);
            if ($pembayaran) {
                $label = 'Rp ' . number_format((float) $pembayaran->jumlah, 0, ',', '.');
                $sheet->setCellValue($payCellCoord, $label);
                $sheet->getCell($payCellCoord)->getHyperlink()->setUrl(
                    add_query_arg([
                        'action'        => 'jg_export_preview_pembayaran',
                        'pembayaran_id' => $pembayaran->id,
                    ], admin_url('admin-ajax.php'))
                );
            } else {
                $sheet->setCellValue($payCellCoord, 'Belum ada bukti');
            }

            // Dokumen per tipe berkas (hyperlink kalau ada)
            $berkasByTipe = $berkasByPendaftaran[$pendaftaranId] ?? [];
            foreach ($tipeBerkasAll as $kode => $label) {
                $docCellCoord = $this->coordFor($col++, $rowNum);
                $berkas = $berkasByTipe[$kode] ?? null;
                if ($berkas) {
                    $verLabel = self::VERIFIKASI_LABEL[$berkas->status] ?? $berkas->status;
                    $sheet->setCellValue($docCellCoord, $verLabel);
                    $sheet->getCell($docCellCoord)->getHyperlink()->setUrl(
                        add_query_arg([
                            'action'    => 'jg_export_preview_berkas',
                            'berkas_id' => $berkas->id,
                        ], admin_url('admin-ajax.php'))
                    );
                } else {
                    $sheet->setCellValue($docCellCoord, 'Belum upload');
                }
            }

            $rowNum++;
        }

        // range('A', $highestCol) TIDAK aman dipakai di sini — PHP range() dengan
        // string cuma benar untuk huruf tunggal, rusak begitu kolom lewat 'Z'
        // (export ini gampang punya >26 kolom). Iterasi pakai index numerik.
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($i = 1; $i <= $highestColIndex; $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * @param list<object> $rows
     * @return array<int,list<object>>
     */
    private function groupBy(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->{$key}][] = $row;
        }
        return $out;
    }

    /**
     * @param list<object> $rows jg_form_jawaban rows
     * @return array<int,array<int,object>> pendaftaran_id => [field_id => jawaban]
     */
    private function groupByField(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->pendaftaran_id][(int) $row->field_id] = $row;
        }
        return $out;
    }

    /**
     * @param list<object> $rows jg_berkas rows
     * @return array<int,array<string,object>> pendaftaran_id => [tipe_berkas => berkas]
     */
    private function groupByTipe(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->pendaftaran_id][$row->tipe_berkas] = $row;
        }
        return $out;
    }

    /**
     * Format nilai jawaban sesuai tipe field (checkbox → gabung koma, wilayah →
     * nama lengkap). $field bisa null kalau gelombang pendaftaran ini tidak punya
     * field dengan nama_field tersebut (mis. beda gelombang, skema beda) — kembalikan
     * string kosong untuk kasus itu.
     *
     * @param object|null        $field          row jg_form_field, atau null kalau tidak ada
     * @param array<int,object>  $jawabanByField  field_id => jawaban (untuk SATU pendaftaran)
     */
    private function formatJawaban(?object $field, array $jawabanByField): string
    {
        if ($field === null) {
            return '';
        }

        $jawaban = $jawabanByField[(int) $field->id] ?? null;
        if (!$jawaban) {
            return '';
        }

        if ($jawaban->nilai_json) {
            $arr = json_decode($jawaban->nilai_json, true);
            return is_array($arr) ? implode(', ', $arr) : '';
        }

        if ($field->tipe === 'wilayah_autocomplete' && $jawaban->nilai_text !== '') {
            return $this->resolveWilayah($jawaban->nilai_text);
        }

        return (string) $jawaban->nilai_text;
    }

    private function resolveWilayah(string $kode): string
    {
        if (!isset($this->wilayahCache[$kode])) {
            $row = (new WilayahRepository())->findByKode($kode);
            $this->wilayahCache[$kode] = $row->nama_lengkap ?? $kode;
        }

        return $this->wilayahCache[$kode];
    }

    private function coordFor(int $colIndex, int $rowNum): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowNum;
    }
}
