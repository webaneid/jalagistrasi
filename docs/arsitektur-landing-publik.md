# Arsitektur Halaman Informasi Pendaftaran (Publik) — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — diimplementasikan (tahun ajaran aktif kini dari `arsitektur-tahun-ajaran.md`, bukan setting Pengaturan)
**Author:** Webane Indonesia

---

## Konteks

Saat ini tidak ada halaman publik (tanpa login) yang menjelaskan ke calon mahasiswa: apa itu PMB, gelombang apa yang sedang dibuka, dan bagaimana alur pendaftarannya. Satu-satunya halaman publik adalah form registrasi langsung (`[jg_registrasi]`) — orang yang belum yakin mau daftar tidak punya tempat baca-baca dulu.

---

## Keputusan

| Keputusan | Pilihan |
|---|---|
| Pembuatan halaman | **Otomatis** — sama pola dengan halaman Registrasi & Dashboard yang sudah ada (`Installer::createRequiredPages()` + shortcode) |
| Multi-gelombang aktif | **Tampilkan semua** gelombang yang aktif & dalam periode buka — bukan cuma satu yang terbaru, konsisten dengan dukungan multi-gelombang yang sudah ada di sistem |
| Konten alur pendaftaran | Generic, hardcoded sesuai pipeline yang sudah didokumentasikan (`arsitektur-overview.md` §"Alur Bisnis Pendaftaran") — bukan dynamic per-gelombang |

---

## Konten Halaman

### 1. Hero
- Nama institusi (`jalagistrasi_nama_institusi`, sudah ada)
- Tahun ajaran aktif (`jalagistrasi_tahun_ajaran_aktif`, setting baru — lihat `arsitektur-identitas-institusi.md`)
- Headline: "Pendaftaran Mahasiswa Baru — Tahun Ajaran {tahun_ajaran_aktif}"
- Tombol CTA besar:
  - **Belum login:** "Daftar Sekarang" → halaman Registrasi (`[jg_registrasi]`)
  - **Sudah login (pendaftar):** "Ke Dashboard Saya" → halaman Dashboard (lebih relevan daripada minta daftar ulang)

### 2. Gelombang yang Sedang Dibuka
Query `GelombangRepository::findAktifTerbuka()` (sudah ada, tidak perlu method baru) — tampilkan **semua** hasilnya sebagai kartu:
- Nama gelombang + tahun akademik (`gelombang.tahun_akademik`, bisa beda dari `tahun_ajaran_aktif` global kalau admin belum sinkron — ditampilkan apa adanya per gelombang)
- Tanggal buka – tutup
- Biaya pendaftaran (`gelombang.biaya_pendaftaran`, kalau > 0 — kalau 0 tidak usah ditampilkan)
- Tombol "Daftar Sekarang" per kartu (mengarah ke registrasi; pemilihan gelombang spesifik tetap terjadi setelah login, sesuai flow yang sudah ada — `pilih-gelombang` route)

**Empty state:** kalau `findAktifTerbuka()` kosong → tampilkan pesan "Pendaftaran belum dibuka. Pantau halaman ini untuk info terbaru." — tombol CTA utama disembunyikan, ganti jadi info kontak (alamat/telp/email institusi, dari setting `arsitektur-identitas-institusi.md`) agar calon mahasiswa tetap bisa tanya-tanya.

### 3. Alur Pendaftaran
8 langkah, generic, hardcoded teks (bukan query dinamis) — diambil dari pipeline yang sudah disepakati:

```
1. Buat Akun
2. Isi Formulir Pendaftaran
3. Upload Dokumen Persyaratan (KTP, KK, Ijazah, Pas Foto, dll)
4. Verifikasi oleh Panitia
5. Upload Bukti Pembayaran
6. Tes / Seleksi
7. Pengumuman Hasil
8. Daftar Ulang
```

Ditampilkan sebagai daftar bernomor sederhana (bukan dokumen/tipe berkas spesifik per gelombang — supaya tidak perlu update halaman ini tiap admin ubah konfigurasi tipe berkas).

### 4. Kontak / Footer
Alamat, telp, email institusi (dari setting baru di `arsitektur-identitas-institusi.md`) — kalau belum diisi admin, section ini disembunyikan (bukan tampil kosong).

---

## Implementasi

| Komponen | File | Catatan |
|---|---|---|
| Controller baru | `src/Frontend/InfoPendaftaranController.php` | `shortcodeInfoPendaftaran(): string` — read-only, tidak butuh login |
| Shortcode baru | `[jg_info_pendaftaran]` | Didaftarkan di `Plugin::registerShortcodes()` |
| Halaman otomatis | `Installer::createRequiredPages()` | Tambah entry baru: option `jalagistrasi_page_info`, slug `informasi-pendaftaran`, shortcode `[jg_info_pendaftaran]` |
| Template baru | `templates/frontend/info-pendaftaran/index.php` | Pola visual sama dengan template frontend lain yang sudah ada (Tailwind class yang **sudah terverifikasi ada** di `assets/css/app.css` — lihat catatan di bawah) |
| Asset loading | `Plugin::enqueueFrontendAssets()` | Tambah `'jg_info_pendaftaran'` ke `$pluginShortcodes` |

**Catatan penting (pelajaran dari sesi sebelumnya):** karena `assets/css/app.css` adalah build Tailwind non-JIT, dipakai **hanya class yang sudah terbukti ada** di file itu (terverifikasi berkali-kali sepanjang pembangunan plugin ini — mis. `bg-brand-600`, `text-white`, `rounded-2xl`, `shadow-sm`, dst) atau inline `style="..."` untuk warna/spacing baru yang belum pasti. Tidak menebak nama class Tailwind standar.

---

## Yang Sengaja TIDAK Dibangun

- **Editor konten bebas untuk admin** (WYSIWYG custom per section) — konten di-generate otomatis dari data sistem (gelombang aktif) + teks alur yang hardcoded. Kalau admin mau ubah teks alur, edit langsung di file template (developer task), bukan dari UI admin.
- **Halaman beda per gelombang** — satu halaman info untuk semua gelombang aktif, tidak ada halaman detail terpisah per gelombang.
- **SEO/meta tag khusus** — di luar scope, pakai default WordPress/tema aktif.
