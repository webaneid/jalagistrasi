<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

/**
 * Menangani upload berkas sensitif ke direktori privat.
 * File disimpan di luar WP Media Library dan diproteksi .htaccess.
 */
final class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
    ];

    /**
     * Validasi file (sebelum dipindah). Tidak menyentuh filesystem.
     *
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return list<string> Daftar error, kosong jika valid
     */
    public function validate(array $file, int $maxSizeKb, string $label): array
    {
        $errors = [];

        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return $errors; // tidak diupload — caller yang memutuskan wajib/tidak
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = sprintf('%s: Gagal diupload (kode error: %d).', $label, $file['error']);
            return $errors;
        }

        // Validasi ukuran
        $maxBytes = $maxSizeKb * 1024;
        if ($file['size'] > $maxBytes) {
            $errors[] = sprintf(
                '%s: Ukuran file (%s) melebihi batas %s KB.',
                $label,
                number_format($file['size'] / 1024, 0) . ' KB',
                $maxSizeKb
            );
        }

        // Validasi mime type dari header file (bukan ekstensi)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            $errors[] = sprintf(
                '%s: Tipe file tidak diizinkan (%s). Hanya JPEG, PNG, dan PDF.',
                $label,
                $mimeType
            );
        }

        return $errors;
    }

    /**
     * Pindahkan file yang sudah divalidasi ke direktori privat.
     * Mengembalikan array info berkas untuk disimpan ke jg_berkas.
     *
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return array{file_path:string,file_name_original:string,file_name_stored:string,file_size:int,mime_type:string}
     * @throws \RuntimeException jika file tidak bisa dipindah
     */
    public function store(array $file, int $pendaftaranId, string $fieldNama): array
    {
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $ext      = self::ALLOWED_MIME_TYPES[$mimeType] ?? 'bin';

        $subDir   = JG_UPLOAD_DIR . '/' . $pendaftaranId;
        if (!is_dir($subDir)) {
            wp_mkdir_p($subDir);
        }

        $storedName = sprintf(
            '%s_%d_%s.%s',
            sanitize_key($fieldNama),
            time(),
            wp_generate_password(8, false),
            $ext
        );

        $destination = $subDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException(
                sprintf('Gagal memindahkan file untuk field "%s".', $fieldNama)
            );
        }

        return [
            'file_path'          => $pendaftaranId . '/' . $storedName,
            'file_name_original' => sanitize_file_name($file['name']),
            'file_name_stored'   => $storedName,
            'file_size'          => (int) $file['size'],
            'mime_type'          => $mimeType,
        ];
    }
}
