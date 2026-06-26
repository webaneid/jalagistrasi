# Arsitektur Kartu CAMABA & Verifikasi QR — Plugin Jalagistrasi

**Tanggal:** 2026-06-26
**Status:** v1.0 — sudah diimplementasikan, sudah diuji terhadap database lokal, belum dirilis (belum di-push/di-tag)
**Author:** Webane Indonesia

---

## 1. Latar Belakang

Setelah submit formulir pendaftaran, pendaftar dapat nomor pendaftaran. Ide: tambahkan **QR code** di atas nomor itu, yang bisa dipakai sebagai semacam "passkey" — discan panitia saat tes/ujian untuk verifikasi cepat siapa pendaftarnya, tanpa panitia perlu login ke sistem.

Istilah yang dipakai user: "barcode". Diklarifikasi di awal bahwa yang dimaksud secara teknis adalah **QR code** (barcode 1D tidak bisa menyimpan URL, QR bisa) — keputusan ini diambil di awal percakapan dan tidak berubah.

Nama fitur di sisi UI: **"Kartu CAMABA"** (bukan "Kartu Peserta" — sempat dipakai sebentar lalu direvisi user jadi CAMABA, akronim "Calon Mahasiswa Baru" yang lebih spesifik untuk konteks PMB perguruan tinggi). Tombol di dashboard: **"Tampilkan Kartu CAMABA"**.

---

## 2. Keputusan Keamanan: Token Acak, Bukan Nomor Urut

**Masalah yang diidentifikasi:** Nomor pendaftaran (`PMB-2026-0002`) berurutan dan mudah ditebak. Kalau halaman verifikasi publik (`/verifikasi/<nomor>/`) cuma mengandalkan nomor itu sebagai kunci akses, siapapun bisa enumerasi nomor urut dan melihat nama+foto pendaftar lain — bukan cuma yang scan QR sungguhan.

**Opsi yang dipertimbangkan (ditanyakan eksplisit ke user via pertanyaan terbuka):**
1. Terima risikonya — nama+foto levelnya jauh di bawah KTP/KK, wajar publik.
2. Tambah token rahasia acak, jangan andalkan nomor urut saja.

**Keputusan: opsi 2.** Kolom baru `jg_pendaftaran.verifikasi_token` (VARCHAR 64, isi: `bin2hex(random_bytes(16))` → 32 karakter hex) dibuat **sekali** saat submit pertama kali, tidak pernah berubah lagi (termasuk saat fitur "edit formulir" dipakai — lihat docs/arsitektur-frontend-pendaftaran.md §13, token tidak ikut di-regenerate di jalur edit).

**URL final:** `/verifikasi/<nomor_pendaftaran>/<token>/` — nomor tetap tampil (manusiawi, gampang dikenali kalau dicetak), tapi **token** yang jadi kunci akses sesungguhnya. Tanpa token yang cocok, server berlaku seolah nomor itu tidak pernah ada (pesan error generik "Data Tidak Ditemukan" — sengaja tidak dibedakan dari "nomor salah" vs "token salah", supaya tidak ada info leak yang membantu orang menebak nomor mana yang valid).

Perbandingan token pakai `hash_equals()`, bukan `===` — supaya konstan-waktu dan tidak bisa ditebak karakter-demi-karakter lewat timing attack.

---

## 3. Skema Database

Kolom baru di tabel `jg_pendaftaran` (lihat `src/Installer.php`):

```sql
verifikasi_token VARCHAR(64) DEFAULT NULL
```

Tidak ada UNIQUE KEY di kolom ini — probabilitas collision dari `random_bytes(16)` astronomis kecil, tidak perlu constraint tambahan.

**Migrasi (`Plugin::migrateVerifikasiToken()`, DB_VERSION 6→7):**
- Backfill token untuk SEMUA baris `jg_pendaftaran` yang `verifikasi_token` masih NULL/kosong (termasuk data lama yang sudah ada sebelum fitur ini dibuat).
- `flush_rewrite_rules()` dipanggil di migrasi yang sama — perlu, karena rewrite rule baru (lihat §4) tidak otomatis ke-cache ulang tanpa ini. **Catatan penting yang sempat ketemu nyata saat development**: migrasi sempat sudah jalan (DB_VERSION sudah di angka 7) SEBELUM rewrite rule-nya selesai ditulis — jadi flush itu tidak menangkap rule yang baru. Solusinya saat itu: hapus manual option `rewrite_rules` di `wp_options`, WordPress otomatis bikin ulang di request berikutnya. Kalau pola ini terulang (nambah rewrite rule baru di versi DB yang SUDAH pernah jalan), perlu langkah manual serupa — bukan otomatis lagi karena migrasi cuma jalan sekali per versi.

---

## 4. Routing: Rewrite Rule, Bukan WP Page

Beda dari halaman lain di plugin ini (`jg_registrasi`, `jg_dashboard`, `jg_info_pendaftaran` — semua shortcode di WP Page tertentu, ID-nya disimpan di `wp_options`), halaman verifikasi **tidak** memakai WP Page sama sekali. Alasan: URL-nya butuh 2 segmen path dinamis (`<nomor>/<token>`), yang tidak cocok dengan model "satu shortcode di satu Page tetap".

**Mekanisme** (`src/Plugin.php`):
```php
add_rewrite_rule(
    '^verifikasi/([^/]+)/([^/]+)/?$',
    'index.php?jg_verifikasi_nomor=$matches[1]&jg_verifikasi_token=$matches[2]',
    'top'
);
```
+ `query_vars` filter daftarkan `jg_verifikasi_nomor`/`jg_verifikasi_token`, + hook `template_redirect` yang langsung panggil `VerifikasiController::render()` dan `exit` — bypass total template hierarchy WordPress (tidak ada `the_content()`, tidak ada Page/Post di baliknya).

**Konsekuensi:** karena tidak lewat `the_content()`, halaman ini tidak bisa pakai `templates/auth/page-blank.php` (yang didesain untuk WP Page). Template `templates/frontend/verifikasi/index.php` jadi satu-satunya template di plugin ini yang menulis kerangka HTML lengkap sendiri (`<!DOCTYPE html>` sampai `</html>`) — tetap panggil `wp_head()`/`wp_body_open()`/`wp_footer()` supaya script tema/plugin lain (analytics, font) tetap jalan, sama prinsipnya dengan `page-blank.php`.

---

## 5. QR Code: SVG, Bukan PNG

**Library:** `endroid/qr-code` (composer), via `src/Service/QrCodeService.php`.

**Keputusan: output SVG, bukan PNG.** PNG butuh extension PHP `GD` atau `Imagick` terpasang di server — banyak hosting shared (termasuk yang dipakai client ini) tidak terjamin punya itu. SVG murni teks/XML, jalan di environment manapun tanpa dependency tambahan. Dikirim sebagai data URI (`data:image/svg+xml;base64,...`) langsung di-embed di `<img src="...">`, tidak perlu endpoint file terpisah untuk QR-nya sendiri.

QR berisi URL lengkap `/verifikasi/<nomor>/<token>/` — bukan cuma token atau nomor saja.

---

## 6. Foto: Endpoint Publik dengan Proteksi Token yang Sama

Foto pendaftar (`tipe_berkas = 'foto'`, lihat `DefaultTipeBerkasSeeder`) disimpan di direktori upload privat (sama seperti KTP/KK — proteksi `.htaccess deny from all`). Untuk ditampilkan di halaman verifikasi (publik, tanpa login), dibuat endpoint baru:

```
wp_ajax_jg_verifikasi_foto / wp_ajax_nopriv_jg_verifikasi_foto
→ VerifikasiController::handlePreviewFoto()
```

**Proteksinya SAMA PERSIS dengan halaman utama** — endpoint ini re-validasi nomor+token (`hash_equals`) sebelum serve file, BUKAN cuma cek "user login atau tidak". Jadi tahu URL foto saja tidak cukup — harus tahu token yang valid juga. Ini konsisten dengan keputusan §2: token adalah satu-satunya kunci akses ke seluruh informasi pendaftar di alur ini (halaman + foto), bukan dua mekanisme proteksi berbeda yang bisa lupa disinkronkan.

---

## 7. Konten Halaman Verifikasi (Iterasi Final)

Field yang ditampilkan, urutan final setelah beberapa kali revisi user:

1. Foto (atau ikon placeholder kalau belum upload foto)
2. Nama lengkap + nomor pendaftaran
3. Badge "✓ Terverifikasi"
4. **Gelombang**
5. **Tahun Akademik** (dipisah dari Gelombang — awalnya digabung jadi satu baris, direvisi user supaya terpisah)
6. **Status** (label dari `StatusPendaftaran::label()`, mis. "Dijadwalkan Tes" — sempat dihapus saat fokus ke 3 field di atas, lalu user minta dikembalikan)
7. **Pilihan Prodi** (daftar bernomor, dari `PendaftaranProdiRepository::findByPendaftaran()`)
8. QR code (ditampilkan ulang, untuk kasus halaman ini sendiri yang mau ditunjukkan ke orang lain)

**Dedup nama Gelombang vs Tahun Akademik:** kalau admin menamai gelombang dengan tahun ajaran sudah ikut di dalamnya (mis. `"Gelombang 1 2026/2027"`), setelah field Tahun Akademik dipisah jadi baris sendiri, nama itu akan terlihat dobel. Fix: `VerifikasiController::render()` membuang substring tahun akademik dari `$gelombangNama` SEBELUM dikirim ke template (cuma di tampilan, data asli `jg_gelombang.nama` di database tidak diubah):

```php
$gelombangNama = trim(str_replace($tahunAkademik, '', $gelombangNama));
```

Field yang SAMA (Gelombang, Tahun Akademik, QR) juga dipakai ulang di halaman "Pendaftaran Berhasil" (`templates/frontend/form/sukses.php`) yang di-redesign bersamaan jadi dark-glass theme (sebelumnya masih desain lama/default, belum pernah disentuh sejak redesign besar sebelumnya).

---

## 8. Tombol di Dashboard

`templates/frontend/dashboard/index.php` — kartu hero (pendaftaran aktif), di samping tombol CTA utama:

```php
<?php if (!empty($p->verifikasi_token)) : ?>
    <a href="<?php echo esc_url($kartuPesertaUrl); ?>" target="_blank" rel="noopener" class="jg-btn jg-btn--outline">
        Tampilkan Kartu CAMABA
    </a>
<?php endif; ?>
```

Hanya tampil kalau `verifikasi_token` sudah ada (artinya sudah pernah submit minimal sekali) — draft belum punya token, tombol tidak muncul untuk draft.

---

## 9. Ringkasan File

| File | Peran |
|---|---|
| `src/Installer.php` | Kolom `verifikasi_token` di DDL `jg_pendaftaran` |
| `src/Plugin.php` | `DB_VERSION` 6→7, `migrateVerifikasiToken()`, rewrite rule, query vars, `template_redirect` hook, hook AJAX foto |
| `src/Repository/PendaftaranRepository.php` | `findByNomor()`, `updateVerifikasiToken()` |
| `src/Service/PendaftaranService.php` | Generate token saat submit pertama (sekali, tidak pernah lagi) |
| `src/Service/QrCodeService.php` (baru) | `generateSvgDataUri()` — wrapper `endroid/qr-code` |
| `src/Frontend/VerifikasiController.php` (baru) | `render()` (halaman publik), `handlePreviewFoto()` (AJAX publik), `cariValid()` (lookup + validasi token, dipakai dua-duanya) |
| `templates/frontend/verifikasi/index.php` (baru) | Halaman Kartu CAMABA — kerangka HTML lengkap sendiri (lihat §4) |
| `templates/frontend/form/sukses.php` | Redesign dark-glass + QR code |
| `templates/frontend/dashboard/index.php` | Tombol "Tampilkan Kartu CAMABA" di hero |
| `composer.json` | `endroid/qr-code` |

---

## 10. Yang Belum Dikerjakan / Dipertimbangkan ke Depan

- Tidak ada rate-limiting di endpoint `/verifikasi/...` maupun `handlePreviewFoto()` — kalau suatu saat ada percobaan brute-force token (sangat tidak praktis mengingat 32 hex char = 128-bit space, tapi tetap dicatat sebagai gap teoretis, bukan ancaman realistis saat ini).
- Belum ada cara admin "cabut" / regenerate token kalau QR seorang pendaftar bocor/disebar tanpa sengaja (mis. di-screenshot dan disebar publik). Saat ini token permanen seumur pendaftaran itu.
- Halaman verifikasi belum dicoba load langsung di browser sungguhan (cuma diuji lewat skrip PHP CLI terhadap database — logic SQL/PHP-nya sudah terverifikasi benar, tapi belum visual check).
