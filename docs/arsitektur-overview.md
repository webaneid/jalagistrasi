# Arsitektur Overview — Plugin Jalagistrasi

**Tanggal:** 2026-06-24
**Status:** v1.0 — disetujui
**Author:** Webane Indonesia

---

## Konteks

Plugin WordPress untuk sistem Pendaftaran Mahasiswa Baru (PMB) kampus. Plugin ini menangani data pribadi sensitif (NIK, KTP, KK, Ijazah, nilai) sehingga **keamanan data adalah prioritas desain nomor satu**, mendahului kemudahan implementasi.

Dikembangkan dan dikelola sepenuhnya oleh Webane Indonesia. Hak cipta milik Webane Indonesia.

---

## Alur Bisnis Pendaftaran (Happy Path)

```
[1] Registrasi Akun
      ↓ (email + no. WA harus unik — validasi di sini)
[2] Isi Formulir (Form Builder Dinamis)
      ↓
[3] Upload Berkas (KTP, KK, Ijazah, Foto, dll)
      ↓
[4] Panitia: Verifikasi Berkas & Data Formulir
      ↓
[5] Pembayaran Biaya Pendaftaran (Upload Bukti Transfer + Kode Unik)
      ↓
[6] Tes / Seleksi (jadwal oleh panitia)
      ↓
[7] Pengumuman Hasil
      ↓
[8] Daftar Ulang (Pembayaran UKT / Biaya Awal)
      ↓
[SELESAI — Mahasiswa Baru]
```

Catatan alur:
- V1 hanya satu jalur pendaftaran (sederhana). Multi-jalur dikembangkan di versi berikutnya.
- Satu akun bisa mengikuti banyak gelombang; riwayat semua pendaftaran tetap tersimpan dan bisa dilihat.
- Satu pendaftaran bisa memilih Program Studi dengan jumlah maksimal yang **dikonfigurasi per gelombang** oleh admin (default 2, tidak hardcoded).

---

## Status Machine Pendaftaran

```
draft
  └→ submitted (pendaftar submit formulir)
       └→ berkas_diupload (pendaftar upload semua dokumen)
            ├→ berkas_ditolak (panitia tolak — dokumen/data tidak valid)
            │     └─ (pendaftar revisi & upload ulang) ──→ berkas_diupload
            └→ berkas_diverifikasi (panitia: SEMUA dokumen + data form OK)
                 └→ pembayaran_diupload (pendaftar upload bukti transfer + kode unik)
                      ├→ pembayaran_ditolak (panitia tolak — bukti tidak valid/kurang)
                      │     └─ (pendaftar upload ulang) ──→ pembayaran_diupload
                      └→ dijadwalkan_tes (panitia: bukti transfer valid)
                           └→ diumumkan_lulus
                           └→ diumumkan_tidak_lulus
                                └→ daftar_ulang (pendaftar bayar daftar ulang)
                                     └→ selesai (daftar ulang dikonfirmasi panitia)
                                     └→ gagal_daftar_ulang (batas waktu terlewat)
```

Transisi status **hanya boleh dilakukan oleh role yang berwenang** — didefinisikan detail di `arsitektur-flow-pendaftaran.md`.

> **Catatan (revisi v1.1):** urutan dibalik dari rencana semula — verifikasi dokumen+data terjadi **sebelum** pembayaran (bukan dibundel jadi satu keputusan setelah pembayaran), supaya panitia tidak perlu meminta uang sebelum yakin aplikasinya sah. Status besar pendaftaran **tidak otomatis berubah** saat satu dokumen ditolak/diterima secara individual — itu adalah status per-dokumen yang independen (`jg_berkas.status`). Detail penuh mekanisme verifikasi dokumen → `arsitektur-verifikasi-berkas.md`; detail pembayaran (kode unik, rekening, dll) → `arsitektur-pembayaran.md`.

---

## Komponen Utama

### 1. Autentikasi & Role (Native WordPress)

**Identifier login:** Email (field `user_email` di `wp_users`).

**Validasi uniqueness saat registrasi:**
- Email — tidak boleh sudah terdaftar di `wp_users`
- Nomor WhatsApp — tidak boleh sudah ada di tabel `jg_pendaftar_profil`

Tidak ada verifikasi email (klik link aktivasi) di v1.

**Custom roles** (dibuat via `add_role()`):

| Role | Deskripsi | Catatan |
|---|---|---|
| `pendaftar` | Calon mahasiswa — isi form, upload berkas, lihat status sendiri | Role default saat registrasi |
| `panitia_pmb` | Staff PMB — lihat data pendaftar, ubah status, ekspor | |
| `verifikator_berkas` | Khusus approve/reject dokumen | Tidak bisa ubah data lain |
| `admin_pmb` | Kelola gelombang, prodi, form builder, pengaturan | |

Sementara: **WordPress `administrator`** menangani semua fungsi admin. Roles dikembangkan & aktifkan bertahap di iterasi berikutnya.

**Dashboard front-end custom** untuk pendaftar (di luar wp-admin). Setelah login sebagai `pendaftar`, WordPress me-redirect ke halaman dashboard custom ini, bukan ke `/wp-admin`.

### 2. Manajemen Gelombang (Dinamis)

Admin membuat gelombang dengan:
- Nama gelombang (mis. "Gelombang 1 - 2026/2027")
- Tahun akademik
- Tanggal & waktu buka pendaftaran
- Tanggal & waktu tutup pendaftaran
- Status (aktif / nonaktif manual)

Form pendaftaran aktif/nonaktif **otomatis berdasarkan tanggal**, dengan override manual oleh admin.

Setiap gelombang memiliki **skema form sendiri** yang terisolasi — lihat keputusan di bagian Form Builder.

### 3. Form Builder Dinamis

**Skema form terikat per gelombang** (bukan global). Setiap gelombang punya snapshot definisi field-nya sendiri.

**Keputusan ini final** — lihat pertimbangan di bagian Trade-off di bawah.

Untuk efisiensi admin, tersedia tombol **"Clone skema dari gelombang sebelumnya"** — admin bisa mulai dari salinan gelombang lama dan memodifikasinya, tanpa harus membuat ulang dari nol.

Tipe field yang didukung di v1:

| Tipe | Contoh penggunaan |
|---|---|
| `text` | Nama, nama sekolah |
| `textarea` | Alamat lengkap, catatan |
| `number` | Tahun lulus |
| `date` | Tanggal lahir, tanggal lahir orang tua |
| `email` | Email |
| `phone` | Nomor HP / WhatsApp |
| `nik` | NIK (validasi 16 digit numerik) |
| `select` | Agama, pendidikan orang tua, asal SMA |
| `radio` | Jenis kelamin, range penghasilan orang tua |
| `checkbox` | Sumber informasi (bisa pilih banyak) |

**Field inti (tidak bisa dihapus admin):** Nama Lengkap, Email, NIK, NISN, Nomor WhatsApp. Field-field ini selalu ada di setiap gelombang karena dipakai oleh sistem (auth, uniqueness check, komunikasi). Label dan properti tampilan tetap bisa dikustomisasi admin, tapi field-nya tidak bisa dihapus.

> **Revisi dari rencana awal:** `file_upload` **tidak lagi** tersedia sebagai tipe field di Form Builder. Semua dokumen (KTP, KK, Ijazah, Pas Foto, Bukti Bayar) ditangani lewat sistem **Tipe Berkas** terpisah (tabel `jg_tipe_berkas`, Step 3 — "Upload Berkas") yang berjalan setelah formulir disubmit, bukan sebagai bagian dari formulir dinamis. Alasan: upload file butuh penanganan berbeda (private storage, validasi mime-type, preview, verifikasi per dokumen) yang lebih konsisten dikelola di satu sistem khusus, daripada bercampur dengan field formulir biasa.

Detail model penyimpanan (EAV), validasi, dan conditional logic → `arsitektur-form-builder.md`.

### 4. Upload Berkas & Media

Integrasi dengan WordPress Media Library untuk UI upload yang familiar.

**Keputusan keamanan kritis:** File dokumen sensitif (KTP, KK, Ijazah, Foto, Bukti Bayar) **TIDAK boleh diakses via URL publik**. WordPress Media Library secara default menyimpan file di lokasi yang dapat diakses publik — ini tidak aman untuk data pribadi mahasiswa.

Strategi yang diadopsi:
1. File sensitif disimpan di direktori **di luar web root** (atau direktori dengan `.htaccess deny all`).
2. Akses file hanya melalui **PHP endpoint internal** dengan capability check — URL yang terlihat user adalah endpoint plugin, bukan URL file asli.
3. Media Library tetap dipakai untuk UI upload (fitur crop, preview), tapi path penyimpanan di-override.

Detail implementasi → `arsitektur-berkas-media.md`.

### 5. Notifikasi Email

Hierarki konfigurasi:
1. Jika SMTP dikonfigurasi di pengaturan plugin (host, port, username, password) → gunakan SMTP tersebut via hook `phpmailer_init`
2. Jika tidak → fallback ke `wp_mail()` bawaan WordPress

Plugin menyediakan halaman pengaturan SMTP (mendukung Google Workspace MX / Gmail SMTP). Panduan konfigurasi akan disertakan dalam dokumentasi pengguna.

Event notifikasi v1:
- Registrasi berhasil (welcome email)
- Perubahan status pendaftaran
- Pengumuman hasil seleksi

Detail → `arsitektur-notifikasi-email.md`.

### 6. Ekspor Data

| Actor | Format | Isi |
|---|---|---|
| Admin | Excel / CSV | Semua data pendaftar, filter per gelombang/prodi/status |
| Admin | PDF | Formulir lengkap per pendaftar (untuk arsip) |
| Pendaftar | PDF | Formulir sendiri lengkap (untuk dicetak/dikirim) |

Library yang digunakan:
- Excel/CSV: **PhpSpreadsheet** (via Composer)
- PDF: **mPDF** (via Composer) — dipilih karena support UTF-8 dan Bahasa Indonesia lebih baik dari TCPDF

Detail → `arsitektur-ekspor.md`.

---

## Struktur Folder Plugin

```
jalagistrasi/
├── jalagistrasi.php              # Entry point — metadata plugin, bootstrap minimal
├── composer.json                 # PSR-4 autoload, dependencies
├── composer.lock
├── vendor/                       # Dependencies (jangan diedit manual)
│
├── src/                          # Namespace root: Webane\Jalagistrasi
│   ├── Plugin.php               # Bootstrap class (singleton) — register hooks
│   ├── Installer.php            # Aktivasi/deaktivasi — create/update DB tables
│   │
│   ├── Admin/                   # Semua yang tampil di wp-admin
│   │   ├── AdminMenu.php        # Register menu & submenu admin
│   │   ├── GelombangController.php
│   │   ├── ProgramStudiController.php
│   │   ├── PendaftarController.php
│   │   ├── FormBuilderController.php
│   │   ├── EksporController.php
│   │   └── PengaturanController.php   # SMTP, pengaturan umum
│   │
│   ├── Frontend/                # Dashboard pendaftar (non-admin)
│   │   ├── DashboardController.php
│   │   ├── RegistrasiController.php
│   │   ├── FormPendaftaranController.php
│   │   ├── UploadBerkasController.php
│   │   ├── PembayaranController.php
│   │   └── EksporPdfController.php    # Export PDF untuk pendaftar
│   │
│   ├── Auth/
│   │   ├── RoleManager.php      # add_role, remove_role, capability mapping
│   │   ├── LoginHandler.php     # Custom login form, redirect setelah login
│   │   └── BerkasAccessChecker.php   # Capability check untuk akses file
│   │
│   ├── Model/                   # Value objects / DTOs (immutable, tanpa DB logic)
│   │   ├── Pendaftaran.php
│   │   ├── Gelombang.php
│   │   ├── ProgramStudi.php
│   │   ├── FormField.php
│   │   └── Pembayaran.php
│   │
│   ├── Repository/              # Semua query DB ada di sini (tidak ada SQL di Controller)
│   │   ├── PendaftaranRepository.php
│   │   ├── GelombangRepository.php
│   │   ├── FormSchemaRepository.php
│   │   ├── BerkasRepository.php
│   │   └── PembayaranRepository.php
│   │
│   ├── Service/                 # Business logic yang tidak fit di Controller/Repository
│   │   ├── EmailService.php
│   │   ├── EksporService.php
│   │   ├── BerkasService.php    # Handle upload, validasi tipe file, private storage
│   │   └── StatusService.php    # Transisi status, validasi, trigger notifikasi
│   │
│   ├── Enum/                    # PHP 8.1 backed enums
│   │   ├── StatusPendaftaran.php
│   │   └── TipeField.php
│   │
│   └── Exception/
│       ├── PendaftaranException.php
│       ├── BerkasException.php
│       └── AuthorizationException.php
│
├── templates/                   # Template PHP (HTML output)
│   ├── admin/
│   ├── dashboard/               # Dashboard pendaftar
│   └── email/                   # Template body email
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── docs/                        # Dokumen arsitektur (folder ini)
└── languages/                   # File .pot / .po untuk i18n
```

---

## Database — Ringkasan Tabel

Semua tabel menggunakan prefix `jg_` (tambahan di atas prefix WordPress, mis. `wp_jg_gelombang`).

| Tabel | Deskripsi |
|---|---|
| `jg_tahun_ajaran` | Tahun ajaran (mis. "2026/2027") — entitas tersendiri, satu tahun ajaran punya banyak gelombang → `arsitektur-tahun-ajaran.md` |
| `jg_gelombang` | Gelombang pendaftaran — nama, FK ke `jg_tahun_ajaran`, tanggal buka/tutup, status, biaya pendaftaran |
| `jg_program_studi` | Daftar program studi kampus — nama, kode, kuota, status aktif |
| `jg_pendaftaran` | Record pendaftaran utama — FK ke `wp_users.ID` dan `jg_gelombang.id`, nomor pendaftaran, status, kode unik pembayaran |
| `jg_pendaftaran_prodi` | Pilihan prodi per pendaftaran — FK ke `jg_pendaftaran.id`, `jg_program_studi.id`, urutan pilihan (1/2) |
| `jg_form_field` | Definisi field formulir per gelombang — tipe, label, urutan, validasi (JSON), is_required, is_core |
| `jg_form_jawaban` | Jawaban pendaftar — FK ke `jg_pendaftaran.id` dan `jg_form_field.id`, nilai |
| `jg_tipe_berkas` | Jenis dokumen yang wajib/opsional diupload per gelombang (KTP, KK, Ijazah, Pas Foto, dll) — kode, label, wajib/tidak, maks ukuran. "Pas Foto" otomatis ter-seed di setiap gelombang, sisanya dikonfigurasi manual admin |
| `jg_berkas` | Dokumen yang diupload — FK ke `jg_pendaftaran.id`, path file private, tipe berkas, status verifikasi per dokumen (`pending`/`diterima`/`ditolak`) + catatan panitia → `arsitektur-verifikasi-berkas.md` |
| `jg_pembayaran` | Bukti transfer biaya pendaftaran — FK ke `jg_pendaftaran.id` dan `jg_rekening_bank.id`, jumlah, file bukti, 1 baris aktif per pendaftaran → `arsitektur-pembayaran.md` |
| `jg_rekening_bank` | Rekening tujuan transfer (bisa lebih dari satu) — nama bank, nomor rekening, nama pemilik, aktif/tidak |
| `jg_status_history` | Audit trail — setiap perubahan status dicatat beserta actor dan timestamp |

Skema DDL lengkap dengan tipe kolom, indeks, dan alasan setiap keputusan → `arsitektur-database.md`.

---

## Dokumen Arsitektur yang Akan Dibuat

| File | Topik | Prioritas |
|---|---|---|
| `arsitektur-overview.md` | Dokumen ini | — |
| `arsitektur-database.md` | DDL lengkap, indeks, relasi antar tabel | **Tinggi** — harus final sebelum coding apapun |
| `arsitektur-auth.md` | Roles, capabilities, login/register flow, redirect logic | **Tinggi** |
| `arsitektur-form-builder.md` | Model EAV, tipe field, validasi, conditional logic, performa laporan | **Tinggi** |
| `arsitektur-flow-pendaftaran.md` | State machine detail, siapa trigger transisi mana | Menengah |
| `arsitektur-berkas-media.md` | Private file serving, integrasi WP Media Library | Menengah |
| `arsitektur-verifikasi-berkas.md` | Mekanisme terima/tolak dokumen per item, terpisah dari status besar pendaftaran | **Tinggi** — sudah diimplementasikan |
| `arsitektur-pembayaran.md` | Pembayaran biaya pendaftaran — kode unik, multi-rekening, satu halaman terpadu | **Tinggi** — sudah diimplementasikan |
| `arsitektur-tahun-ajaran.md` | Tahun Ajaran sebagai entitas tersendiri (hierarki Tahun Ajaran → Gelombang → Pendaftar) | **Tinggi** — sudah diimplementasikan |
| `arsitektur-identitas-institusi.md` | Setting logo, kop surat — persiapan untuk fitur ekspor PDF nanti | Menengah — sudah diimplementasikan |
| `arsitektur-dashboard-admin.md` | Dashboard admin dengan statistik pendaftaran (kartu + tabel) + filter Tahun Ajaran | Menengah — sudah diimplementasikan |
| `arsitektur-landing-publik.md` | Halaman publik info cara pendaftaran + gelombang aktif + tombol daftar | Menengah — sudah diimplementasikan |
| `arsitektur-color-palette.md` | Setting warna brand primer & sekunder, override CSS variable otomatis | **Tinggi** — sudah diimplementasikan |
| `arsitektur-login-register.md` | Halaman Masuk/Daftar custom — tab, gradient brand, glassmorphism, tanpa header/footer | **Tinggi** — sudah diimplementasikan |
| `arsitektur-notifikasi-email.md` | SMTP settings, event triggers, template | Rendah |
| `arsitektur-ekspor.md` | Format, library, batasan, cara kerja | Rendah |
| `arsitektur-lessons-learned.md` | Catatan bug dan keputusan yang pernah salah | Ongoing |

---

## Trade-off: Skema Form per Gelombang vs Global

**Keputusan: Skema form terikat per gelombang.**

| Aspek | Skema per Gelombang ✓ | Skema Global |
|---|---|---|
| **Integritas data historis** | Data gelombang lama tidak pernah rusak akibat perubahan form baru | Perubahan field bisa merusak cara baca data lama |
| **Laporan per gelombang** | Konsisten — field yang dilaporkan sama dengan field saat isi form | Kompleks — harus track versi schema saat jawaban disimpan |
| **Kemudahan admin** | Harus clone untuk gelombang baru (kita sediakan tombol clone) | Satu tempat kelola, tapi risiko tidak terasa |
| **Kompleksitas implementasi** | Sedikit lebih tinggi di awal | Lebih simpel di awal, lebih rumit di kemudian hari |
| **Refactor cost** | Rendah | Sangat tinggi jika harus diubah setelah data masuk |

Kesimpulan: skema per gelombang adalah satu-satunya pilihan yang aman untuk sistem jangka panjang dengan data real.

---

## Keputusan Pending

*(Tidak ada — semua sudah dikonfirmasi per 2026-06-24)*

---

## Asumsi yang Dibuat

- Satu kampus (single-site WordPress, bukan Multisite).
- Tidak ada integrasi payment gateway di v1 — cukup upload bukti transfer manual.
- Notifikasi WA/SMS tidak ada di v1.
- Bahasa antarmuka: Bahasa Indonesia.
