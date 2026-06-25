# Arsitektur Frontend: Alur Pendaftaran Pendaftar

> Status: DRAFT — disepakati sebelum eksekusi  
> Tanggal: 2026-06-25

---

## 1. Konteks

Shortcode `[jg_registrasi]` sudah menangani **pembuatan akun** (daftar akun baru).  
Shortcode `[jg_dashboard]` masih stub — inilah yang akan dikembangkan di sesi ini.

Scope v1 ini mencakup:
- Dashboard pendaftar (lihat status, tombol mulai daftar)
- Pilih gelombang (jika lebih dari 1 aktif)
- Isi formulir dinamis (field dari form builder + pilihan prodi)
- Submit → konfirmasi

**Tidak termasuk v1:**
- Upload bukti bayar terpisah (termasuk dalam form sebagai field `file_upload`)
- Edit pendaftaran setelah submit
- Fitur simpan sebagai draft (resume nanti)
- Notifikasi email

---

## 2. Halaman & URL

Semua page frontend melewati satu shortcode `[jg_dashboard]` dengan GET params:

| URL | Action | Tampil |
|-----|--------|--------|
| `/dashboard-pmb/` | *(default)* | Dashboard: status + tombol daftar |
| `/dashboard-pmb/?action=pilih-gelombang` | `pilih-gelombang` | Pilih gelombang aktif |
| `/dashboard-pmb/?action=form&gelombang_id=X` | `form` | Form pendaftaran |
| `/dashboard-pmb/?action=sukses&ref=NOMOR` | `sukses` | Konfirmasi berhasil |

Semua require login. Jika tidak login → redirect ke `wp_login_url(current_url)`.

---

## 3. File Baru yang Akan Dibuat

### Controllers
```
src/Frontend/PendaftaranController.php
```
Menangani satu hook: `admin_post_jg_submit_pendaftaran`  
- Verifikasi nonce, login, validasi semua field
- Delegate ke `PendaftaranService`
- Redirect ke sukses atau kembali ke form dengan error transient

`RegistrasiController` diubah: `shortcodeDashboard()` diperluas untuk routing action.

### Repositories
```
src/Repository/PendaftaranRepository.php
src/Repository/FormJawabanRepository.php
src/Repository/PendaftaranProdiRepository.php
src/Repository/BerkasRepository.php
```

### Services
```
src/Service/PendaftaranService.php       — orkestra submit pendaftaran
src/Service/FileUploadService.php        — wp_handle_upload ke direktori privat
src/Service/NomorPendaftaranService.php  — generate nomor PMB-YYYY-NNNN
```

### Templates
```
templates/frontend/dashboard/index.php       — dashboard utama
templates/frontend/dashboard/pilih-gelombang.php — pilih gelombang
templates/frontend/form/index.php            — form pendaftaran
templates/frontend/form/sukses.php           — konfirmasi
```

---

## 4. Flow Submit Pendaftaran

```
POST /wp-admin/admin-post.php
  action = jg_submit_pendaftaran
  _wpnonce = [nonce]
  gelombang_id = X
  [field_nama] = [nilai] ...   (semua field form builder)
  prodi_pilihan[] = [id, id]   (urutan = index array + 1)
  [file_field] = $_FILES[...]  (untuk field bertipe file_upload)
```

**Di PendaftaranService::submit():**
1. Cek user sudah punya pendaftaran di gelombang ini → block duplikat
2. Validasi semua `is_required` field tidak kosong
3. Validasi pilihan prodi: jumlah = max_pilihan_prodi gelombang, tidak ada duplikat
4. Validasi file: mime type (jpg/png/pdf), ukuran ≤ max_size_kb dari konfigurasi
5. `$wpdb->insert` ke `jg_pendaftaran` → dapat `pendaftaran_id`
6. Loop field → `jg_form_jawaban` (bulk insert via transaction)
7. Untuk field `file_upload`: `FileUploadService::store()` → `jg_berkas` → simpan `berkas_id` di `nilai_text`
8. Insert pilihan prodi ke `jg_pendaftaran_prodi`
9. Generate nomor pendaftaran → update `jg_pendaftaran`
10. Update status `draft` → `submitted`, set `submitted_at = NOW()` (catatan: revisi nama status final ada di `arsitektur-overview.md`/enum `StatusPendaftaran` — dokumen ini sempat memakai nama lama `menunggu_verifikasi`)
11. Redirect ke sukses

**Error handling:** Jika validasi gagal, simpan errors + POST data ke transient (TTL 60s), redirect kembali ke form.

---

## 5. Pilihan Program Studi

Pilihan prodi **bukan** field form builder — selalu tampil sebagai seksi permanen pertama di form.

**Rendering:**
```
Pilihan Program Studi
  Pilihan ke-1 *  : [<select> — Pilih program studi ▼]   ← WAJIB
  Pilihan ke-2    : [<select> — Tidak memilih ▼]          ← opsional
  Pilihan ke-3    : [<select> — Tidak memilih ▼]          ← opsional
  (jumlah baris = gelombang.max_pilihan_prodi)
```

Opsi dropdown: semua `jg_program_studi` dengan `status = 'aktif'`, diurutkan `urutan ASC`.  
Pilihan 2+ memiliki opsi pertama "— Tidak memilih —" (value kosong).

**POST name:** `prodi_pilihan[1]`, `prodi_pilihan[2]`, `prodi_pilihan[3]` — key = nomor urut prioritas (1-based).

**Validasi server:**
- `prodi_pilihan[1]` wajib diisi (tidak boleh kosong)
- `prodi_pilihan[2]` dst opsional — jika diisi, harus valid & aktif
- Jumlah pilihan yang diisi tidak boleh melebihi `max_pilihan_prodi`
- Tidak ada duplikat di antara pilihan yang diisi
- Hanya baris yang terisi (value tidak kosong) yang disimpan ke `jg_pendaftaran_prodi`

---

## 6. Auto-fill Field Inti

| Field (`nama_field`) | Sumber | Behavior |
|---|---|---|
| `email` | `wp_users.user_email` | Read-only, pre-filled, hidden input |
| `nomor_hp` | `jg_pendaftar.nomor_wa` | Read-only, pre-filled, hidden input |
| `nik` | `jg_pendaftar.nik` | Pre-filled jika ada, bisa diisi jika null |
| `nisn` | `jg_pendaftar.nisn` | Pre-filled jika ada, bisa diisi jika null |
| `nama_lengkap` | `wp_users.display_name` | Pre-filled, bisa diubah |

Setelah submit: NIK dan NISN yang diisi pendaftar di-sync kembali ke `jg_pendaftar` (UPDATE).

---

## 7. File Upload

- **Direktori:** `WP_CONTENT_DIR/jalagistrasi-uploads/{pendaftaran_id}/`
- **Nama file tersimpan:** `{field_nama}_{timestamp}_{random8}.{ext}`
- **Mime type yang diterima:** `image/jpeg`, `image/png`, `application/pdf`
- **Ukuran maks:** dari `konfigurasi.max_size_kb` field (default 2048 KB)
- **Akses file:** Tidak boleh diakses publik — dilindungi `.htaccess` + `deny from all`
- **Catatan:** Tidak menggunakan WP Media Library (`wp_handle_upload` dengan custom `upload_dir`)

---

## 8. Nomor Pendaftaran

Format: `{PREFIX}-{TAHUN}-{SEQ}`  
Contoh: `PMB-2026-0001`

- `PREFIX` dari `wp_option` key `jalagistrasi_nomor_prefix` (default: `PMB`)
- `TAHUN` dari `gelombang.tahun_akademik` (ambil 4 digit tahun awal)
- `SEQ` = COUNT(pendaftaran di gelombang ini yang sudah submit) + 1, zero-padded
- Panjang padding SEQ dari `wp_option` key `jalagistrasi_nomor_seq_length` (default: `4`)
- Di-generate di `NomorPendaftaranService::generate(int $gelombangId): string`
- Di-set saat status berubah ke `submitted`

**Setting yang bisa dikonfigurasi admin (halaman Pengaturan):**

| wp_option key | Default | Keterangan |
|---|---|---|
| `jalagistrasi_nomor_prefix` | `PMB` | Prefix nomor pendaftaran |
| `jalagistrasi_nomor_seq_length` | `4` | Panjang digit urutan (4 → 0001) |
| `jalagistrasi_nama_institusi` | *(kosong)* | Nama kampus, tampil di form & konfirmasi |

Halaman Pengaturan dibangun bersamaan dengan frontend (minimal form sederhana untuk tiga setting ini).

---

## 9. Repository Interface

### PendaftaranRepository
```php
findByUserGelombang(int $userId, int $gelombangId): ?object
findById(int $id): ?object
findByUser(int $userId): array
insert(array $data): int|false
updateStatus(int $id, string $status): bool
updateNomor(int $id, string $nomor): bool
countByGelombang(int $gelombangId): int
```

### FormJawabanRepository
```php
findByPendaftaran(int $pendaftaranId): array
upsert(int $pendaftaranId, int $fieldId, string $nilaiText = '', ?array $nilaiJson = null): bool
bulkInsert(int $pendaftaranId, array $jawabanMap): bool
```

### PendaftaranProdiRepository
```php
findByPendaftaran(int $pendaftaranId): array
insertAll(int $pendaftaranId, array $prodiIds): bool   // prodiIds ordered by priority
deleteByPendaftaran(int $pendaftaranId): bool
```

### BerkasRepository
```php
insert(array $data): int|false
findByPendaftaran(int $pendaftaranId): array
findByPendaftaranAndTipe(int $pendaftaranId, string $tipeBerkas): ?object
```

---

## 10. Security Checklist

- [x] Semua halaman require `is_user_logged_in()` → redirect ke login
- [x] Form submission via `admin_post_` + `wp_verify_nonce`
- [x] `current_user_can('read')` — semua role pendaftar punya capability ini
- [x] Pendaftar hanya bisa melihat pendaftarannya sendiri (filter `user_id = current_user_id`)
- [x] File upload: validasi mime type server-side (tidak percaya `$_FILES['type']`)
- [x] File disimpan di direktori privat dengan `.htaccess deny from all`
- [x] Nama file tersimpan di-generate ulang (tidak menggunakan nama asli dari user)
- [x] Semua output di-escape: `esc_html`, `esc_attr`, `esc_url`
- [x] Semua input di-sanitize sebelum dimasukkan ke DB
- [x] `$wpdb->prepare()` untuk semua query dengan parameter user input
- [x] Validasi `gelombang_id` dan `field_id` adalah integer positif yang valid di DB
- [x] Cek gelombang masih berstatus `aktif` saat submit

---

## 11. Hook Baru di Plugin.php

```php
$pendaftaranCtrl = new PendaftaranController();
add_action('admin_post_jg_submit_pendaftaran', [$pendaftaranCtrl, 'handleSubmit']);
```

Tidak perlu `admin_post_nopriv_` — pendaftar wajib login sebelum bisa submit.

---

## 12. Status Implementasi

| Komponen | Status |
|---|---|
| **Repositories** | |
| PendaftaranRepository | ✅ |
| FormJawabanRepository | ✅ |
| PendaftaranProdiRepository | ✅ |
| BerkasRepository | ✅ |
| GelombangRepository (+ findAktifTerbuka) | ✅ |
| PendaftarRepository (+ updateNikNisn) | ✅ |
| **Services** | |
| NomorPendaftaranService | ✅ |
| FileUploadService | ✅ |
| PendaftaranService | ✅ |
| **Controllers** | |
| PendaftaranController (frontend) | ✅ |
| PengaturanController (admin, inline render) | ✅ |
| RegistrasiController (routing dashboard) | ✅ |
| Plugin.php (hook baru) | ✅ |
| AdminMenu.php (Pengaturan live) | ✅ |
| **Templates Frontend** | |
| templates/frontend/dashboard/index.php | ✅ |
| templates/frontend/dashboard/pilih-gelombang.php | ✅ |
| templates/frontend/form/index.php | ✅ |
| templates/frontend/form/sukses.php | ✅ |
| **Catatan** | |
| Status setelah submit: `submitted` (sesuai StatusPendaftaran enum) | ✅ |
| Prefix nomor pendaftaran: configurable via wp_options | ✅ |

## 13. Edit Formulir Setelah Submit (2026-06-26)

**Keputusan:** Pendaftar boleh mengedit ulang jawaban formulir biodata selama status pendaftaran masih di salah satu dari: `submitted`, `berkas_diupload`, `berkas_ditolak`. Begitu dokumen diverifikasi panitia (`berkas_diverifikasi`) atau status sudah lebih jauh dari itu, form dikunci permanen.

**Alasan batas di `berkas_diverifikasi`:** data biodata (nama, NIK, alamat, dst) pada titik itu sudah dicocokkan manual oleh panitia terhadap dokumen yang diupload (KTP, KK, dst). Mengizinkan edit setelah titik ini berisiko menciptakan ketidaksesuaian antara data formulir dan dokumen yang sudah "disahkan" panitia.

**Mekanisme:**
- `RegistrasiController::renderFormPendaftaran()` — kondisi blokir akses diperluas dari "hanya draft" menjadi "draft ATAU salah satu dari 3 status di atas". Pre-fill jawaban existing kini juga jalan untuk status non-draft tadi (sebelumnya hanya untuk draft).
- `PendaftaranService::submit()` — saat pendaftaran existing yang ditemukan statusnya BUKAN draft (berarti ini edit, bukan submit pertama):
  - Jawaban & pilihan prodi tetap di-overwrite seperti biasa (hapus lama, tulis ulang).
  - **Nomor pendaftaran TIDAK digenerate ulang** — tetap pakai nomor lama, karena ini bukan pendaftaran baru.
  - **Status TIDAK diubah** — kalau sebelumnya `berkas_ditolak`, tetap `berkas_ditolak` setelah edit biodata (edit form tidak otomatis mencabut status tolak dokumen — itu keputusan terpisah lewat verifikasi ulang dokumen oleh panitia).
  - `StatusHistoryRepository::log()` tetap dipanggil untuk audit trail, tapi dengan `status_lama === status_baru` (status saat ini) + catatan `"Formulir biodata diedit oleh pendaftar."` — bukan transisi status sungguhan.
- `templates/frontend/detail/index.php` — link "Edit Formulir" ditambahkan (mengarah ke halaman form yang sama) untuk ketiga status yang boleh edit, di samping link "Lanjutkan Mengisi Formulir" yang sudah ada untuk status `draft`.
- `templates/frontend/form/index.php` — tombol & teks berubah kontekstual: kalau sedang edit mode (status existing bukan draft), tombol jadi "Simpan Perubahan" dan tombol "Simpan Draft" disembunyikan (konsep draft tidak relevan lagi setelah submit pertama).
- Tidak ada perubahan pada `PendaftaranService::saveDraft()` — method itu tetap exclusive untuk status draft (pesan error "Pendaftaran ini sudah dikirim dan tidak bisa diedit" tetap berlaku untuk SIMPAN DRAFT, karena draft-saving memang bukan jalur yang dipakai untuk edit pasca-submit).
