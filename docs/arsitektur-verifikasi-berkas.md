# Arsitektur Verifikasi Berkas — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — disetujui & diimplementasikan
**Author:** Webane Indonesia

---

## Konteks

Setelah pendaftar upload dokumen di Step 3 (KTP, KK, Ijazah, Pas Foto, dll — lihat `arsitektur-database.md` untuk tabel `jg_tipe_berkas`), panitia perlu mekanisme untuk **menerima atau menolak tiap dokumen**, dengan alasan penolakan yang jelas agar pendaftar tahu apa yang harus diperbaiki.

Pertanyaan awal yang harus dijawab sebelum implementasi: *status apa yang berubah ketika satu dokumen ditolak — status dokumen itu sendiri, atau status besar pendaftaran (`jg_pendaftaran.status`)?*

---

## Keputusan: Dua Level Status yang Independen

| Level | Kolom | Nilai | Siapa yang mengubah |
|---|---|---|---|
| **Dokumen** (mikro) | `jg_berkas.status` | `pending` / `diterima` / `ditolak` | Panitia, per dokumen, kapan saja setelah upload |
| **Pendaftaran** (makro) | `jg_pendaftaran.status` | `StatusPendaftaran` enum (lihat `arsitektur-flow-pendaftaran.md`) | Panitia, manual, lewat form "Update Status" yang sudah ada |

**Keputusan eksplisit (dikonfirmasi ke user):** menerima/menolak satu dokumen **TIDAK** otomatis mengubah status besar pendaftaran. Admin tetap mengubah status besar secara manual lewat panel "Update Status" di halaman detail pendaftar. Status dokumen hanya menjadi *data pendukung* yang dilihat admin sebelum memutuskan transisi status besar.

Alasan keputusan ini:
- Admin bisa mencicil review dokumen (terima KTP hari ini, KK besok) tanpa memicu perubahan status besar yang prematur.
- Mencegah race condition: jika rejection otomatis memindahkan status besar, dan pada saat bersamaan dokumen lain juga sedang direview, status bisa "lompat" tidak sesuai ekspektasi panitia.
- Konsisten dengan prinsip *single source of truth untuk keputusan bisnis* — perubahan status besar adalah keputusan sadar panitia, bukan side-effect.

---

## Skema Database

Kolom-kolom berikut **sudah ada** di tabel `jg_berkas` sejak skema awal (`arsitektur-database.md`), namun baru dipakai sejak fitur ini:

```sql
status       VARCHAR(20)   NOT NULL DEFAULT 'pending',  -- pending | diterima | ditolak
catatan      TEXT          DEFAULT NULL,                 -- alasan, wajib diisi saat 'ditolak'
verified_at  DATETIME      DEFAULT NULL,
verified_by  BIGINT UNSIGNED DEFAULT NULL,                -- FK wp_users.ID (admin yang verifikasi)
```

Tidak ada migrasi tabel baru untuk fitur ini — hanya menambah logic di layer Repository/Controller.

---

## Perubahan State Machine

`src/Enum/StatusPendaftaran.php` — ditambah satu transisi:

```php
self::BerkasDiupload => [self::PembayaranDiupload, self::BerkasDitolak], // + BerkasDitolak
```

**Alasan:** alur asli (`arsitektur-overview.md`) memodelkan verifikasi berkas terjadi *setelah* tahap Pembayaran (`pembayaran_diupload → berkas_diverifikasi/berkas_ditolak`). Tapi Step 4 (Pembayaran) belum dibangun, dan panitia perlu bisa menandai status besar "Berkas Ditolak" begitu menemukan dokumen yang jelas bermasalah — tanpa menunggu pendaftar upload bukti bayar terlebih dahulu. Transisi `berkas_diupload → berkas_ditolak` ditambahkan supaya jalur ini tidak terblokir, tanpa mengubah jalur normal (`pembayaran_diupload → berkas_diverifikasi/berkas_ditolak`) yang akan dipakai setelah Step 4 selesai dibangun.

Transisi `berkas_ditolak → berkas_diupload` sudah ada sebelumnya — dipakai saat pendaftar upload ulang dokumen yang ditolak dan klik "Selesaikan Upload Berkas".

---

## Alur Admin (Verifikasi per Dokumen)

Lokasi: `/wp-admin/admin.php?page=jg-pendaftar&id=<id>` → section **"Dokumen Berkas"**.

Setiap dokumen yang sudah diupload menampilkan:
- Thumbnail (klik untuk lightbox preview — foto/PDF)
- Badge status: `Menunggu Verifikasi` (abu) / `✓ Diterima` (hijau) / `✕ Ditolak` (merah)
- Catatan penolakan (jika status `ditolak`)
- Tombol **Terima** — submit langsung, tidak perlu catatan
- Tombol **Tolak** — membuka textarea inline, catatan **wajib diisi**, baru bisa submit

Handler: `PendaftarController::handleVerifyBerkas()` (hook `admin_post_jg_verify_berkas`):
1. Cek `manage_options` + nonce `jg_verify_berkas`
2. Validasi `decision` ∈ `{diterima, ditolak, pending}`
3. Jika `ditolak` → wajib ada `catatan` (validasi server-side, bukan cuma `required` di HTML)
4. `BerkasRepository::updateVerifikasi()` — update `status`, `catatan`, `verified_at` (`current_time('mysql')`), `verified_by` (`get_current_user_id()`)
5. Redirect balik ke halaman detail

Status bisa diubah bolak-balik (`diterima` ⇄ `ditolak` ⇄ `pending`) — tidak ada state lock, karena admin mungkin perlu mengoreksi keputusan review sebelumnya.

---

## Alur Pendaftar (Melihat Hasil Verifikasi)

Dua tempat menampilkan status verifikasi ke pendaftar:

1. **`templates/frontend/berkas/upload.php`** (Step 3) — badge status + box merah berisi alasan penolakan, tepat di bawah kartu dokumen yang bersangkutan.
2. **`templates/frontend/detail/index.php`** (halaman detail pendaftaran, dashboard) — badge kecil di thumbnail grid + alasan penolakan ditampilkan di dalam modal preview.

**Re-upload dokumen yang ditolak:** memakai jalur upload yang sama (`handleUploadBerkasItem`) — `BerkasRepository::insert()` selalu set `status = 'pending'` untuk file baru, jadi begitu pendaftar upload ulang, dokumen otomatis kembali ke status "Menunggu Verifikasi", siap direview ulang oleh admin. Tidak perlu logic reset terpisah.

---

## Gap yang Ditemukan & Diperbaiki: Akses Halaman Upload Saat Status Besar Belum Berubah

Karena status dokumen independen dari status besar (lihat keputusan di atas), muncul celah: admin menolak satu dokumen, tapi **tidak mengubah status besar**, sehingga `jg_pendaftaran.status` tetap `berkas_diupload`. Halaman "Upload Berkas" (Step 3) dan halaman detail awalnya hanya mengizinkan akses/menampilkan tombol upload ulang berdasarkan status besar (`submitted` / `berkas_ditolak`) — pendaftar jadi **tidak punya jalan masuk** untuk upload ulang dokumen yang ditolak.

**Perbaikan:**
- `RegistrasiController::renderUploadBerkas()`, `PendaftaranController::handleUploadBerkasItem()`, `handleFinalizeBerkas()` — status `berkas_diupload` ditambahkan ke daftar status yang diizinkan akses, supaya halaman Step 3 tetap bisa dikunjungi ulang kapan saja sebelum tahap Pembayaran.
- `templates/frontend/detail/index.php` — dihitung `$adaBerkasDitolak` (cek langsung ke `jg_berkas.status`, bukan ke status besar). Jika ada dokumen ditolak:
  - Banner peringatan tampil terlepas dari status besar pendaftaran.
  - Tombol **"Upload Ulang"** muncul langsung di kartu dokumen yang bersangkutan (bukan cuma tombol generik di atas halaman).

---

## Yang Belum Dikerjakan (Out of Scope v1.0)

- **Verifikasi bukti pembayaran** — menunggu Step 4 (Pembayaran) dibangun. Pola yang sama (status/catatan per item) bisa dipakai ulang untuk `jg_pembayaran` nanti.
- **Notifikasi otomatis** ke pendaftar saat dokumen ditolak (email/WA) — saat ini pendaftar hanya tahu lewat dashboard, tidak ada push notification.
- **Agregasi otomatis status besar** (mis. auto-transisi ke `berkas_diverifikasi` saat semua dokumen wajib diterima) — sengaja tidak dibuat, lihat bagian Keputusan di atas.

---

## File yang Terlibat

| File | Peran |
|---|---|
| `src/Repository/BerkasRepository.php` | `updateVerifikasi()` |
| `src/Admin/PendaftarController.php` | `handleVerifyBerkas()` |
| `src/Enum/StatusPendaftaran.php` | Transisi `berkas_diupload → berkas_ditolak` |
| `src/Plugin.php` | Hook `admin_post_jg_verify_berkas` |
| `templates/admin/pendaftar/detail.php` | UI terima/tolak per dokumen |
| `templates/frontend/berkas/upload.php` | Badge + catatan untuk pendaftar (Step 3) |
| `templates/frontend/detail/index.php` | Badge + catatan untuk pendaftar (halaman detail) |
