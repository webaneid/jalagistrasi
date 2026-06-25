<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Enum;

/**
 * Tipe field yang didukung oleh form builder dinamis.
 * Nilai string ini yang tersimpan di kolom `tipe` tabel `jg_form_field`.
 */
enum TipeField: string
{
    case Text      = 'text';
    case Textarea  = 'textarea';
    case Number    = 'number';
    case Date      = 'date';
    case Email     = 'email';
    case Phone     = 'phone';
    case Nik       = 'nik';
    case Nisn      = 'nisn';
    case Select    = 'select';
    case Radio     = 'radio';
    case Checkbox  = 'checkbox';
    case FileUpload = 'file_upload';
    case WilayahAutocomplete = 'wilayah_autocomplete';

    public function label(): string
    {
        return match($this) {
            self::Text       => 'Teks Singkat',
            self::Textarea   => 'Teks Panjang',
            self::Number     => 'Angka',
            self::Date       => 'Tanggal',
            self::Email      => 'Email',
            self::Phone      => 'Nomor Telepon / WhatsApp',
            self::Nik        => 'NIK (16 digit)',
            self::Nisn       => 'NISN (10 digit)',
            self::Select     => 'Pilihan Dropdown',
            self::Radio      => 'Pilihan Tunggal (Radio)',
            self::Checkbox   => 'Pilihan Ganda (Checkbox)',
            self::FileUpload => 'Upload File',
            self::WilayahAutocomplete => 'Alamat Wilayah (Provinsi–Desa)',
        };
    }

    /**
     * Apakah tipe field ini memerlukan daftar opsi (options) di konfigurasi?
     */
    public function requiresOptions(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox], true);
    }

    /**
     * Apakah jawaban disimpan sebagai JSON (multi-value)?
     * Jika false, jawaban disimpan di kolom `nilai_text`.
     */
    public function isMultiValue(): bool
    {
        return $this === self::Checkbox;
    }

    /**
     * Apakah field ini menyimpan path file (bukan nilai teks biasa)?
     */
    public function isFileType(): bool
    {
        return $this === self::FileUpload;
    }

    /**
     * Validasi format nilai untuk tipe ini.
     * Mengembalikan true jika valid, false jika tidak.
     */
    public function validateFormat(string $value): bool
    {
        return match($this) {
            self::Email  => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            self::Nik    => (bool) preg_match('/^\d{16}$/', $value),
            self::Nisn   => (bool) preg_match('/^\d{10}$/', $value),
            self::Phone  => (bool) preg_match('/^(\+62|62|0)[0-9]{8,13}$/', $value),
            self::Number => is_numeric($value),
            self::Date   => (bool) \DateTime::createFromFormat('Y-m-d', $value),
            default      => true,
        };
    }
}
