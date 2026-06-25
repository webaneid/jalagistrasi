# Arsitektur Database — Plugin Jalagistrasi

**Tanggal:** 2026-06-24
**Diperbarui:** 2026-06-24 (rev 2 — NIK/NISN di jg_pendaftar, unified berkas, NISN API research)
**Status:** ⚠️ STALE — skema di dokumen ini sudah jauh ketinggalan, lihat catatan di bawah sebelum membaca lebih lanjut
**Author:** Webane Indonesia

---

> ## ⚠️ Dokumen Ini Sudah Tidak Akurat (per 2026-06-25)
>
> Skema DDL di bawah ini adalah rancangan **awal** sebelum beberapa revisi besar. Jangan jadikan acuan tanpa cross-check ke sumber yang lebih baru:
>
> - **`jg_gelombang`** — kolom `tahun_akademik` (VARCHAR) di dokumen ini **sudah tidak ada**. Diganti `tahun_ajaran_id` (FK) + kolom baru `biaya_pendaftaran`. Lihat `arsitektur-tahun-ajaran.md` dan `arsitektur-pembayaran.md`.
> - **`jg_pendaftaran`** — ada kolom baru `kode_unik_pembayaran` yang belum tercatat di sini. Lihat `arsitektur-pembayaran.md`.
> - **`jg_pembayaran`** — skema di dokumen ini (`jenis`, `berkas_id`, `status`) **sudah direvisi total**, lihat skema baru di `arsitektur-pembayaran.md`.
> - **`jg_berkas`** — status verifikasi per dokumen (`pending`/`diterima`/`ditolak`) sudah aktif dipakai, lihat `arsitektur-verifikasi-berkas.md`.
> - **Tabel yang belum tercatat di dokumen ini sama sekali:** `jg_tahun_ajaran`, `jg_tipe_berkas`, `jg_rekening_bank`.
>
> **Sumber kebenaran skema terkini:** tabel ringkasan di `arsitektur-overview.md` (bagian "Database — Ringkasan Tabel") + DDL aktual di `src/Installer.php`. Dokumen ini dipertahankan sebagai catatan sejarah keputusan awal (lihat bagian Trade-off & alasan desain di bawah, yang sebagian besar masih relevan), bukan referensi skema yang dipakai.

---

## Konteks

Dokumen ini mendefinisikan seluruh skema custom table yang dibutuhkan plugin Jalagistrasi. Semua tabel ini melengkapi tabel native WordPress (`wp_users`, `wp_options`, `wp_posts`) — bukan menggantikannya.

---

## Prinsip Desain Database

1. **Semua tabel menggunakan prefix ganda**: `{wp_prefix}jg_` — misalnya jika prefix WordPress adalah `wp_`, tabel menjadi `wp_jg_gelombang`. Ini menghindari konflik dengan plugin lain.
2. **Storage engine: InnoDB** — untuk dukungan foreign key integrity dan transaction.
3. **Character set: `utf8mb4`** — mendukung karakter Unicode penuh termasuk emoji. Collation: `utf8mb4_unicode_ci`.
4. **Minimum MySQL 5.7 / MariaDB 10.2** — diperlukan untuk dukungan kolom JSON native.
5. **Status field: `VARCHAR(50)` bukan `ENUM`** — dbDelta WordPress tidak reliable dengan kolom ENUM (bisa trigger false-positive ALTER TABLE). Validasi status dilakukan di layer PHP via backed enum `StatusPendaftaran`.
6. **Money: `BIGINT UNSIGNED` dalam satuan Rupiah bulat** — tidak pernah `DECIMAL` untuk uang di sistem ini karena tidak ada operasi pecahan desimal. Menghindari floating-point error.
7. **`created_at` dan `updated_at` di semua tabel utama** — untuk audit trail ringan.
8. **Migrasi via `dbDelta()`** — skema didefinisikan di `Installer.php` dan dijalankan saat aktivasi atau update plugin.

---

## Opsi yang Dipertimbangkan

### Opsi A: Data pendaftar sebagai Custom Post Type
- **Ditolak** — CPT tidak dirancang untuk query skala ribuan row dengan filter multi-kolom. `WP_Query` dengan `meta_query` bersarang akan sangat lambat tanpa index yang proper. Tidak cocok untuk ekspor massal.

### Opsi B: Custom Table + EAV untuk semua data
- **Diadopsi sebagian** — Custom table untuk entitas utama (pendaftaran, gelombang, prodi). EAV (`jg_form_field` + `jg_form_jawaban`) **hanya untuk data dinamis dari form builder**. Data yang perlu di-query/filter sering (status, nomor WA, gelombang) tetap di kolom terstruktur.

### Opsi C: Satu tabel besar dengan kolom JSON untuk semua jawaban
- **Ditolak** — Kolom JSON tidak bisa diindex secara efisien untuk filter/sort di laporan. Backup/restore lebih rumit.

---

## Skema Tabel

### Tabel 1: `jg_gelombang`

Menyimpan setiap gelombang pendaftaran yang dibuat admin.

```sql
CREATE TABLE {prefix}jg_gelombang (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama         VARCHAR(200)    NOT NULL,
    tahun_akademik VARCHAR(20)   NOT NULL,          -- format: "2026/2027"
    tanggal_buka DATETIME        NOT NULL,
    tanggal_tutup DATETIME       NOT NULL,
    max_pilihan_prodi TINYINT UNSIGNED NOT NULL DEFAULT 2,  -- dikonfigurasi per gelombang
    status       VARCHAR(20)     NOT NULL DEFAULT 'nonaktif', -- 'aktif', 'nonaktif', 'selesai'
    created_by   BIGINT UNSIGNED NOT NULL,          -- FK wp_users.ID
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    INDEX idx_status (status),
    INDEX idx_tanggal (tanggal_buka, tanggal_tutup)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Catatan:**
- `max_pilihan_prodi` menggantikan nilai hardcoded. Default 2 sesuai formulir baku.
- Status `selesai` diset admin secara manual atau otomatis setelah `tanggal_tutup` terlewat.
- Tidak ada FK ke `wp_users` di level DB (WordPress convention — FK enforced di PHP).

---

### Tabel 2: `jg_program_studi`

Daftar program studi yang tersedia untuk dipilih pendaftar.

```sql
CREATE TABLE {prefix}jg_program_studi (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama      VARCHAR(200)    NOT NULL,
    kode      VARCHAR(20)     NOT NULL,     -- singkatan unik, mis. "TI", "MAN"
    deskripsi TEXT,
    kuota     INT UNSIGNED    NOT NULL DEFAULT 0,   -- 0 = tidak dibatasi
    status    VARCHAR(20)     NOT NULL DEFAULT 'aktif',  -- 'aktif', 'nonaktif'
    urutan    SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- urutan tampil di dropdown
    created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kode (kode),
    INDEX idx_status_urutan (status, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Tabel 3: `jg_pendaftar`

Profil dasar pendaftar pada level user (bukan per-pendaftaran). Satu baris per user WordPress yang mendaftar sebagai pendaftar.

```sql
CREATE TABLE {prefix}jg_pendaftar (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,    -- FK wp_users.ID
    nomor_wa   VARCHAR(20)     NOT NULL,    -- divalidasi unik saat registrasi
    nik        VARCHAR(16),                 -- NIK 16 digit; unik per orang; nullable saat pertama daftar
    nisn       VARCHAR(10),                 -- NISN 10 digit; unik per siswa; nullable jika tidak punya
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_id  (user_id),
    UNIQUE KEY uq_nomor_wa (nomor_wa),
    UNIQUE KEY uq_nik      (nik),
    UNIQUE KEY uq_nisn     (nisn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Mengapa NIK dan NISN di sini (bukan hanya di `jg_form_jawaban`)?**

Admin perlu bisa mencari/filter pendaftar berdasarkan NIK atau NISN (misalnya untuk pelaporan ke pusat, atau untuk klarifikasi data). Query via EAV (`jg_form_jawaban`) untuk ini akan mahal. Dengan kolom terstruktur di `jg_pendaftar`, query `WHERE nik = '...'` langsung hit UNIQUE index — O(1).

NIK dan NISN juga disimpan di `jg_form_jawaban` sebagai jawaban field inti (untuk ditampilkan di form), tapi `jg_pendaftar` adalah **source of truth** untuk keperluan sistem/admin. Keduanya harus sinkron saat pendaftar mengisi/update form.

**Mengapa tabel terpisah (bukan `wp_usermeta`)?**

`wp_usermeta` tidak mendukung UNIQUE constraint di level database. Tabel ini kecil (satu row per pendaftar) dan tidak menimbulkan overhead signifikan.

---

### Tabel 4: `jg_pendaftaran`

Record pendaftaran utama. Satu pendaftar bisa punya beberapa row di sini (per gelombang berbeda).

```sql
CREATE TABLE {prefix}jg_pendaftaran (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            BIGINT UNSIGNED NOT NULL,   -- FK wp_users.ID
    gelombang_id       BIGINT UNSIGNED NOT NULL,   -- FK jg_gelombang.id
    nomor_pendaftaran  VARCHAR(50)     NOT NULL,   -- generated: "PMB-2026-001234"
    status             VARCHAR(50)     NOT NULL DEFAULT 'draft',
    catatan_panitia    TEXT,
    submitted_at       DATETIME,                   -- NULL selama masih draft
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nomor_pendaftaran (nomor_pendaftaran),
    UNIQUE KEY uq_user_gelombang (user_id, gelombang_id),  -- 1 user = 1 pendaftaran per gelombang
    INDEX idx_gelombang_status (gelombang_id, status),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Nilai status yang valid** (divalidasi PHP enum `StatusPendaftaran`):

| Nilai | Keterangan |
|---|---|
| `draft` | Formulir belum disubmit |
| `submitted` | Formulir disubmit pendaftar |
| `berkas_diupload` | Dokumen sudah diupload |
| `pembayaran_diupload` | Bukti transfer sudah diupload |
| `berkas_diverifikasi` | Panitia approve semua berkas |
| `berkas_ditolak` | Panitia reject, pendaftar harus revisi |
| `dijadwalkan_tes` | Jadwal tes sudah ditetapkan |
| `diumumkan_lulus` | Dinyatakan lulus seleksi |
| `diumumkan_tidak_lulus` | Dinyatakan tidak lulus |
| `daftar_ulang` | Dalam proses daftar ulang |
| `selesai` | Daftar ulang selesai dikonfirmasi |
| `gagal_daftar_ulang` | Batas waktu daftar ulang terlewat |

---

### Tabel 5: `jg_pendaftaran_prodi`

Pilihan program studi per pendaftaran. Jumlah baris maksimal per `pendaftaran_id` dibatasi oleh `jg_gelombang.max_pilihan_prodi` — divalidasi di PHP, bukan di DB.

```sql
CREATE TABLE {prefix}jg_pendaftaran_prodi (
    id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    pendaftaran_id    BIGINT UNSIGNED  NOT NULL,  -- FK jg_pendaftaran.id
    program_studi_id  BIGINT UNSIGNED  NOT NULL,  -- FK jg_program_studi.id
    urutan            TINYINT UNSIGNED NOT NULL,  -- 1 = pilihan pertama, 2 = kedua, dst.
    PRIMARY KEY (id),
    UNIQUE KEY uq_pendaftaran_urutan  (pendaftaran_id, urutan),         -- tidak boleh 2 pilihan dengan urutan sama
    UNIQUE KEY uq_pendaftaran_prodi   (pendaftaran_id, program_studi_id), -- tidak boleh pilih prodi yang sama 2x
    INDEX idx_program_studi_id (program_studi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Tabel 6: `jg_form_field`

Definisi field formulir. Satu gelombang punya snapshot skema-nya sendiri (terisolasi).

```sql
CREATE TABLE {prefix}jg_form_field (
    id           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    gelombang_id BIGINT UNSIGNED   NOT NULL,       -- FK jg_gelombang.id
    nama_field   VARCHAR(100)      NOT NULL,        -- machine name: huruf kecil + underscore, e.g. "nama_lengkap"
    label        VARCHAR(200)      NOT NULL,        -- label tampil di form
    tipe         VARCHAR(50)       NOT NULL,        -- lihat enum TipeField di bawah
    is_required  TINYINT(1)        NOT NULL DEFAULT 0,
    is_core      TINYINT(1)        NOT NULL DEFAULT 0,  -- 1 = field inti, tidak bisa dihapus admin
    urutan       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    konfigurasi  JSON,                              -- options, validation rules, conditional logic
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY uq_gelombang_nama (gelombang_id, nama_field),  -- nama_field unik per gelombang
    INDEX idx_gelombang_urutan (gelombang_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Nilai `tipe` yang valid** (PHP enum `TipeField`):

| Tipe | Keterangan | Konfigurasi JSON |
|---|---|---|
| `text` | Input teks satu baris | `{min_length, max_length, pattern}` |
| `textarea` | Input teks multi-baris | `{min_length, max_length, rows}` |
| `number` | Input angka | `{min, max, step}` |
| `date` | Tanggal (date picker) | `{min_date, max_date}` |
| `email` | Email (validasi format) | — |
| `phone` | Nomor telepon/HP | `{format}` |
| `nik` | NIK (validasi 16 digit numerik) | — |
| `nisn` | NISN (validasi 10 digit numerik) | — |
| `select` | Dropdown pilih satu | `{options: [{value, label}]}` |
| `radio` | Pilih satu dengan tampilan radio button | `{options: [{value, label}]}` |
| `checkbox` | Pilih banyak | `{options: [{value, label}], min_checked, max_checked}` |
| `file_upload` | Upload file | `{allowed_mime_types: [], max_size_kb}` |

**Struktur `konfigurasi` untuk conditional logic:**
```json
{
  "conditional": {
    "action": "show",
    "rules": [
      { "field": "jenis_kelamin", "operator": "equals", "value": "perempuan" }
    ]
  }
}
```

**Field inti yang selalu dibuat saat gelombang dibuat** (`is_core = 1`):
- `nama_lengkap` (tipe: `text`)
- `email` (tipe: `email`)
- `nik` (tipe: `nik`)
- `nisn` (tipe: `nisn`)
- `nomor_wa` (tipe: `phone`)

---

### Tabel 7: `jg_form_jawaban`

Jawaban pendaftar untuk setiap field dinamis. Pola EAV (Entity-Attribute-Value).

```sql
CREATE TABLE {prefix}jg_form_jawaban (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pendaftaran_id BIGINT UNSIGNED NOT NULL,  -- FK jg_pendaftaran.id
    field_id       BIGINT UNSIGNED NOT NULL,  -- FK jg_form_field.id
    nilai_text     TEXT,                      -- untuk tipe single-value (text, date, select, radio, dll)
    nilai_json     JSON,                      -- untuk tipe multi-value (checkbox) atau complex
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pendaftaran_field (pendaftaran_id, field_id),
    INDEX idx_pendaftaran_id (pendaftaran_id),
    INDEX idx_field_id (field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Catatan performa EAV:**

EAV punya kelemahan inherent: query "cari semua pendaftar di mana field X bernilai Y" memerlukan JOIN ke `jg_form_jawaban` yang bisa lambat di data besar. Mitigasi untuk v1:

1. Untuk laporan/ekspor: query ambil semua `pendaftaran_id` dari `jg_pendaftaran` (dengan filter status/gelombang yang terindeks), lalu batch-load jawaban per pendaftaran. Jangan JOIN langsung di satu query untuk ribuan row.
2. Untuk v2 jika performa laporan menjadi masalah: implementasi **materialized export table** yang di-rebuild on-demand (trigger-based denormalization).

---

### Tabel 8: `jg_berkas`

**Unified file storage** — menampung semua file yang diupload dalam sistem: dokumen identitas, ijazah, foto, maupun bukti transfer pembayaran. Satu tabel untuk semua, dibedakan via kolom `tipe_berkas`.

**Tidak menggunakan `wp_posts` attachment** — file disimpan di lokasi privat, tidak boleh diakses via URL publik.

```sql
CREATE TABLE {prefix}jg_berkas (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pendaftaran_id      BIGINT UNSIGNED NOT NULL,   -- FK jg_pendaftaran.id
    tipe_berkas         VARCHAR(50)     NOT NULL,   -- lihat daftar nilai valid di bawah
    file_path           VARCHAR(500)    NOT NULL,   -- path relatif dari WP_CONTENT_DIR/jalagistrasi-uploads/
    file_name_original  VARCHAR(255)    NOT NULL,   -- nama file asli dari user
    file_name_stored    VARCHAR(255)    NOT NULL,   -- nama file yang disimpan (randomized, no extension guessing)
    file_size           INT UNSIGNED    NOT NULL,   -- ukuran dalam bytes
    mime_type           VARCHAR(100)    NOT NULL,
    status              VARCHAR(20)     NOT NULL DEFAULT 'pending',  -- 'pending', 'approved', 'rejected'
    catatan             TEXT,                       -- catatan verifikasi dari panitia
    uploaded_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at         DATETIME,
    verified_by         BIGINT UNSIGNED,            -- FK wp_users.ID
    PRIMARY KEY (id),
    INDEX idx_pendaftaran_id (pendaftaran_id),
    INDEX idx_tipe_status (tipe_berkas, status),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Nilai `tipe_berkas` yang dikenal sistem:**

| Nilai | Keterangan | Diverifikasi oleh |
|---|---|---|
| `ktp` | KTP pendaftar | verifikator_berkas |
| `kk` | Kartu Keluarga | verifikator_berkas |
| `ijazah` | Ijazah / SKHUN | verifikator_berkas |
| `foto` | Pas foto 2x3 | verifikator_berkas |
| `bukti_bayar_pendaftaran` | Bukti transfer biaya pendaftaran | panitia_pmb |
| `bukti_bayar_daftar_ulang` | Bukti transfer biaya daftar ulang | panitia_pmb |

Nilai lain bisa ditambahkan admin via konfigurasi plugin tanpa perubahan skema tabel.

**Strategi private file storage:**
- File disimpan di: `{WP_CONTENT_DIR}/jalagistrasi-uploads/{pendaftaran_id}/{randomized_filename}`
- Direktori dilindungi dengan `.htaccess`:
  ```
  Options -Indexes
  deny from all
  ```
- Akses file via endpoint plugin: `/?jg_action=unduh_berkas&id={berkas_id}&nonce={nonce}`
- Endpoint melakukan capability check sebelum serve file via `readfile()`
- `file_name_stored` menggunakan nama acak (UUID) — mencegah path traversal dan URL guessing

Detail implementasi → `arsitektur-berkas-media.md`.

---

### Tabel 9: `jg_pembayaran`

Record pembayaran biaya pendaftaran dan daftar ulang. File bukti transfer **tidak** disimpan di sini — disimpan di `jg_berkas` (unified file storage) dengan `tipe_berkas = 'bukti_bayar_*'`, lalu `berkas_id` di sini menjadi pointer-nya.

```sql
CREATE TABLE {prefix}jg_pembayaran (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pendaftaran_id BIGINT UNSIGNED NOT NULL,   -- FK jg_pendaftaran.id
    jenis          VARCHAR(20)     NOT NULL,   -- 'pendaftaran', 'daftar_ulang'
    jumlah         BIGINT UNSIGNED NOT NULL,   -- dalam satuan Rupiah (integer)
    berkas_id      BIGINT UNSIGNED,            -- FK jg_berkas.id; NULL sebelum bukti diupload
    status         VARCHAR(20)     NOT NULL DEFAULT 'menunggu_pembayaran',
    catatan        TEXT,
    verified_at    DATETIME,
    verified_by    BIGINT UNSIGNED,            -- FK wp_users.ID
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pendaftaran_id (pendaftaran_id),
    INDEX idx_berkas_id (berkas_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Nilai status pembayaran:**

| Nilai | Keterangan |
|---|---|
| `menunggu_pembayaran` | Record dibuat, pendaftar belum upload bukti |
| `bukti_diupload` | Pendaftar sudah upload bukti transfer |
| `verified` | Panitia konfirmasi pembayaran diterima |
| `rejected` | Bukti ditolak (tidak valid/tidak sesuai) |

---

### Tabel 10: `jg_status_history`

Audit trail setiap perubahan status pendaftaran. Tidak pernah didelete — ini adalah log permanen.

```sql
CREATE TABLE {prefix}jg_status_history (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pendaftaran_id BIGINT UNSIGNED NOT NULL,   -- FK jg_pendaftaran.id
    status_lama    VARCHAR(50),                -- NULL jika ini record pertama (insert)
    status_baru    VARCHAR(50)     NOT NULL,
    actor_user_id  BIGINT UNSIGNED NOT NULL,   -- FK wp_users.ID — siapa yang trigger perubahan
    catatan        TEXT,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pendaftaran_id (pendaftaran_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Diagram Relasi (ERD Ringkas)

```
wp_users (native WP)
   │
   ├──[1:1]── jg_pendaftar (profil: nomor_wa + NIK + NISN — semua UNIQUE)
   │
   └──[1:N]── jg_pendaftaran ──[N:1]── jg_gelombang
                   │
                   ├──[1:N]── jg_pendaftaran_prodi ──[N:1]── jg_program_studi
                   │
                   ├──[1:N]── jg_form_jawaban ──[N:1]── jg_form_field ──[N:1]── jg_gelombang
                   │
                   ├──[1:N]── jg_berkas ◄──────────────────────┐
                   │              (unified: dokumen + bukti bayar)  │
                   ├──[1:N]── jg_pembayaran ──[N:1 nullable]────┘
                   │              (FK berkas_id → jg_berkas.id)
                   └──[1:N]── jg_status_history
```

---

## Strategi Migrasi (dbDelta)

Semua DDL didefinisikan di `src/Installer.php` dalam method `createTables()`. Proses:

1. Plugin diaktivasi → `register_activation_hook` → `Installer::activate()`
2. `Installer::createTables()` membangun string SQL sesuai format dbDelta
3. `dbDelta($sql)` dipanggil — fungsi ini create table baru atau add/alter kolom yang belum ada
4. Versi schema disimpan di `wp_options` sebagai `jalagistrasi_db_version`
5. Saat update plugin → `plugins_loaded` hook → cek versi → jalankan migrasi jika versi berubah

**Aturan penulisan SQL untuk dbDelta (WAJIB diikuti):**
- Setiap kolom pada baris sendiri
- Dua spasi antara nama kolom/key dan tipe/definisi
- `PRIMARY KEY` ditulis sebagai baris terpisah, bukan `inline` di kolom
- Tidak ada `FOREIGN KEY` constraint di SQL (WordPress convention — tidak di-enforce di DB level)
- Tidak ada `ENUM` — gunakan `VARCHAR`

---

## Riset: NISN API Publik (Kemdikbud/Kemendikdasmen)

**Tanggal riset:** 2026-06-24

**Kesimpulan: API resmi/terdokumentasi tidak ada.**

Portal resmi saat ini: `nisn.data.kemendikdasmen.go.id` (URL lama `nisn.data.kemdikbud.go.id` nonaktif setelah kementerian berganti nama menjadi Kemendikdasmen). Portal ini adalah aplikasi web berbasis CodeIgniter dengan endpoint seperti `index.php/Cindex/formcaribynama/`. Tidak ada dokumentasi API yang dipublikasikan.

**Opsi yang ada:**
1. **Reverse-engineer endpoint web** — bisa dilakukan via HTTP POST ke endpoint CodeIgniter. Risiko: tidak resmi, bisa berubah kapanpun tanpa pemberitahuan, berpotensi melanggar ToS, tidak ada SLA uptime.
2. **Tidak integrasikan, validasi format saja** — NISN 10 digit numerik bisa divalidasi format di PHP tanpa API call. Admin tetap bisa cross-check manual ke portal Kemdikbud.
3. **Integrasi resmi** — memerlukan MoU/perjanjian kerjasama dengan Kemendikdasmen. Tidak feasible untuk v1.

**Keputusan v1: validasi format saja.** Arsitektur dirancang extensible untuk integrasi verifikasi di v2.

**Pola arsitektur yang diadopsi — `NisnVerifierInterface`:**

```php
namespace Webane\Jalagistrasi\Service\Verification;

interface NisnVerifierInterface
{
    public function verify(string $nisn, string $namaSiswa): VerificationResult;
    public function isAvailable(): bool;
}
```

Implementasi:
- `FormatOnlyNisnVerifier` — v1, hanya validasi 10 digit numerik
- `KemdikbudNisnVerifier` — v2 opsional, hit endpoint Kemdikbud dengan error handling graceful

`FormatOnlyNisnVerifier` digunakan secara default. Admin bisa enable `KemdikbudNisnVerifier` dari pengaturan plugin jika ingin (dengan peringatan bahwa ini unofficial). Tidak ada kode lain yang perlu berubah — dependency injection via konstruktor.

---

## Keputusan Final & Alasan

| Keputusan | Alasan |
|---|---|
| Custom table, bukan CPT | Performa query & filter di ribuan row; ekspor massal |
| VARCHAR untuk status, bukan ENUM | dbDelta compatibility; PHP enum cukup untuk type safety |
| BIGINT UNSIGNED untuk uang | Tidak ada operasi pecahan; menghindari floating-point |
| JSON column untuk konfigurasi field | Schema form terlalu beragam untuk kolom terpisah; MySQL 5.7+ sudah support |
| Tabel `jg_pendaftar` terpisah | UNIQUE constraint untuk nomor_wa/nik/nisn tidak bisa diterapkan di wp_usermeta |
| NIK & NISN di `jg_pendaftar` (terstruktur) | Admin perlu query/filter berdasarkan NIK/NISN; EAV terlalu lambat untuk ini |
| Skema form per gelombang | Isolasi historis; data gelombang lama tidak rusak walau form baru berubah |
| File di luar web root | KTP/KK/Ijazah adalah data pribadi sensitif; URL publik WP media library tidak aman |
| `jg_berkas` unified untuk semua file | Satu tabel untuk dokumen dan bukti bayar; `jg_pembayaran` cukup FK ke berkas_id |
| EAV untuk jawaban form | Skema form dinamis; trade-off performa diterima dan dimitigasi dengan strategi query batch |
| NISN v1 validasi format saja | Tidak ada API resmi Kemdikbud; `NisnVerifierInterface` memungkinkan upgrade ke v2 tanpa refactor |

---

## Konsekuensi & Trade-off

| Trade-off | Dampak | Mitigasi |
|---|---|---|
| EAV lambat untuk query filter di laporan | Query "cari semua pendaftar di mana field X = Y" memerlukan subquery/JOIN | Batch query per pendaftaran_id; filter utama di tabel terstruktur (status, gelombang_id) |
| NIK/NISN disimpan dua tempat (jg_pendaftar + jg_form_jawaban) | Potensi inkonsistensi jika salah satu diupdate | `StatusService` wajib update keduanya dalam satu operasi; tidak boleh update salah satu saja |
| File di luar web root memerlukan PHP serve | Setiap unduhan file melewati PHP — ada overhead | Acceptable untuk skala kampus; bisa tambahkan X-Accel-Redirect (Nginx) di masa depan |
| Tidak ada FK constraint di DB | Data integrity sepenuhnya di tangan PHP | Repository layer wajib enforce constraint sebelum insert/update |
| NISN API tidak resmi (jika diaktifkan) | Endpoint bisa berubah/mati tanpa pemberitahuan | `NisnVerifierInterface`: fallback ke format-only jika API tidak available |

---

## Dokumen Terkait

- [arsitektur-overview.md](arsitektur-overview.md) — gambaran besar sistem
- [arsitektur-form-builder.md](arsitektur-form-builder.md) — detail model EAV, tipe field, kondisional *(belum dibuat)*
- [arsitektur-berkas-media.md](arsitektur-berkas-media.md) — detail private file serving *(belum dibuat)*

---

## Hasil Implementasi

**Tanggal:** 2026-06-25
**Status:** Selesai — syntax check passed

### File yang dibuat

| File | Keterangan |
|---|---|
| `src/Installer.php` | dbDelta 10 tabel + buat upload dir + register roles |
| `src/Plugin.php` | Bootstrap singleton, definisi konstanta, auto-migrasi |
| `src/Enum/StatusPendaftaran.php` | Backed enum status + label + state machine transitions |
| `src/Enum/TipeField.php` | Backed enum tipe field + validasi format |
| `jalagistrasi.php` | Entry point plugin (metadata + register hooks) |

### Hasil `php -l`

```
No syntax errors detected in src/Enum/StatusPendaftaran.php
No syntax errors detected in src/Enum/TipeField.php
No syntax errors detected in src/Installer.php
No syntax errors detected in src/Plugin.php
No syntax errors detected in jalagistrasi.php
```

### Catatan deviasi dari rancangan

1. **Direktori upload**: Tidak bisa di-set benar-benar di luar web root via PHP (memerlukan konfigurasi server). Sebagai gantinya: subdirektori `wp-content/jalagistrasi-uploads/` dengan `.htaccess deny all` + `index.php` kosong sebagai fallback untuk server Nginx yang tidak membaca `.htaccess`.

2. **Roles langsung didaftarkan** di `Installer::createRoles()` meski belum aktif dipakai di v1. Ini disengaja — capability mapping (`jg_view_pendaftaran`, `jg_manage_gelombang`, dst.) sudah tersedia sehingga v2 bisa mengaktifkan role tanpa migrasi tambahan.

3. **Plugin::runMigrationsIfNeeded()** dipanggil di setiap `plugins_loaded` — bukan hanya saat aktivasi. Ini menangani skenario update plugin di server produksi tanpa harus deaktivasi/aktivasi ulang secara manual.
