# Arsitektur Form Builder — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — rancangan, menunggu persetujuan
**Author:** Webane Indonesia

---

## Konsep Inti

Form Builder adalah UI di wp-admin untuk admin membuat dan mengelola skema field formulir **per gelombang**. Pendekatan ini mirip Contact Form 7 — `tipe` field menentukan jenis input dan validasi, sedangkan `nama_field` adalah identifier unik yang membedakan satu field dari field lain dalam satu gelombang.

Contoh: NIK Pendaftar dan NIK Ayah keduanya bertipe `nik` (validasi 16 digit), tapi `nama_field`-nya berbeda: `nik_pendaftar` dan `nik_ayah`.

---

## Tipe Field yang Didukung (v1)

| Tipe | Input HTML | Validasi Otomatis |
|---|---|---|
| `text` | `<input type="text">` | max_length dari konfigurasi |
| `textarea` | `<textarea>` | max_length dari konfigurasi |
| `number` | `<input type="number">` | min/max dari konfigurasi |
| `date` | `<input type="date">` | min/max date dari konfigurasi |
| `email` | `<input type="email">` | format email (regex) |
| `phone` | `<input type="tel">` | format Indonesia: `+62/62/0` + 8–13 digit |
| `nik` | `<input type="text" inputmode="numeric">` | tepat 16 digit angka |
| `nisn` | `<input type="text" inputmode="numeric">` | tepat 10 digit angka |
| `select` | `<select>` | harus salah satu dari options |
| `radio` | `<input type="radio">` | harus salah satu dari options |
| `checkbox` | `<input type="checkbox">` | min 1 dipilih jika required |
| `file_upload` | `<input type="file">` | mime type + max size dari konfigurasi |

---

## Struktur Data: Kolom `konfigurasi` (JSON)

Setiap tipe menyimpan konfigurasi tambahan di kolom `konfigurasi JSON`:

```json
// select / radio / checkbox
{ "options": ["Islam", "Kristen", "Katolik", "Hindu", "Budha", "Konghucu"] }

// file_upload
{ "accept": ["image/jpeg", "image/png", "application/pdf"], "max_size_kb": 2048 }

// text / textarea
{ "placeholder": "Masukkan nama lengkap sesuai KTP", "max_length": 200 }

// number
{ "min": 1990, "max": 2030 }

// date
{ "min": "1990-01-01", "max": "2015-12-31" }

// text, email, phone, nik, nisn tanpa konfigurasi tambahan → null
```

---

## Perubahan Schema DB

Tambah kolom `section_name` ke tabel `jg_form_field`:

```sql
-- Diupdate via dbDelta di Installer::createTables()
section_name VARCHAR(100) DEFAULT NULL
```

Schema lengkap `jg_form_field` setelah update:

```sql
CREATE TABLE {prefix}jg_form_field (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gelombang_id  BIGINT UNSIGNED NOT NULL,
  section_name  VARCHAR(100) DEFAULT NULL,        -- ← baru: "Biodata Pribadi", dll
  nama_field    VARCHAR(100) NOT NULL,
  label         VARCHAR(200) NOT NULL,
  tipe          VARCHAR(50) NOT NULL,
  is_required   TINYINT(1) NOT NULL DEFAULT 0,
  is_core       TINYINT(1) NOT NULL DEFAULT 0,
  urutan        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  konfigurasi   JSON DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gelombang_nama (gelombang_id, nama_field),
  KEY idx_gelombang_urutan (gelombang_id, urutan)
)
```

---

## Aturan Field Inti (`is_core = 1`)

Field berikut **tidak bisa dihapus** admin karena digunakan oleh sistem:

| nama_field | Tipe | Alasan |
|---|---|---|
| `nama_lengkap` | text | Ditampilkan di seluruh sistem |
| `nik` | nik | Dipakai untuk identifikasi ke instansi |
| `nisn` | nisn | Dipakai untuk identifikasi ke instansi |
| `nomor_hp` | phone | Sudah ada di `jg_pendaftar`, tampil di form untuk konfirmasi |
| `email` | email | Sudah ada di `wp_users`, tampil di form untuk konfirmasi |

Label dan urutan field inti tetap bisa diubah admin. Yang tidak bisa: hapus dan ubah tipe.

---

## `file_upload` dan `jg_berkas`

Saat pendaftar mengisi field bertipe `file_upload`, file disimpan ke tabel `jg_berkas`. Kolomnya:
- `tipe_berkas` = `nama_field` field tersebut (misal: `foto`, `ijazah`, `ktp`)
- `pendaftaran_id` = FK ke pendaftaran aktif

Di `jg_form_jawaban`, field `file_upload` menyimpan `nilai_text = berkas_id` (sebagai referensi). Ini menjaga konsistensi — semua jawaban tetap bisa di-query dari satu tabel.

---

## UI Admin: Alur Form Builder

```
/wp-admin/admin.php?page=jg-form-builder
  ↓
Pilih gelombang (dropdown)
  ↓
Tampil daftar field + tombol "Tambah Field"
  ↓
Field dikelompokkan per section_name
  ↓
Drag & drop untuk reorder (jQuery UI Sortable — sudah bundled di WP admin, 0 KB tambahan)
  → Reorder disimpan via AJAX (wp_ajax_jg_reorder_fields)
  ↓
Klik "Tambah Field" → form inline / halaman add
Klik "Edit" pada field → halaman edit
Klik "Hapus" → konfirmasi → delete
```

### Mengapa jQuery UI Sortable, bukan SortableJS?

jQuery UI Sortable sudah di-bundle di setiap instalasi WordPress (`wp_enqueue_script('jquery-ui-sortable')`). Zero tambahan payload, zero CDN dependency. SortableJS lebih modern tapi menambah ~9 KB tanpa alasan yang cukup untuk area admin.

---

## Struktur File yang Dibuat

```
src/Admin/
└── FormBuilderController.php      # Render page + handle POST + AJAX reorder

src/Repository/
└── FormSchemaRepository.php       # CRUD jg_form_field

src/Service/
└── DefaultFormTemplate.php        # 34 field default dari DOCX formulir PMB

templates/admin/
└── form-builder/
    ├── index.php                  # Pilih gelombang + list field (sortable)
    └── field-form.php             # Form tambah/edit field
```

---

## Hooks yang Didaftarkan di Plugin.php

```php
$formBuilderCtrl = new FormBuilderController();
add_action('admin_post_jg_save_form_field',   [$formBuilderCtrl, 'handleSave']);
add_action('admin_post_jg_delete_form_field', [$formBuilderCtrl, 'handleDelete']);
add_action('wp_ajax_jg_reorder_fields',       [$formBuilderCtrl, 'handleReorder']);
```

---

## Default Template: 34 Field dari DOCX

Template di-seed saat gelombang baru dibuat. Admin bisa edit/hapus/tambah setelahnya.

### SEKSI 1: Biodata Pribadi

| urutan | nama_field | label | tipe | required | is_core |
|---|---|---|---|---|---|
| 1 | nama_lengkap | Nama Lengkap | text | ya | ya |
| 2 | tempat_lahir | Tempat Lahir | text | ya | — |
| 3 | tanggal_lahir | Tanggal Lahir | date | ya | — |
| 4 | jenis_kelamin | Jenis Kelamin | radio | ya | — |
| 5 | alamat_jalan | Alamat Jalan | text | ya | — |
| 6 | alamat_dusun | Dusun | text | — | — |
| 7 | alamat_rt | RT | text | — | — |
| 8 | alamat_rw | RW | text | — | — |
| 9 | alamat_kelurahan | Kelurahan / Desa | text | ya | — |
| 10 | alamat_kecamatan | Kecamatan | text | ya | — |
| 11 | alamat_kode_pos | Kode Pos | text | — | — |
| 12 | nik | NIK | nik | ya | ya |
| 13 | nisn | NISN | nisn | ya | ya |
| 14 | nomor_hp | No. HP / WhatsApp | phone | ya | ya |
| 15 | email | Email | email | ya | ya |
| 16 | agama | Agama | select | ya | — |
| 17 | kewarganegaraan_suku | Kewarganegaraan / Suku | text | — | — |
| 18 | foto | Pas Foto (2×3) | file_upload | ya | — |

### SEKSI 2: Sekolah Asal

| urutan | nama_field | label | tipe | required | is_core |
|---|---|---|---|---|---|
| 19 | jenis_sekolah | Jenis Sekolah | select | ya | — |
| 20 | nama_sekolah | Nama Sekolah | text | ya | — |
| 21 | alamat_sekolah | Alamat Sekolah | textarea | — | — |
| 22 | tahun_lulus | Tahun Lulus | number | ya | — |
| 23 | nomor_ijazah | Nomor Ijazah | text | — | — |

### SEKSI 3: Biodata Orang Tua

| urutan | nama_field | label | tipe | required | is_core |
|---|---|---|---|---|---|
| 24 | nik_ayah | NIK Ayah | nik | — | — |
| 25 | nama_ayah | Nama Ayah | text | ya | — |
| 26 | tanggal_lahir_ayah | Tanggal Lahir Ayah | date | — | — |
| 27 | pendidikan_ayah | Pendidikan Terakhir Ayah | select | — | — |
| 28 | nik_ibu | NIK Ibu | nik | — | — |
| 29 | nama_ibu | Nama Ibu | text | ya | — |
| 30 | tanggal_lahir_ibu | Tanggal Lahir Ibu | date | — | — |
| 31 | pendidikan_ibu | Pendidikan Terakhir Ibu | select | — | — |

### SEKSI 4: Pertanyaan Tambahan

| urutan | nama_field | label | tipe | required | is_core |
|---|---|---|---|---|---|
| 32 | penghasilan_ayah | Penghasilan Ayah per Bulan | radio | — | — |
| 33 | penghasilan_ibu | Penghasilan Ibu per Bulan | radio | — | — |
| 34 | sumber_informasi | Darimana Anda mengetahui kami? | checkbox | — | — |

**Konfigurasi options:**

- `agama`: Islam, Kristen, Katolik, Hindu, Budha, Konghucu
- `jenis_kelamin`: Laki-laki, Perempuan
- `jenis_sekolah`: SMA, SMK, MA, Paket C
- `pendidikan_ayah/ibu`: Tidak Sekolah, SD, SMP, SMA/SMK, D3, S1, S2, S3
- `penghasilan_ayah/ibu`: Di bawah Rp 500.000 | Rp 500.000–1.000.000 | Rp 1.000.000–2.000.000 | Rp 2.000.000–3.000.000 | Rp 3.000.000–4.000.000 | Di atas Rp 4.000.000
- `sumber_informasi`: Teman, Saudara/Keluarga, Brosur/Spanduk/Poster, Website, Instagram, Facebook, TikTok, Iklan, Pameran Pendidikan, Presentasi ke Sekolah, Lainnya
- `foto`: accept image/jpeg + image/png, max 2 MB
- `nomor_ijazah`: tidak ada konfigurasi khusus (bebas format per kampus)

---

## Auto-fill dari Data Akun (Poin C yang Disepakati)

Field berikut di-pre-fill otomatis dari data akun saat pendaftar membuka form, dan ditampilkan sebagai **read-only** — tidak bisa diubah langsung di form:

| nama_field | Sumber data | Catatan |
|---|---|---|
| `email` | `wp_users.user_email` | Sudah ada saat registrasi |
| `nomor_hp` | `jg_pendaftar.nomor_wa` | Sudah ada saat registrasi |
| `nik` | `jg_pendaftar.nik` | Nullable — kosong jika belum pernah diisi |
| `nisn` | `jg_pendaftar.nisn` | Nullable — kosong jika belum pernah diisi |

Saat form disimpan:
- `nik` dan `nisn` → nilai diupdate ke `jg_pendaftar.nik` dan `jg_pendaftar.nisn` (sinkron)
- `email` dan `nomor_hp` → disimpan ke `jg_form_jawaban` dari sumber akun (bukan dari POST — diabaikan jika user coba manipulasi)
- Semua field lain → disimpan normal dari POST ke `jg_form_jawaban`

Jika pendaftar ingin mengubah email atau nomor WA, mereka harus lewat halaman profil (v2).

**NISN Verification**: Field `nisn` sudah punya `NisnVerifierInterface` (didokumentasikan di `arsitektur-database.md`). v1 hanya validasi format 10 digit.

Integrasi API kemendikdasmen (`nisn.data.kemendikdasmen.go.id`) **ditunda ke v2**. Alasan:
- API tidak resmi / tidak didokumentasikan untuk developer eksternal
- AES key tertanam di JS bundle mereka dan bisa berubah kapan saja tanpa notice
- Constraint reCAPTCHA v3 belum ditest dari domain pihak ketiga
- Data yang dikembalikan menarik (nama, tempat/tgl lahir, nama sekolah) tapi resiko downtime tidak acceptable untuk produksi

Slot implementasi sudah tersedia via `NisnVerifierInterface` — ketika siap, cukup tambah class `KemendikdasmenNisnVerifier` tanpa ubah form builder.

---

## Kapan Default Template Di-seed?

Saat admin menyimpan gelombang baru (POST ke `jg_save_gelombang`), `GelombangController` memanggil `DefaultFormTemplate::seedForGelombang(int $gelombangId)` setelah insert berhasil. Tidak ada checkbox — langsung otomatis, karena admin selalu butuh form dan lebih mudah menghapus field yang tidak dibutuhkan daripada membuat dari nol.

Jika admin clone gelombang (v2), template tidak di-seed ulang — field di-copy dari gelombang sumber.

---

## FormSchemaRepository: Method

```php
findByGelombang(int $gelombangId): array          // semua field, diurutkan urutan ASC
findById(int $id): ?object
insert(array $data): int|false
update(int $id, array $data): bool
delete(int $id): bool
updateUrutan(array $orderedIds): bool              // [{id: 1, urutan: 1}, ...] → bulk update
existsNamaField(int $gelombangId, string $nama, int $excludeId = 0): bool
```

---

## Hasil Implementasi

**Tanggal:** 2026-06-25
**Status:** Selesai — 8/8 syntax check passed, end-to-end verified

### File yang dibuat / diubah

| File | Status |
|---|---|
| `src/Repository/FormSchemaRepository.php` | Baru |
| `src/Service/DefaultFormTemplate.php` | Baru — 34 field, 4 seksi |
| `src/Admin/FormBuilderController.php` | Baru |
| `src/Admin/AdminMenu.php` | Diperbarui — Form Builder terhubung + jquery-ui-sortable |
| `src/Admin/GelombangController.php` | Diperbarui — panggil DefaultFormTemplate::seedForGelombang() setelah insert |
| `src/Installer.php` | Diperbarui — tambah kolom section_name ke jg_form_field |
| `src/Plugin.php` | Diperbarui — 3 hooks form builder (save, delete, AJAX reorder) |
| `templates/admin/form-builder/index.php` | Baru |
| `templates/admin/form-builder/field-form.php` | Baru |

### Verifikasi Fungsional

| Fitur | Status |
|---|---|
| Kolom `section_name` ditambahkan via dbDelta | ✅ |
| 34 field ter-seed otomatis saat gelombang baru dibuat | ✅ (Gelombang 1: 0 field karena dibuat sebelum fitur ini; Gelombang 2: 34 field) |
| Distribusi seksi: Biodata Pribadi(18), Sekolah Asal(5), Biodata Orang Tua(8), Pertanyaan Tambahan(3) | ✅ |
| Halaman Form Builder ter-render dengan tabel field dan drag handle | ✅ |
| Drag & drop jQuery UI Sortable + AJAX save urutan | ✅ |
| Form tambah/edit field dengan conditional konfigurasi (options, placeholder, min/max) | ✅ |
| Field inti (is_core=1) tidak bisa dihapus, nama field & tipe read-only saat edit | ✅ |
