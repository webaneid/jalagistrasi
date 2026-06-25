# Arsitektur Tahun Ajaran — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — diimplementasikan
**Author:** Webane Indonesia

---

## Konteks

Gap struktural ditemukan: `jg_gelombang.tahun_akademik` saat ini cuma **teks bebas** (VARCHAR), bukan entitas sendiri. Tidak ada jaminan konsistensi antar-gelombang, dan tidak bisa diagregasi reliable "semua gelombang di tahun ajaran X".

**Hierarki yang benar:** Tahun Ajaran (1) → Gelombang (banyak) → Pendaftar (banyak). Dokumen ini memperbaiki struktur tersebut.

---

## Keputusan (sudah dikonfirmasi)

| Keputusan | Pilihan |
|---|---|
| Kolom lama `tahun_akademik` | **Dihapus permanen** setelah migrasi (ALTER TABLE DROP COLUMN manual — `dbDelta` tidak bisa drop kolom) |
| Field Tahun Ajaran | Cukup **nama + status aktif/nonaktif** — tanggal mulai/selesai tetap milik Gelombang seperti sekarang, tidak duplikat di level Tahun Ajaran |
| Setting "Tahun Ajaran Aktif" di Pengaturan | **Dihapus** (bagian dari `arsitektur-identitas-institusi.md` yang baru dibangun) — digantikan flag `status='aktif'` di tabel baru, satu sumber kebenaran |

---

## Skema Database

### Tabel baru: `jg_tahun_ajaran`

```sql
CREATE TABLE jg_tahun_ajaran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'nonaktif',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nama (nama),
  KEY idx_status (status)
);
```

Format `nama` disarankan `YYYY/YYYY` (sama validasi seperti `tahun_akademik` gelombang sebelumnya), divalidasi di layer PHP (bukan DB constraint, konsisten dengan konvensi plugin ini — tidak ada FK/CHECK constraint di SQL).

`status`: hanya label penanda "aktif" untuk keperluan tampilan (headline halaman info publik, nanti kop surat PDF) — **tidak mengontrol** apakah gelombang di bawahnya bisa menerima pendaftaran (itu tetap ditentukan `jg_gelombang.status` + tanggal buka/tutup, tidak berubah).

### `jg_gelombang` — tambah kolom, hapus kolom lama

```sql
ALTER TABLE jg_gelombang ADD COLUMN tahun_ajaran_id BIGINT UNSIGNED DEFAULT NULL;
-- (backfill semua baris, lihat bagian Migrasi)
ALTER TABLE jg_gelombang DROP COLUMN tahun_akademik;
```

`tahun_ajaran_id` nullable di level DB (konsisten — plugin ini tidak pakai FK constraint), tapi **wajib diisi** di layer validasi `GelombangController` (tidak bisa simpan gelombang baru tanpa pilih tahun ajaran).

---

## Migrasi Data (Satu Kali, Otomatis)

Dijalankan via `Plugin::runMigrationsIfNeeded()` (pola sama dengan migrasi-migrasi sebelumnya — bump `DB_VERSION`):

1. `dbDelta()` — buat tabel `jg_tahun_ajaran`, tambah kolom `jg_gelombang.tahun_ajaran_id`.
2. Ambil semua nilai **distinct** `tahun_akademik` dari `jg_gelombang` yang sudah ada (mis. "2026/2027" dari Gelombang 1 yang sudah dibuat) → insert masing-masing ke `jg_tahun_ajaran` dengan `status='nonaktif'` (admin tandai aktif manual setelah migrasi — tidak menebak mana yang seharusnya aktif).
3. `UPDATE jg_gelombang g JOIN jg_tahun_ajaran ta ON ta.nama = g.tahun_akademik SET g.tahun_ajaran_id = ta.id` — backfill FK berdasarkan nama yang cocok.
4. Cek kolom `tahun_akademik` masih ada (`SHOW COLUMNS`) → kalau ada, `ALTER TABLE ... DROP COLUMN tahun_akademik` lewat `$wpdb->query()` manual (bukan `dbDelta`).

Idempotent — kalau migrasi sudah jalan (kolom sudah didrop), langkah ini di-skip otomatis di run berikutnya.

---

## Komponen yang Perlu Dibuat

| Komponen | File | Catatan |
|---|---|---|
| Migrasi | `src/Plugin.php`, `src/Installer.php` | Bump `DB_VERSION`, logic migrasi di atas |
| `TahunAjaranRepository` | `src/Repository/TahunAjaranRepository.php` | `findAll()`, `findAktif()`, `findById()`, `insert()`, `update()`, `delete()`, `countGelombang()` |
| `TahunAjaranController` (admin CRUD) | `src/Admin/TahunAjaranController.php` | Pola sama `ProgramStudiController` — tolak hapus kalau masih ada gelombang yang pakai |
| Menu admin | `src/Admin/AdminMenu.php` | Submenu "Tahun Ajaran" ditaruh **sebelum** "Gelombang" (urutan visual sesuai hierarki) |
| `GelombangRepository` | Revisi | `tahun_akademik` (text) → `tahun_ajaran_id` (FK); `findAll()`/`findAktifTerbuka()` JOIN ke `jg_tahun_ajaran` untuk ambil `nama` |
| `GelombangController` + form template | Revisi | Input teks "Tahun Akademik" → `<select>` pilih Tahun Ajaran yang sudah dibuat. Validasi: tolak simpan kalau belum ada Tahun Ajaran sama sekali ("buat Tahun Ajaran dulu") |
| `PengaturanController` | Revisi | Hapus field `jalagistrasi_tahun_ajaran_aktif` (dari `arsitektur-identitas-institusi.md`) |
| `InfoPendaftaranController` + template | Revisi | Headline tahun ajaran aktif ambil dari `TahunAjaranRepository::findAktif()`, bukan setting yang dihapus |
| `DashboardController` + template | Revisi | Tambah filter **Tahun Ajaran** (selain filter Gelombang yang sudah ada) — pilih tahun ajaran → tabel breakdown **per gelombang** di bawahnya (sesuai permintaan: "tahun ajaran sekian → ada berapa yang daftar di gelombang 1, gelombang 2") |

---

## Dampak ke Dokumen Lain

- `arsitektur-identitas-institusi.md` — field "Tahun Ajaran Aktif" yang didokumentasikan di sana **dibatalkan**, digantikan mekanisme di dokumen ini.
- `arsitektur-landing-publik.md` — sumber data tahun ajaran aktif berubah (dari setting ke tabel baru), tampilan/perilaku halaman tidak berubah.
- `arsitektur-dashboard-admin.md` — ditambah satu filter baru (Tahun Ajaran) di atas filter Gelombang yang sudah didokumentasikan.
- `arsitektur-overview.md` — tabel "Database — Ringkasan Tabel" perlu tambah baris `jg_tahun_ajaran`, dan deskripsi `jg_gelombang` perlu sebut FK ke tahun ajaran.

---

## Status Implementasi

Semua komponen di tabel "Komponen yang Perlu Dibuat" sudah dieksekusi (2026-06-25):

- `jg_tahun_ajaran` (tabel baru), `jg_gelombang.tahun_ajaran_id` (FK), kolom lama `tahun_akademik` **sudah didrop** lewat migrasi otomatis (`Plugin::migrateTahunAjaran()`, `DB_VERSION` bump ke `5`).
- `TahunAjaranRepository`, `TahunAjaranController` (CRUD), menu "Tahun Ajaran" ditaruh sebelum "Gelombang".
- `GelombangRepository`/`GelombangController`: form gelombang sekarang `<select>` pilih Tahun Ajaran (bukan input teks), validasi tolak simpan kalau belum ada Tahun Ajaran.
- **Strategi minim-risiko:** semua method `GelombangRepository` yang mengembalikan data gelombang (`findAll`, `findById`, `findAktifTerbuka`, `findByTahunAjaran` baru) JOIN ke `jg_tahun_ajaran` dan mengembalikan `tahun_akademik` sebagai **alias hasil JOIN** — sehingga 11 template yang sudah ada (`$gelombang->tahun_akademik`, dst) **tidak perlu diubah sama sekali**. Hanya titik input (form Gelombang) dan repository yang disentuh.
- `PendaftaranRepository`/`PendaftaranProdiRepository`: query statistik (`countTotal`, `countByStatusGrouped`, `findProdiTerpopuler`) ditambah parameter `$tahunAjaranId` untuk dashboard.
- `arsitektur-identitas-institusi.md`: field "Tahun Ajaran Aktif" **dihapus** dari Pengaturan, digantikan `TahunAjaranRepository::findAktif()`.
- `arsitektur-landing-publik.md` (`InfoPendaftaranController`): headline tahun ajaran aktif sekarang dari tabel baru.
- `arsitektur-dashboard-admin.md`: filter Tahun Ajaran ditambahkan di atas filter Gelombang — pilih Tahun Ajaran (Gelombang = Semua) menampilkan tabel breakdown "Pendaftar per Gelombang dalam Tahun Ajaran Ini".

---

## Yang Sengaja TIDAK Dibangun

- **Tanggal mulai/selesai di level Tahun Ajaran** — sesuai keputusan, tetap di level Gelombang saja.
- **Multi-tahun-ajaran aktif sekaligus** — `status='aktif'` tidak divalidasi unik di level DB (admin secara konvensi cuma tandai satu), tapi tidak ada blocking constraint. Kalau ke depan perlu strict satu-aktif, bisa ditambah validasi di `TahunAjaranController::handleSave()` (uncheck yang lain otomatis) — belum dibangun sekarang, dianggap belum perlu.
