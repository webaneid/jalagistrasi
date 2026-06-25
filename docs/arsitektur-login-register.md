# Arsitektur Halaman Login & Register — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — diimplementasikan
**Author:** Webane Indonesia

---

## Konteks

Sebelumnya, login pendaftar memakai `wp-login.php` standar WordPress (tidak ada form custom — `LoginHandler` hanya menangani redirect *setelah* login, bukan tampilan form-nya). Registrasi sudah punya form sendiri (`[jg_registrasi]`) tapi terpisah dari login, dan masih pakai header/footer tema standar. User minta "wow factor": satu halaman dengan tab Masuk/Daftar, background gradient gelap berbasis warna brand, desain glassmorphism, tanpa header/footer tema, tanpa font baru.

---

## Keputusan

| Keputusan | Pilihan |
|---|---|
| Login custom vs skin wp-login.php | **Form custom sendiri** (`wp_signon()` dipanggil manual) — wp-login.php tidak bisa diskin dengan baik tanpa hack berat; form sendiri lebih konsisten dengan arsitektur shortcode yang sudah ada |
| Satu halaman dua tab vs dua halaman | **Satu halaman, tab Alpine.js** — sesuai referensi UI, dan secara teknis lebih simpel (satu shortcode, satu route) |
| Header/footer tema | **Dihilangkan** lewat `template_include` filter — `wp_head()`/`wp_footer()` tetap jalan (skrip WP core, font tema, plugin lain tidak terganggu), cuma `header.php`/`footer.php` tema yang dilewati |
| Gradient & warna | `linear-gradient(135deg, ...)` 4 titik (gelap → terang aksen → gelap lagi → paling gelap) — rasio campur-ke-hitam (`ColorPaletteGenerator::mixTowardBlack()`) diturunkan dari contoh referensi user, diterapkan ke `jalagistrasi_warna_brand` (lihat `arsitektur-color-palette.md`) supaya ikut berubah kalau brand di-custom, bukan warna hardcoded |
| Font | **Tidak ada font baru** — halaman tetap memuat `wp_head()` sehingga font dari tema aktif tetap dipakai (cuma markup tema yang dilewati, bukan asset-nya) |
| Field form | **Tetap field yang sudah ada** (Nama Lengkap, Email, Nomor WhatsApp, Password) — UI referensi yang diberikan user (field "Pilih Organisasi", "Alumni Pondok Modern Gontor", dst) hanya dipakai sebagai referensi **tampilan**, bukan konten, sesuai instruksi eksplisit user |

---

## Implementasi

| Komponen | File | Catatan |
|---|---|---|
| Helper warna untuk gradient | `src/Service/ColorPaletteGenerator.php` | + `toRgbString()` (untuk `rgba()`), `mixTowardBlack()` (base gradient gelap) |
| Render gabungan + handler login | `src/Frontend/RegistrasiController.php` | `handleSubmitLogin()` (baru, pakai `wp_signon()`), `renderFormAuth()` (gantikan `renderFormRegistrasi()`), `handleSubmitRegistrasi()` direvisi panggil `renderFormAuth()` |
| Redirect setelah login | `RegistrasiController::handleSubmitLogin()` | Pendaftar → dashboard, staff/admin → `wp-admin` (logika sama dengan `LoginHandler::redirectAfterLogin()`, tapi dipanggil manual karena `wp_signon()` langsung tidak memicu filter `login_redirect` milik wp-login.php) |
| Template gabungan | `templates/auth/login-register.php` | Tab Masuk/Daftar (Alpine `x-data`), gradient radial 3-layer + base gelap (computed dari warna brand), kartu glass (`backdrop-filter:blur()`) |
| Template halaman kosong | `templates/auth/page-blank.php` | `wp_head()`/`wp_footer()` tetap dipanggil, `header.php`/`footer.php` tema dilewati |
| Filter `template_include` | `src/Plugin.php::maybeUseBlankTemplate()` | Hanya aktif di halaman `jalagistrasi_page_registrasi` |
| Redirect ke halaman login custom | `PendaftaranController::loginUrl()`, `RegistrasiController::shortcodeDashboard()` | Semua `wp_login_url()` lama diganti — supaya konsisten mengarah ke halaman custom, bukan wp-login.php |
| Template lama dihapus | `templates/auth/form-registrasi.php` | Digantikan total oleh `login-register.php` |

---

## Yang Sengaja TIDAK Dibangun

- **Reset password custom** — tetap pakai flow native WordPress (`wp_lostpassword_url()` → `wp-login.php?action=lostpassword`). Membangun ulang ini di luar scope permintaan saat ini.
- **Login via Nomor WhatsApp** — sistem ini login identifier-nya **email** (sudah final, lihat `arsitektur-auth.md`). Referensi UI yang diberikan user menampilkan "Email atau Nomor WhatsApp" tapi itu konten dari sistem lain, tidak dipakai di sini.
- **Override `login_url` filter secara global** — wp-login.php asli tetap bisa diakses langsung (mis. admin yang hafal URL-nya); plugin ini hanya mengarahkan **link-link miliknya sendiri** ke halaman custom, tidak memaksa seluruh WordPress.
