# Arsitektur Admin Panel — Plugin Jalagistrasi

**Tanggal:** 2026-06-25 (rancangan awal — sudah jauh berkembang, lihat catatan "Update" di bawah)
**Status:** Historis — sebagian besar scope sudah diimplementasikan & diperluas jauh di luar rancangan awal ini
**Author:** Webane Indonesia

---

## Lingkup Modul Ini

Admin panel di dalam `wp-admin` untuk mengelola:
1. **Gelombang Pendaftaran** — CRUD + toggle status aktif
2. **Program Studi** — CRUD + toggle status + urutan tampil

> **Update (2026-06-25):** sejak dokumen ini ditulis, **Form Builder** (`arsitektur-form-builder.md`), **Manajemen Pendaftar** (`arsitektur-verifikasi-berkas.md`, `arsitektur-pembayaran.md`), **Tipe Berkas**, **Rekening Bank**, **Tahun Ajaran** (`arsitektur-tahun-ajaran.md`), dan **Pengaturan** dasar (nomor pendaftaran, identitas institusi — `arsitektur-identitas-institusi.md`) semuanya **sudah dibangun dan dipakai aktif**. Yang masih benar-benar ditunda: **Ekspor Data** (Excel/CSV/PDF) dan **Pengaturan SMTP** (kirim email) — lihat `arsitektur-overview.md` §5–6.

---

## Keputusan Desain

### UI Framework: WordPress Native Admin CSS
Admin panel menggunakan class CSS bawaan WordPress (`wrap`, `wp-list-table`, `button-primary`, `form-table`, dll) — **tidak menggunakan Tailwind**.

**Alasan:**
- Konsisten secara visual dengan antarmuka wp-admin yang sudah dikenal admin kampus.
- Tidak ada risiko konflik CSS dengan tema atau plugin lain di area admin.
- Tidak butuh build step tambahan untuk admin CSS.

### Form Processing: `admin_post_{action}` Hook
Semua submit form diproses via hook `admin_post_{action}` (native WordPress), bukan endpoint custom atau REST API.

**Alasan:**
- Terintegrasi dengan mekanisme nonce dan capability WordPress.
- Redirect setelah success menggunakan `wp_safe_redirect()`.
- Lebih mudah di-debug karena mengikuti konvensi WP.

### Keamanan
- Setiap form menggunakan `wp_nonce_field()` + `check_admin_referer()`
- Setiap halaman admin menggunakan `current_user_can()` atau `RoleManager::requireCapability()`
- Semua input di-sanitasi sebelum dimasukkan ke DB
- Semua output di-escape dengan `esc_html()`, `esc_attr()`, `esc_url()`
- Delete menggunakan nonce spesifik per ID: `jg_delete_gelombang_{id}`

---

## Struktur File yang Dibuat

```
src/Admin/
├── AdminMenu.php                  # Register menu + submenu + enqueue admin JS
├── GelombangController.php        # CRUD gelombang
└── ProgramStudiController.php     # CRUD program studi

src/Repository/
├── GelombangRepository.php        # Semua query jg_gelombang
└── ProgramStudiRepository.php     # Semua query jg_program_studi

templates/admin/
├── gelombang/
│   ├── list.php                   # Tabel daftar gelombang
│   └── form.php                   # Form tambah/edit gelombang
└── program-studi/
    ├── list.php                   # Tabel daftar program studi
    └── form.php                   # Form tambah/edit program studi
```

---

## Registrasi Menu WordPress

```
Jalagistrasi PMB          (top-level menu, icon: dashicons-graduation)
├── Dashboard              → DashboardController::renderPage() — statistik, lihat arsitektur-dashboard-admin.md
├── Tahun Ajaran           → TahunAjaranController::renderPage() — arsitektur-tahun-ajaran.md
├── Gelombang              → GelombangController::renderPage()
├── Program Studi          → ProgramStudiController::renderPage()
├── Pendaftar              → PendaftarController::renderPage() — arsitektur-verifikasi-berkas.md, arsitektur-pembayaran.md
├── Tipe Berkas            → TipeBerkasController::renderPage()
├── Rekening Bank          → RekeningBankController::renderPage() — arsitektur-pembayaran.md
├── Form Builder           → FormBuilderController::renderPage() — arsitektur-form-builder.md
└── Pengaturan             → PengaturanController::renderPage() — arsitektur-identitas-institusi.md (SMTP belum ada)
```

*(Daftar di atas mencerminkan kondisi terkini, 2026-06-25 — bukan rencana awal saat dokumen ini ditulis.)*

Capability minimum untuk akses menu utama: `manage_options` (administrator) atau `jg_manage_gelombang`.

---

## Gelombang: Spesifikasi CRUD

### List View

Kolom tabel:
| Kolom | Sortable |
|---|---|
| Nama Gelombang | Ya |
| Tahun Akademik | Ya |
| Tgl Buka | Ya |
| Tgl Tutup | Ya |
| Max Pilihan Prodi | — |
| Status | Ya |
| Aksi (Edit / Hapus) | — |

Filter atas tabel: semua status / aktif / nonaktif

### Form Tambah/Edit

Field:
| Field | Tipe HTML | Validasi |
|---|---|---|
| Nama Gelombang | text | required, max 200 |
| Tahun Akademik | text | required, format `YYYY/YYYY`, maks 20 |
| Tanggal Buka | datetime-local | required |
| Tanggal Tutup | datetime-local | required, harus setelah tgl buka |
| Max Pilihan Prodi | number | required, 1–10 |
| Status | select: aktif/nonaktif | required |

### Alur Request

```
GET  /wp-admin/admin.php?page=jg-gelombang            → render list
GET  /wp-admin/admin.php?page=jg-gelombang&action=add → render form kosong
GET  /wp-admin/admin.php?page=jg-gelombang&action=edit&id=X → render form isi
POST /wp-admin/admin-post.php (action=jg_save_gelombang)    → simpan → redirect list
POST /wp-admin/admin-post.php (action=jg_delete_gelombang)  → hapus → redirect list
```

Redirect setelah sukses/gagal membawa `?jg_message=created|updated|deleted|error` di URL. Pesan ditampilkan via admin notice.

### Constraint Delete
Gelombang **tidak bisa dihapus** jika sudah ada pendaftaran di gelombang itu (`jg_pendaftaran.gelombang_id`). Controller cek ini sebelum delete dan tampilkan error yang jelas.

---

## Program Studi: Spesifikasi CRUD

### List View

Kolom tabel:
| Kolom | Sortable |
|---|---|
| Kode | Ya |
| Nama Program Studi | Ya |
| Kuota | — |
| Status | Ya |
| Urutan Tampil | Ya |
| Aksi (Edit / Hapus) | — |

### Form Tambah/Edit

Field:
| Field | Tipe HTML | Validasi |
|---|---|---|
| Nama Program Studi | text | required, max 200 |
| Kode | text | required, max 20, unik |
| Deskripsi | textarea | opsional |
| Kuota | number | required, min 0 |
| Urutan Tampil | number | required, min 0 |
| Status | select: aktif/nonaktif | required |

### Constraint Delete
Program studi **tidak bisa dihapus** jika sudah dipilih di `jg_pendaftaran_prodi`. Tampilkan error yang jelas.

---

## Repository: Method yang Diimplementasi

### GelombangRepository

```php
findAll(string $status = ''): array         // list + optional filter status
findById(int $id): ?object                   // single row atau null
insert(array $data): int|false               // return insert ID
update(int $id, array $data): bool
delete(int $id): bool
countPendaftaran(int $gelombangId): int      // cek sebelum delete
```

### ProgramStudiRepository

```php
findAll(string $status = ''): array
findById(int $id): ?object
findByKode(string $kode): ?object            // cek uniqueness kode
insert(array $data): int|false
update(int $id, array $data): bool
delete(int $id): bool
countPilihan(int $prodiId): int              // cek sebelum delete
```

---

## Hooks yang Didaftarkan di Plugin.php

```php
// Di Plugin::registerHooks():
add_action('admin_menu', [$adminMenu, 'registerMenus']);
add_action('admin_enqueue_scripts', [$adminMenu, 'enqueueAdminAssets']);
add_action('admin_post_jg_save_gelombang', [$gelombangCtrl, 'handleSave']);
add_action('admin_post_jg_delete_gelombang', [$gelombangCtrl, 'handleDelete']);
add_action('admin_post_jg_save_program_studi', [$prodiCtrl, 'handleSave']);
add_action('admin_post_jg_delete_program_studi', [$prodiCtrl, 'handleDelete']);
```

---

## Admin JavaScript (Minimal)

Satu file kecil `assets/js/admin.js` — hanya untuk:
1. Konfirmasi delete: `confirm()` native browser sebelum submit form hapus
2. Tidak ada dependency framework — vanilla JS

---

## Hasil Implementasi

**Tanggal:** 2026-06-25
**Status:** Selesai — 10/10 syntax check passed, CRUD verified via test

### File yang dibuat / diubah

| File | Status |
|---|---|
| `src/Repository/GelombangRepository.php` | Baru |
| `src/Repository/ProgramStudiRepository.php` | Baru |
| `src/Admin/AdminMenu.php` | Baru |
| `src/Admin/GelombangController.php` | Baru |
| `src/Admin/ProgramStudiController.php` | Baru |
| `templates/admin/gelombang/list.php` | Baru |
| `templates/admin/gelombang/form.php` | Baru |
| `templates/admin/program-studi/list.php` | Baru |
| `templates/admin/program-studi/form.php` | Baru |
| `src/Plugin.php` | Diperbarui — tambah 6 hooks admin |
| `resources/js/admin.js` | Diperbarui — vanilla JS hanya konfirmasi delete |

### Verifikasi Fungsional

| Fitur | Status |
|---|---|
| Menu "Jalagistrasi PMB" muncul di wp-admin dengan 6 submenu | ✅ |
| Halaman Gelombang: list, tambah, edit, hapus | ✅ |
| Halaman Program Studi: list, tambah, edit, hapus | ✅ |
| Validasi form + repopulate setelah error (via transient) | ✅ |
| Nonce CSRF di setiap form dan delete action | ✅ |
| Constraint delete: blocked jika ada data terkait | ✅ |
| Admin notice sukses/error setelah operasi | ✅ |
| admin.js: 0.27 kB (vanilla JS, tanpa Alpine/framework) | ✅ |

### Catatan Implementasi

- Form error disimpan via `set_transient()` (TTL 60 detik) lalu di-delete setelah dibaca — menghindari flash state antar request.
- Kode program studi di-uppercase otomatis baik di controller (PHP) maupun di input (CSS `text-transform:uppercase`).
- Constraint delete dilakukan di PHP controller sebelum query delete — tidak mengandalkan DB constraint (sesuai konvensi WordPress yang tidak pakai FK).
- `admin.js` di-refactor dari Alpine.js ke vanilla JS — admin panel tidak butuh reaktivitas, `confirm()` native browser sudah cukup.
