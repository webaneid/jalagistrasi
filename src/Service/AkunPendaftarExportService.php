<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Webane\Jalagistrasi\Repository\AkunPendaftarRepository;

/**
 * Export semua akun ber-role 'pendaftar' ke .xlsx — basis data kontak untuk
 * broadcast/promo email nanti (lihat docs/arsitektur-overview.md, menu
 * "Role Pendaftar"). Lebih simpel dari PendaftarExportService (tidak ada
 * kolom dinamis form builder — cuma data akun).
 */
class AkunPendaftarExportService
{
    public function build(string $statusFilter, string $search): Spreadsheet
    {
        $rows = (new AkunPendaftarRepository())->findAllForExport($statusFilter, $search);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Akun Pendaftar');

        $headers = ['No', 'Nama', 'Email', 'No. WhatsApp', 'NIK', 'NISN', 'Tanggal Daftar Akun', 'Status Keterlibatan'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1');
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->freezePane('A2');

        $rowNum = 2;
        $no = 1;
        foreach ($rows as $row) {
            $sheet->setCellValue([1, $rowNum], $no++);
            $sheet->setCellValue([2, $rowNum], $row->display_name);
            $sheet->setCellValue([3, $rowNum], $row->user_email);
            $sheet->setCellValue([4, $rowNum], $row->nomor_wa ?? '');
            $sheet->setCellValue([5, $rowNum], $row->nik ?? '');
            $sheet->setCellValue([6, $rowNum], $row->nisn ?? '');
            $sheet->setCellValue([7, $rowNum], date('d M Y H:i', strtotime($row->user_registered)));
            $sheet->setCellValue([8, $rowNum], $row->sudah_mendaftar ? 'Sudah Mendaftar' : 'Baru Bikin Akun');
            $rowNum++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
