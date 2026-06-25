<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Enum;

/**
 * Status machine pendaftaran mahasiswa.
 *
 * Nilai string ini yang tersimpan di kolom `status` tabel `jg_pendaftaran`.
 * Perubahan nilai di sini harus diikuti migrasi DB untuk data yang sudah ada.
 */
enum StatusPendaftaran: string
{
    case Draft                 = 'draft';
    case Submitted             = 'submitted';
    case BerkasDiupload        = 'berkas_diupload';
    case PembayaranDiupload    = 'pembayaran_diupload';
    case BerkasDiverifikasi    = 'berkas_diverifikasi';
    case BerkasDitolak         = 'berkas_ditolak';
    case PembayaranDitolak     = 'pembayaran_ditolak';
    case DijadwalkanTes        = 'dijadwalkan_tes';
    case DiumumkanLulus        = 'diumumkan_lulus';
    case DiumumkanTidakLulus   = 'diumumkan_tidak_lulus';
    case DaftarUlang           = 'daftar_ulang';
    case Selesai               = 'selesai';
    case GagalDaftarUlang      = 'gagal_daftar_ulang';

    /**
     * Label yang ditampilkan ke user.
     */
    public function label(): string
    {
        return match($this) {
            self::Draft               => 'Belum Disubmit',
            self::Submitted           => 'Formulir Disubmit',
            self::BerkasDiupload      => 'Berkas Diupload',
            self::PembayaranDiupload  => 'Bukti Bayar Diupload',
            self::BerkasDiverifikasi  => 'Berkas Diverifikasi',
            self::BerkasDitolak       => 'Berkas Ditolak — Perlu Revisi',
            self::PembayaranDitolak   => 'Pembayaran Ditolak — Perlu Revisi',
            self::DijadwalkanTes      => 'Dijadwalkan Tes',
            self::DiumumkanLulus      => 'Lulus Seleksi',
            self::DiumumkanTidakLulus => 'Tidak Lulus Seleksi',
            self::DaftarUlang         => 'Proses Daftar Ulang',
            self::Selesai             => 'Selesai',
            self::GagalDaftarUlang    => 'Gagal Daftar Ulang',
        };
    }

    /**
     * Status yang membolehkan pendaftar masih mengedit formulir biodata — sampai
     * sebelum dokumen diverifikasi panitia (lihat docs/arsitektur-frontend-pendaftaran.md #13).
     */
    public function isEditable(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Submitted,
            self::BerkasDiupload,
            self::BerkasDitolak,
        ], true);
    }

    /**
     * Status terminal — tidak ada transisi lebih lanjut.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Selesai,
            self::DiumumkanTidakLulus,
            self::GagalDaftarUlang,
        ], true);
    }

    /**
     * Transisi status yang valid dari state ini.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Draft               => [self::Submitted],
            self::Submitted           => [self::BerkasDiupload],
            self::BerkasDiupload      => [self::BerkasDiverifikasi, self::BerkasDitolak],
            self::BerkasDitolak       => [self::BerkasDiupload],
            self::BerkasDiverifikasi  => [self::PembayaranDiupload],
            self::PembayaranDiupload  => [self::DijadwalkanTes, self::PembayaranDitolak],
            self::PembayaranDitolak   => [self::PembayaranDiupload],
            self::DijadwalkanTes      => [self::DiumumkanLulus, self::DiumumkanTidakLulus],
            self::DiumumkanLulus      => [self::DaftarUlang],
            self::DaftarUlang         => [self::Selesai, self::GagalDaftarUlang],
            self::DiumumkanTidakLulus,
            self::Selesai,
            self::GagalDaftarUlang    => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
