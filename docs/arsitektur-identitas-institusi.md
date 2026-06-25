# Arsitektur Identitas Institusi (Logo, Kop Surat, Tahun Ajaran) — Plugin Jalagistrasi

**Tanggal:** 2026-06-25 (revisi — lihat catatan Tahun Ajaran di bawah)
**Status:** v1.0 — diimplementasikan
**Author:** Webane Indonesia

---

## Konteks

Sebelum fitur ekspor PDF (formulir pendaftar, dst — lihat `arsitektur-overview.md` §6, belum dibangun sama sekali) bisa dikerjakan, institusi butuh tempat menyimpan identitas resmi: logo, alamat, kontak, dan tahun ajaran aktif. Dokumen ini **hanya membangun setting-nya** — bukan fitur ekspor PDF itu sendiri (keputusan eksplisit, lihat `arsitektur-pembayaran.md` untuk pola dokumentasi serupa).

---

## Keputusan

| Keputusan | Pilihan |
|---|---|
| Scope | Hanya setting (Pengaturan) — fitur ekspor PDF yang akan memakai data ini dibangun terpisah nanti |
| Tahun ajaran | ~~Setting global terpisah~~ **DIBATALKAN** — lihat catatan revisi di bawah |
| Logo | File **publik** (bukan dokumen sensitif) — disimpan lewat WP Media Library standar, bukan private storage seperti KTP/KK |

> **Revisi (2026-06-25, sesudah dokumen ini awalnya ditulis):** field setting "Tahun Ajaran Aktif" yang tadinya direncanakan di sini **dibatalkan dan dihapus**. Ternyata `tahun_akademik` butuh jadi entitas sendiri (bukan teks bebas per-gelombang) supaya hierarki Tahun Ajaran → Gelombang → Pendaftar bisa diagregasi dengan benar untuk statistik. Lihat `arsitektur-tahun-ajaran.md` — "tahun ajaran aktif" sekarang ditentukan dari tabel `jg_tahun_ajaran` (`TahunAjaranRepository::findAktif()`), bukan dari `wp_options` di halaman ini. Setting lain di dokumen ini (logo, alamat, telp, email) **tidak terpengaruh**, tetap seperti rencana awal.

---

## Setting Baru di `PengaturanController`

`PengaturanController::SETTINGS` (const yang sudah ada, isinya saat ini: `nomor_prefix`, `nomor_seq_length`, `nama_institusi`) ditambah:

| Option key | Tipe | Keterangan |
|---|---|---|
| `jalagistrasi_logo_id` | int (attachment ID) | ID Media Library, bukan path langsung — pakai `wp_get_attachment_image_url()` saat ditampilkan |
| `jalagistrasi_alamat_institusi` | textarea | Alamat lengkap |
| `jalagistrasi_telp_institusi` | text | Nomor telepon/WhatsApp kontak resmi |
| `jalagistrasi_email_institusi` | text | Email kontak resmi |
| `jalagistrasi_tahun_ajaran_aktif` | text | Format bebas mis. `2026/2027` — **diisi manual admin**, tidak otomatis dari gelombang manapun |

**Catatan implementasi logo:** pakai `wp_enqueue_media()` + tombol "Pilih Logo" yang membuka WP Media Modal standar (pattern umum WP, bukan custom uploader) — paling sedikit kode, paling familiar untuk admin yang sudah biasa pakai WordPress.

**Tidak ada tabel/migrasi DB baru** — semua via `wp_options` (pola sama seperti setting yang sudah ada, pakai `get_option()`/`update_option()`).

---

## Validasi

- `jalagistrasi_logo_id` — kalau diisi, harus attachment image yang valid (`wp_attachment_is_image()`); kalau tidak valid, tolak simpan dengan error.
- `jalagistrasi_tahun_ajaran_aktif` — format disarankan `YYYY/YYYY` (sama seperti validasi `tahun_akademik` gelombang yang sudah ada), tapi **tidak wajib diisi** (boleh kosong di awal sebelum admin mengisi).
- Field lain (alamat, telp, email) — opsional, tidak ada validasi format ketat (alamat bebas, telp/email sekadar `sanitize_text_field`/`sanitize_email`).

---

## Pemakaian ke Depan (Tidak Dibangun Sekarang)

Sekadar dicatat agar jelas kenapa setting ini dibuat — **tidak diimplementasikan di iterasi ini**:
- Header/kop surat saat fitur ekspor PDF dibangun (`arsitektur-overview.md` §6).
- Kemungkinan ditampilkan juga di halaman info publik (`arsitektur-landing-publik.md`) — alamat/kontak institusi.

---

## Komponen yang Perlu Dibuat

| Komponen | File | Catatan |
|---|---|---|
| Tambah field setting | `src/Admin/PengaturanController.php` | Extend `SETTINGS`, render form, validasi |
| UI Media uploader | Render form pengaturan (inline, sesuai pola sekarang) | `wp_enqueue_media()` + JS singkat |

Tidak ada repository/tabel baru — murni `wp_options`.
