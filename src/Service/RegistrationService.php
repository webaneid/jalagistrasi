<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Webane\Jalagistrasi\Repository\PendaftarRepository;

/**
 * Orchestrator proses registrasi akun pendaftar.
 * Validasi → buat WP user → buat profil jg_pendaftar → auto-login.
 */
final class RegistrationService
{
    public function __construct(
        private readonly PendaftarRepository $pendaftarRepository
    ) {}

    /**
     * Proses registrasi lengkap.
     *
     * @param array<string, string> $data Input mentah dari POST (belum disanitasi)
     * @return array{success: true, user_id: int}|array{success: false, errors: list<string>}
     */
    public function register(array $data): array
    {
        $sanitized = $this->sanitize($data);
        $errors    = $this->validate($sanitized);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // wp_insert_user menangani password hashing — tidak boleh hash manual sebelum ini.
        $userId = wp_insert_user([
            'user_login' => $sanitized['email'],
            'user_email' => $sanitized['email'],
            'display_name' => $sanitized['nama_lengkap'],
            'first_name'   => $sanitized['nama_lengkap'],
            'user_pass'    => $sanitized['password'],
            'role'         => 'pendaftar',
        ]);

        if (is_wp_error($userId)) {
            return [
                'success' => false,
                'errors'  => [$userId->get_error_message()],
            ];
        }

        try {
            $this->pendaftarRepository->insert((int) $userId, $sanitized['nomor_wa']);
        } catch (\RuntimeException $e) {
            // Rollback WP user agar tidak ada user tanpa profil pendaftar.
            wp_delete_user((int) $userId);

            return [
                'success' => false,
                'errors'  => [
                    __('Terjadi kesalahan saat menyimpan data. Silakan coba lagi.', 'jalagistrasi'),
                ],
            ];
        }

        return ['success' => true, 'user_id' => (int) $userId];
    }

    /**
     * Auto-login setelah registrasi berhasil.
     * Mengembalikan true jika login berhasil, false jika gagal.
     */
    public function autoLogin(string $email, string $password): bool
    {
        $credentials = [
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => false,
        ];

        $user = wp_signon($credentials, is_ssl());

        return !is_wp_error($user);
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function sanitize(array $data): array
    {
        return [
            'nama_lengkap'        => sanitize_text_field($data['nama_lengkap'] ?? ''),
            'email'               => sanitize_email($data['email'] ?? ''),
            'nomor_wa'            => sanitize_text_field($data['nomor_wa'] ?? ''),
            'password'            => $data['password'] ?? '',
            'konfirmasi_password' => $data['konfirmasi_password'] ?? '',
        ];
    }

    /**
     * @param array<string, string> $data Data yang sudah disanitasi
     * @return list<string> Daftar pesan error; kosong jika valid
     */
    private function validate(array $data): array
    {
        $errors = [];

        if (mb_strlen($data['nama_lengkap']) < 3) {
            $errors[] = __('Nama lengkap minimal 3 karakter.', 'jalagistrasi');
        }

        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = __('Format email tidak valid.', 'jalagistrasi');
        } elseif (email_exists($data['email'])) {
            $errors[] = __('Email sudah terdaftar.', 'jalagistrasi');
        }

        if (!preg_match('/^(\+62|62|0)[0-9]{8,13}$/', $data['nomor_wa'])) {
            $errors[] = __('Format nomor WhatsApp tidak valid.', 'jalagistrasi');
        } elseif ($this->pendaftarRepository->existsByNomorWa($data['nomor_wa'])) {
            $errors[] = __('Nomor WhatsApp sudah terdaftar.', 'jalagistrasi');
        }

        if (mb_strlen($data['password']) < 8) {
            $errors[] = __('Password minimal 8 karakter.', 'jalagistrasi');
        }

        if ($data['password'] !== $data['konfirmasi_password']) {
            $errors[] = __('Konfirmasi password tidak cocok.', 'jalagistrasi');
        }

        return $errors;
    }
}
