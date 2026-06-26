<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Frontend;

use Webane\Jalagistrasi\Enum\StatusPendaftaran;
use Webane\Jalagistrasi\Repository\BerkasRepository;
use Webane\Jalagistrasi\Repository\GelombangRepository;
use Webane\Jalagistrasi\Repository\PendaftaranProdiRepository;
use Webane\Jalagistrasi\Repository\PendaftaranRepository;

/**
 * Halaman publik verifikasi QR — /verifikasi/<nomor>/<token>/. Lihat
 * docs/arsitektur-verifikasi-qr.md.
 *
 * Token (bukan nomor_pendaftaran saja) yang jadi kunci akses sebenarnya — nomor
 * urut bisa ditebak, token acak 32 hex char tidak. Dibandingkan dengan
 * hash_equals() (konstan-waktu, bukan ===) supaya tidak ada timing attack
 * untuk menebak token karakter demi karakter.
 */
final class VerifikasiController
{
    /**
     * Dipanggil dari Plugin::maybeRenderVerifikasi() — render lalu exit.
     */
    public function render(string $nomor, string $token): void
    {
        $pendaftaran = $this->cariValid($nomor, $token);

        if ($pendaftaran === null) {
            $this->loadTemplate('frontend/verifikasi/index', ['ditemukan' => false]);
            exit;
        }

        $gelombang    = (new GelombangRepository())->findById((int) $pendaftaran->gelombang_id);
        $wpUser       = get_userdata((int) $pendaftaran->user_id);
        $adaFoto      = (new BerkasRepository())->findByPendaftaranAndTipe((int) $pendaftaran->id, 'foto') !== null;
        $prodiPilihan = (new PendaftaranProdiRepository())->findByPendaftaran((int) $pendaftaran->id);

        $fotoUrl = $adaFoto
            ? add_query_arg([
                'action' => 'jg_verifikasi_foto',
                'nomor'  => $pendaftaran->nomor_pendaftaran,
                'token'  => $pendaftaran->verifikasi_token,
            ], admin_url('admin-ajax.php'))
            : '';

        $tahunAkademik = $gelombang?->tahun_akademik ?? '';

        // Nama gelombang sering ditulis admin sudah menyertakan tahun akademik
        // (mis. "Gelombang 1 2026/2027") — kalau begitu, jangan tampilkan dobel
        // sekarang setelah ada baris "Tahun Akademik" terpisah. Cuma buang dari
        // tampilan, data asli di DB tidak diubah.
        $gelombangNama = trim((string) ($gelombang?->nama ?? '—'));
        if ($tahunAkademik !== '') {
            $gelombangNama = trim(str_replace($tahunAkademik, '', $gelombangNama));
        }

        $this->loadTemplate('frontend/verifikasi/index', [
            'ditemukan'     => true,
            'pendaftaran'   => $pendaftaran,
            'namaLengkap'   => $wpUser ? $wpUser->display_name : '—',
            'gelombangNama' => $gelombangNama !== '' ? $gelombangNama : ($gelombang?->nama ?? '—'),
            'tahunAkademik' => $tahunAkademik !== '' ? $tahunAkademik : '—',
            'prodiPilihan'  => $prodiPilihan,
            'statusLabel'   => StatusPendaftaran::from($pendaftaran->status)->label(),
            'fotoUrl'       => $fotoUrl,
        ]);
        exit;
    }

    /**
     * AJAX publik (login TIDAK wajib — itu intinya, supaya bisa di-scan siapa
     * saja yang pegang QR fisik) untuk serve foto pendaftar. Hook:
     * wp_ajax_jg_verifikasi_foto / wp_ajax_nopriv_jg_verifikasi_foto.
     *
     * Proteksi sama dengan halaman utama: nomor + token harus cocok. Tanpa
     * token yang benar, foto tidak bisa diakses — bukan cuma butuh login.
     */
    public function handlePreviewFoto(): void
    {
        $nomor = sanitize_text_field(wp_unslash($_GET['nomor'] ?? ''));
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));

        $pendaftaran = $this->cariValid($nomor, $token);
        if ($pendaftaran === null) {
            wp_die('Forbidden', '', ['response' => 403]);
        }

        $berkas = (new BerkasRepository())->findByPendaftaranAndTipe((int) $pendaftaran->id, 'foto');
        if (!$berkas) {
            wp_die('Not found', '', ['response' => 404]);
        }

        $filePath = JG_UPLOAD_DIR . '/' . $berkas->file_path;
        if (!file_exists($filePath) || !is_file($filePath)) {
            wp_die('File not found', '', ['response' => 404]);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            wp_die('Forbidden', '', ['response' => 403]);
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . sanitize_file_name($berkas->file_name_original) . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

    /**
     * Cari pendaftaran by nomor lalu validasi token-nya. Selalu kembalikan null
     * untuk SEMUA kasus gagal (nomor tidak ada, token kosong, token salah) —
     * jangan bedakan pesan errornya, supaya tidak membantu orang nebak nomor
     * mana yang valid (info leak lewat respons beda).
     */
    private function cariValid(string $nomor, string $token): ?object
    {
        if ($nomor === '' || $token === '') {
            return null;
        }

        $pendaftaran = (new PendaftaranRepository())->findByNomor($nomor);

        if (!$pendaftaran || empty($pendaftaran->verifikasi_token)) {
            return null;
        }

        if (!hash_equals((string) $pendaftaran->verifikasi_token, $token)) {
            return null;
        }

        return $pendaftaran;
    }

    /**
     * @param array<string,mixed> $vars
     */
    private function loadTemplate(string $name, array $vars = []): void
    {
        $path = JG_PLUGIN_DIR . 'templates/' . $name . '.php';

        if (!file_exists($path)) {
            echo esc_html(sprintf('Template tidak ditemukan: %s', $name));
            return;
        }

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($vars, EXTR_SKIP);
        include $path;
    }
}
