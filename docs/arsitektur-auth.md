# Arsitektur Auth & Registrasi — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — disetujui sebelum implementasi
**Author:** Webane Indonesia

---

## Konteks

Sistem autentikasi sepenuhnya menggunakan infrastruktur native WordPress (`wp_insert_user`, `wp_signon`, `wp_set_current_user`, nonce, capability). Tidak ada session management atau password hashing custom — terlalu berisiko untuk data pribadi mahasiswa.

---

## Komponen yang Dibangun di Sesi Ini

| Komponen | File | Tanggung Jawab |
|---|---|---|
| Role & capability check | `src/Auth/RoleManager.php` | Helper cek role/capability aktif user |
| Login redirect + WP-admin guard | `src/Auth/LoginHandler.php` | Redirect setelah login, blokir pendaftar dari wp-admin |
| Repository data pendaftar | `src/Repository/PendaftarRepository.php` | Query jg_pendaftar (cek duplikat WA, insert) |
| Orchestrator registrasi | `src/Service/RegistrationService.php` | Validasi → buat WP user → buat row jg_pendaftar → auto-login |
| Shortcode controller | `src/Frontend/RegistrasiController.php` | `[jg_registrasi]` dan `[jg_dashboard]` |
| Template form registrasi | `templates/auth/form-registrasi.php` | HTML form, Tailwind, Alpine |
| Template dashboard | `templates/dashboard/index.php` | Stub: selamat datang + status pendaftaran |

---

## Alur Registrasi

```
User buka halaman /daftar/
   ↓
[jg_registrasi] shortcode → RegistrasiController::renderForm()
   ↓
User isi form: Nama, Email, No. WA, Password, Konfirmasi Password
   ↓
Submit POST → RegistrasiController::handleSubmit()
   ↓
Validasi nonce (wp_verify_nonce)
   ↓
RegistrationService::register()
   ├─ Sanitasi semua input
   ├─ Validasi format: email, nomor WA, password min 8 karakter
   ├─ Cek email unik → email_exists()
   ├─ Cek nomor WA unik → PendaftarRepository::existsByNomorWa()
   ├─ Password === konfirmasi
   ├─ wp_insert_user() → $user_id
   ├─ wp_set_role('pendaftar')
   ├─ PendaftarRepository::insert($user_id, $nomor_wa)
   └─ wp_signon() → redirect ke halaman dashboard
```

**Jika ada error:** form ditampilkan ulang dengan pesan error yang spesifik (tapi tidak membocorkan info: misal "email sudah terdaftar" — ini aman karena form registrasi publik, bukan login).

---

## Alur Login & Redirect

```
User login via /wp-login.php (native WordPress)
   ↓
Hook: filter 'login_redirect'
   ↓
LoginHandler::redirectAfterLogin()
   ├─ Role 'pendaftar'     → redirect ke halaman dashboard plugin
   ├─ Role 'panitia_pmb'   → redirect ke wp-admin
   ├─ Role 'admin_pmb'     → redirect ke wp-admin
   └─ Role lain            → perilaku default WordPress
```

**Blokir pendaftar dari wp-admin:**

```
Hook: action 'admin_init'
   ↓
LoginHandler::blockPendaftarFromAdmin()
   ├─ Jika current_user_can('pendaftar') + bukan DOING_AJAX
   └─ wp_redirect(dashboard_url) + exit
```

---

## Halaman WordPress yang Dibuat Otomatis

Dibuat oleh `Installer::createRequiredPages()` saat aktivasi. ID disimpan di `wp_options`.

| Option key | Slug | Shortcode |
|---|---|---|
| `jalagistrasi_page_registrasi` | `daftar` | `[jg_registrasi]` |
| `jalagistrasi_page_dashboard` | `dashboard-pmb` | `[jg_dashboard]` |

Jika halaman sudah ada (re-aktivasi), tidak dibuat ulang.

---

## Field Registrasi (v1)

| Field | Validasi | Catatan |
|---|---|---|
| Nama Lengkap | required, min 3 karakter | `sanitize_text_field` |
| Email | required, format email, unik | `sanitize_email` + `email_exists()` |
| Nomor WhatsApp | required, format `\+62 / 62 / 0` + 8–13 digit, unik | `sanitize_text_field` + query `jg_pendaftar` |
| Password | required, min 8 karakter | tidak pernah disimpan plain, langsung ke `wp_insert_user` |
| Konfirmasi Password | harus sama dengan Password | dibandingkan di PHP, tidak disimpan |

NIK dan NISN **tidak ada di form registrasi** — diisi saat mengisi formulir pendaftaran (bagian form builder). Kolom `jg_pendaftar.nik` dan `jg_pendaftar.nisn` nullable saat insert awal.

---

## Keamanan

| Mekanisme | Implementasi |
|---|---|
| CSRF | `wp_nonce_field('jg_registrasi')` + `wp_verify_nonce()` sebelum proses apapun |
| XSS output | Semua output di template via `esc_html()`, `esc_attr()`, `esc_url()` |
| SQL injection | Semua query via `$wpdb->prepare()` — tidak ada string concatenation SQL |
| Password | `wp_insert_user()` — WordPress menangani hashing (phpass) |
| Enumeration | Pesan error tidak membedakan "email tidak ada" vs "password salah" di login |
| WP-admin guard | `admin_init` hook + capability check — pendaftar tidak bisa masuk wp-admin |

---

## Keputusan yang Dibuat

| Keputusan | Alasan |
|---|---|
| Pakai `/wp-login.php` native, bukan custom login page | Keamanan — WP native sudah handle brute force protection, cookie security. Membuat login page sendiri menambah attack surface tanpa manfaat nyata di v1. |
| Halaman dibuat via `wp_insert_post()` di aktivasi | Familiar bagi admin WordPress, bisa dipindah/edit sesuai kebutuhan tema kampus |
| Auto-login setelah registrasi | UX — pendaftar tidak perlu login ulang setelah daftar. Wajar untuk pendaftaran publik. |
| Tidak ada email verifikasi di v1 | Sudah disepakati di arsitektur-overview. Duplikat WA dan email dicegah di DB level. |

---

## Hasil Implementasi

**Tanggal:** 2026-06-25
**Status:** Selesai — 12/12 syntax check passed

### File yang dibuat / diubah

| File | Status |
|---|---|
| `src/Auth/RoleManager.php` | Baru |
| `src/Auth/LoginHandler.php` | Baru |
| `src/Repository/PendaftarRepository.php` | Baru |
| `src/Service/RegistrationService.php` | Baru |
| `src/Frontend/RegistrasiController.php` | Baru |
| `templates/auth/form-registrasi.php` | Baru |
| `templates/dashboard/index.php` | Baru (stub) |
| `src/Plugin.php` | Diperbarui — shortcodes + enqueue + login hooks |
| `src/Installer.php` | Diperbarui — `createRequiredPages()` |

### Halaman WordPress yang dibuat otomatis saat aktivasi ulang

| Slug | Option key | Shortcode |
|---|---|---|
| `/daftar/` | `jalagistrasi_page_registrasi` | `[jg_registrasi]` |
| `/dashboard-pmb/` | `jalagistrasi_page_dashboard` | `[jg_dashboard]` |

### Catatan implementasi

- `RegistrasiController` menggunakan `extract()` untuk inject template variables. Ini aman karena `$vars` dikontrol penuh oleh kode internal, bukan user input mentah. Komentar phpcs inline ditambahkan untuk menjelaskan alasan.
- `RegistrationService::register()` melakukan rollback `wp_delete_user()` jika insert ke `jg_pendaftar` gagal — mencegah user orphan tanpa profil pendaftar.
- Asset CSS/JS hanya dienqueue di halaman yang benar-benar memuat shortcode plugin (`has_shortcode()` check di `enqueueFrontendAssets()`).
- Halaman dashboard menggunakan `wp_safe_redirect()`, bukan `wp_redirect()` — lebih aman karena membatasi redirect ke domain yang sama.
