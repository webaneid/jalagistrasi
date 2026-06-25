# Arsitektur Pembayaran Biaya Pendaftaran — Plugin Jalagistrasi

**Tanggal:** 2026-06-25 (diimplementasikan)
**Status:** v1.0 — diimplementasikan
**Author:** Webane Indonesia

---

## Konteks

Step 4 dari alur PMB: setelah dokumen (berkas) dan data formulir pendaftar **dinyatakan valid oleh panitia**, mahasiswa diminta mengupload bukti transfer biaya pendaftaran. Setelah bukti transfer diperiksa dan disetujui, pendaftaran lanjut ke tahap Tes/Seleksi.

Dokumen ini **hanya rancangan** — tidak ada kode yang dieksekusi sampai rancangan ini disetujui.

---

## Keputusan Kunci (sudah dikonfirmasi)

| Keputusan | Pilihan yang dipilih |
|---|---|
| Urutan pipeline | **Verifikasi dulu, baru Pembayaran** — bukan urutan asli (Pembayaran lalu verifikasi bareng) di `arsitektur-overview.md`. Dokumen overview akan direvisi menyusul setelah rancangan ini disetujui. |
| Makna "data form valid" | **Keputusan holistik** — admin melihat semua data + dokumen sekaligus di halaman detail, lalu satu keputusan: Diverifikasi / Ditolak untuk seluruh pendaftaran. Tidak ada verifikasi per-field. |
| Scope tabel `jg_pembayaran` | **Khusus biaya pendaftaran** — tidak dirancang generik untuk Daftar Ulang (UKT) di step 8. Step 8 akan didesain ulang terpisah nanti. |
| Rekening tujuan | **Bisa lebih dari satu bank** — mahasiswa pilih salah satu rekening aktif saat upload bukti bayar. |
| Nominal transfer | **Harus pas/exact** — dibantu **kode unik per pendaftaran** ditambahkan ke nominal biaya, supaya tiap transfer punya angka berbeda dan mudah dicocokkan dengan mutasi rekening. |
| Notifikasi email | **Konsep dibutuhkan** (trigger point harus ada di kode), **tapi implementasi pengiriman email ditunda** — sejalan dengan keputusan project-wide bahwa seluruh sistem notifikasi email (lihat `arsitektur-overview.md` §5) belum dibangun. Lihat bagian "Notifikasi" di bawah. |

### Mengapa "Verifikasi dulu, baru Pembayaran" lebih masuk akal

Kampus tidak perlu meminta uang sebelum yakin aplikasinya sah (dokumen asli, data lengkap, bukan spam/percobaan acak). Urutan baru ini juga konsisten dengan mekanisme yang **sudah dibangun**: keputusan "Diverifikasi/Ditolak" untuk dokumen+data sudah ada lewat panel "Update Status" admin (lihat `arsitektur-verifikasi-berkas.md`) — pembayaran tinggal jadi gate berikutnya setelah keputusan itu.

---

## Pipeline & State Machine (Revisi)

```
draft
  └→ submitted (mahasiswa submit formulir)
       └→ berkas_diupload (mahasiswa upload semua dokumen wajib)
            ├→ berkas_ditolak (admin: dokumen/data ada yang tidak valid)
            │     └─ (mahasiswa revisi & upload ulang) ──→ berkas_diupload
            └→ berkas_diverifikasi (admin: SEMUA dokumen + data form OK)
                 └→ pembayaran_diupload (mahasiswa upload bukti transfer)
                      ├→ pembayaran_ditolak (admin: bukti transfer tidak valid/kurang)
                      │     └─ (mahasiswa upload ulang) ──→ pembayaran_diupload
                      └→ dijadwalkan_tes (admin: bukti transfer valid)
                           └→ diumumkan_lulus / diumumkan_tidak_lulus
                                └→ daftar_ulang → selesai / gagal_daftar_ulang
```

### Perubahan di `src/Enum/StatusPendaftaran.php`

| Transisi | Status saat ini | Rencana |
|---|---|---|
| `berkas_diupload →` | `[pembayaran_diupload, berkas_ditolak]` | `[berkas_diverifikasi, berkas_ditolak]` |
| `berkas_diverifikasi →` | `[dijadwalkan_tes]` | `[pembayaran_diupload]` |
| `pembayaran_diupload →` | *(status ini belum punya transisi keluar — baru ditambahkan saat tabel `jg_pembayaran` dibuat)* | `[dijadwalkan_tes, pembayaran_ditolak]` |
| `pembayaran_ditolak →` *(case baru)* | — | `[pembayaran_diupload]` |

**Case enum baru:** `PembayaranDitolak = 'pembayaran_ditolak'`.

**Tidak diubah:** transisi `berkas_diupload → berkas_ditolak` yang sudah ditambahkan minggu lalu (untuk dukung penolakan dokumen sebelum status besar pindah) tetap dipertahankan.

---

## Mekanisme Verifikasi: TIDAK Perlu Tabel Status Per-Item

Berbeda dengan dokumen (banyak file, butuh `jg_berkas.status` per item — lihat `arsitektur-verifikasi-berkas.md`), pembayaran **hanya satu bukti transfer per pendaftaran**. Karena granularitasnya 1:1 dengan pendaftaran, keputusan terima/tolak **cukup lewat panel "Update Status" yang sudah ada** — tidak perlu kolom `status`/`catatan`/`verified_by` terpisah di tabel `jg_pembayaran`.

Alasan: menambah lapisan verifikasi terpisah untuk entity yang cuma satu baris per pendaftaran adalah duplikasi tanpa manfaat — admin tinggal lihat bukti transfer di halaman detail, lalu pilih status baru (`dijadwalkan_tes` atau `pembayaran_ditolak`) + isi `catatan_panitia` (kolom yang sudah ada di `jg_pendaftaran`) kalau ditolak. Sama persis dengan cara kerja transisi `berkas_diupload → berkas_ditolak` hari ini.

`jg_pembayaran` jadi tabel **penyimpanan file + metadata saja**, bukan entity yang diverifikasi sendiri.

### Revisi: Panel Verifikasi Khusus (Anti Salah Klik / Penipuan)

**Masalah yang ditemukan saat pemakaian:** dropdown generik "Update Status" terlalu mudah di-klik tanpa admin benar-benar mengecek mutasi rekening dulu — risiko dana belum masuk tapi pendaftaran sudah dilanjutkan ke tahap tes (kerugian finansial institusi).

**Perbaikan (tanpa mengubah keputusan di atas — tetap tidak ada kolom status terpisah di DB):** saat `currentStatus === pembayaran_diupload`, panel "Update Status" di halaman admin **diganti tampilannya** (bukan dropdown bebas pilih) menjadi:
- Checkbox konfirmasi wajib dicentang dulu: *"Saya sudah memeriksa mutasi rekening dan dana sebesar Rp X benar-benar sudah masuk"* — tombol "Dana Diterima" tetap `disabled` sampai checkbox ini dicentang.
- Tombol terpisah "✕ Tolak Pembayaran" yang membuka textarea catatan **wajib diisi** sebelum bisa submit.

Kedua tombol tetap memanggil handler `handleUpdateStatus()` yang sama (hanya `new_status` sudah di-hardcode di `<input type="hidden">`, bukan dipilih bebas dari dropdown) — tidak ada perubahan di layer backend/database, murni penguatan UX untuk mencegah kelalaian admin. Untuk status lain (bukan `pembayaran_diupload`), dropdown generik tetap dipakai seperti biasa.

---

## Kode Unik Pembayaran

**Masalah yang dipecahkan:** kalau semua mahasiswa di satu gelombang bayar nominal yang sama persis (mis. semua Rp 200.000), admin tidak bisa mencocokkan satu baris mutasi rekening dengan satu pendaftaran tertentu — apalagi kalau nama pengirim di rekening berbeda dari nama mahasiswa (transfer dari rekening orang tua, dsb).

**Solusi:** tiap pendaftaran mendapat **kode unik 3 digit (001–999)** begitu admin memverifikasi dokumen (status pindah ke `berkas_diverifikasi`). Mahasiswa diminta transfer **persis**:

```
Total Transfer = biaya_pendaftaran (gelombang) + kode_unik

Contoh: Rp 200.000 + 047  →  mahasiswa transfer Rp 200.047
```

Karena nominalnya jadi unik per pendaftaran (dalam satu gelombang), admin tinggal cocokkan angka di mutasi rekening dengan kode unik di sistem — tidak perlu mengandalkan nama pengirim yang bisa berbeda-beda atau ambigu.

### Aturan generate kode unik

- Dibuat otomatis **saat admin transisi status ke `berkas_diverifikasi`** lewat panel "Update Status" yang sudah ada (titik hook: `PendaftarController::handleUpdateStatus()`).
- Angka acak 1–999, dicek unik **dalam lingkup gelombang yang sama** (bukan global) — supaya kode tidak akan collide dengan kode unik pendaftaran lain di gelombang yang sama yang nominal biayanya sama juga.
- Setelah dibuat, kode **tidak pernah berubah/digenerate ulang** untuk pendaftaran yang sama, walau pendaftarannya nanti ditolak (`pembayaran_ditolak`) dan upload ulang — supaya konsisten dan tidak bingung kalau mahasiswa sempat lihat angka pertama.
- Kolom baru `kode_unik_pembayaran` di tabel `jg_pendaftaran` (lihat skema di bawah).
- Kemungkinan kehabisan kode (lebih dari 999 pendaftaran aktif menunggu pembayaran dalam satu gelombang) **sangat tidak mungkin** secara realistis, tapi retry-loop perlu ada pengaman (mis. maksimal 50x percobaan lalu fallback error log) — detail teknis ditentukan saat implementasi.

### Verifikasi nominal di sisi admin

Karena nominal sekarang harus pas, admin tidak perlu menebak-nebak — bandingkan langsung:
- `jumlah` yang dilaporkan mahasiswa (self-report saat upload) **vs**
- `total_seharusnya` = `gelombang.biaya_pendaftaran + pendaftaran.kode_unik_pembayaran` (dihitung on-the-fly, tidak disimpan terpisah)

Kalau dua angka itu cocok → tampilkan badge hijau "✓ Nominal sesuai" otomatis di halaman detail admin. Kalau tidak cocok → badge merah "⚠ Tidak sesuai, cek manual" sebagai peringatan visual saja (bukan validasi blocking — admin tetap yang memutuskan lewat panel Update Status, sesuai prinsip di bagian "Mekanisme Verifikasi").

---

## Skema Database

### Tabel baru: `jg_rekening_bank` (rekening tujuan — bisa lebih dari satu)

```sql
CREATE TABLE jg_rekening_bank (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_bank       VARCHAR(100) NOT NULL,         -- mis. "BCA", "Bank Mandiri"
  nomor_rekening  VARCHAR(50) NOT NULL,
  nama_pemilik    VARCHAR(150) NOT NULL,
  is_aktif        TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- non-aktifkan tanpa hapus data historis
  urutan          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
```

Dikelola lewat halaman admin baru (CRUD sederhana, pola sama dengan `TipeBerkasController`) — bisa tambah/edit/nonaktifkan rekening kapan saja. Mahasiswa hanya melihat & memilih dari rekening yang `is_aktif = 1`.

### Kolom baru di `jg_pendaftaran`

```sql
ALTER TABLE jg_pendaftaran ADD COLUMN kode_unik_pembayaran SMALLINT UNSIGNED DEFAULT NULL;
```

```sql
CREATE TABLE jg_pembayaran (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pendaftaran_id      BIGINT UNSIGNED NOT NULL,
  rekening_bank_id    BIGINT UNSIGNED NOT NULL,       -- FK ke jg_rekening_bank — rekening yang dipilih mahasiswa
  jumlah              DECIMAL(12,2) NOT NULL,         -- nominal yang dikonfirmasi mahasiswa saat upload
  nama_pengirim       VARCHAR(150) DEFAULT NULL,      -- opsional — kalau transfer dari rekening bukan milik sendiri
  file_path           VARCHAR(500) NOT NULL,
  file_name_original  VARCHAR(255) NOT NULL,
  file_name_stored    VARCHAR(255) NOT NULL,
  file_size           INT UNSIGNED NOT NULL,
  mime_type           VARCHAR(100) NOT NULL,
  uploaded_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pendaftaran (pendaftaran_id),         -- 1 bukti aktif per pendaftaran, re-upload = replace
  KEY idx_pendaftaran_id (pendaftaran_id),
  KEY idx_rekening_bank_id (rekening_bank_id)
);
```

`UNIQUE KEY uq_pendaftaran` memastikan cuma ada satu baris aktif per pendaftaran — re-upload (setelah `pembayaran_ditolak`) **menghapus baris lama lalu insert baru**, sama seperti pola `BerkasRepository::deleteByPendaftaranAndTipe()` + `insert()` yang sudah ada untuk dokumen. `rekening_bank_id` disimpan per transaksi (bukan cuma di pendaftaran) supaya riwayat tetap valid walau admin nanti menonaktifkan/menghapus rekening tersebut.

### Kolom baru di `jg_gelombang`

```sql
ALTER TABLE jg_gelombang ADD COLUMN biaya_pendaftaran DECIMAL(12,2) NOT NULL DEFAULT 0;
```

Nominal biaya pendaftaran **berbeda per gelombang** (admin bisa naikkan/turunkan biaya tiap periode pendaftaran baru) — dikonfigurasi di form Gelombang yang sudah ada (`GelombangController`), ditampilkan ke mahasiswa di halaman upload bukti bayar sebagai acuan ("Silakan transfer Rp X").

### Rekening tujuan — dikelola lewat halaman admin baru, bukan opsi tunggal

~~Pengaturan global 1 rekening~~ — digantikan tabel `jg_rekening_bank` di atas, karena institusi bisa punya beberapa rekening tujuan (mis. BCA + Mandiri) dan mahasiswa memilih salah satu. Rekening-rekening ini **berlaku untuk semua gelombang** (bukan per gelombang) — hanya nominal biaya yang beda per gelombang, rekening tujuannya sama.

---

## Keputusan UI: Satu Halaman Terpadu (Mahasiswa & Admin)

**Perubahan penting dari rancangan awal.** Bukan 3 halaman terpisah (Detail / Upload Berkas / Upload Pembayaran) yang dipindah-pindah sesuai tahap — **semuanya digabung jadi satu halaman**, baik di sisi mahasiswa maupun admin, berisi 3 section:

1. **Section Data Formulir** — jawaban formulir pendaftaran (sudah ada, read-only kecuali masih `draft`)
2. **Section Dokumen Persyaratan** — daftar dokumen wajib (KTP, KK, Ijazah, Foto, dst), **upload/ganti file langsung di section ini** (tidak pindah ke halaman lain)
3. **Section Bukti Pembayaran** — info rekening + total bayar + **upload bukti transfer langsung di section ini**

Section yang belum waktunya **ditampilkan terkunci** (abu-abu, dengan penjelasan singkat kenapa terkunci), bukan disembunyikan total — supaya mahasiswa tetap bisa melihat alur lengkap proses dari awal meski belum sampai di tahap itu.

### Dampak ke kode yang sudah ada

Ini mengubah struktur yang **sudah diimplementasikan** untuk Step 3 (Upload Berkas), bukan cuma menambah Step 4:

| Saat ini (sudah jalan) | Rencana baru |
|---|---|
| `templates/frontend/detail/index.php` — lihat-lihat saja, read-only | Jadi **satu-satunya halaman** — gabung kemampuan lihat + upload |
| `templates/frontend/berkas/upload.php` — halaman terpisah, route `?action=upload-berkas` | **Dilebur** jadi Section Dokumen Persyaratan di halaman detail. Route `upload-berkas` dipensiunkan. |
| `templates/frontend/dashboard/index.php` — tombol "Lanjutkan Upload Berkas" mengarah ke halaman terpisah | Tombol di dashboard tetap ada, tapi mengarah ke halaman detail (yang sekarang sudah punya semua section) |
| (rencana lama) `templates/frontend/pembayaran/upload.php` — halaman baru terpisah | **Tidak dibuat terpisah** — langsung jadi Section Bukti Pembayaran di halaman detail yang sama |

### Kapan tiap section terkunci/terbuka (sisi mahasiswa)

| Status pendaftaran | Section Formulir | Section Dokumen | Section Pembayaran |
|---|---|---|---|
| `draft` | Bisa diedit | Terkunci ("Selesaikan formulir dulu") | Terkunci |
| `submitted` / `berkas_diupload` / `berkas_ditolak` | Read-only | **Terbuka** — upload/ganti dokumen | Terkunci ("Menunggu verifikasi dokumen") |
| `berkas_diverifikasi` / `pembayaran_ditolak` | Read-only | Read-only (selesai, terverifikasi) | **Terbuka** — upload bukti transfer |
| `pembayaran_diupload` dan seterusnya | Read-only | Read-only | Read-only ("Menunggu verifikasi pembayaran" / status berikutnya) |

### Sisi admin

Halaman detail pendaftar (`templates/admin/pendaftar/detail.php`) **sudah** dirancang sebagai satu halaman (lihat `arsitektur-verifikasi-berkas.md`) — section "Dokumen Berkas" sudah ada, tinggal tambah section baru **"Bukti Pembayaran"** setelahnya, isinya:
- **Kode unik pendaftaran** ditampilkan jelas (mis. "Kode unik: 047")
- **Badge perbandingan otomatis**: nominal dilaporkan mahasiswa vs `biaya_pendaftaran + kode_unik` — hijau "✓ Sesuai" kalau cocok, merah "⚠ Tidak sesuai" kalau beda (lihat bagian "Kode Unik Pembayaran")
- Rekening tujuan yang dipilih mahasiswa (nama bank + nomor rekening, untuk dicocokkan ke mutasi bank yang benar kalau institusi punya >1 rekening)
- Thumbnail bukti transfer (klik untuk lightbox preview, reuse pola modal yang sama dengan dokumen)
- Nama pengirim (jika diisi)

**Tidak ada tombol Terima/Tolak di section ini** (lihat bagian "Mekanisme Verifikasi" di atas) — admin memutuskan lewat panel "Update Status" yang sudah ada di sebelah kanan, pilih `dijadwalkan_tes` (terima) atau `pembayaran_ditolak` (tolak + isi catatan).

Jadi di sisi admin, perubahan dari rencana sebelumnya **minimal** (cuma nambah 1 section) — admin memang sudah didesain satu-halaman sejak fitur verifikasi dokumen dibangun. Perubahan besar justru di sisi mahasiswa, yang sebelumnya 2 halaman terpisah (Detail + Upload Berkas), sekarang dilebur jadi 1.

---

## Komponen yang Perlu Dibuat (Rencana Implementasi — BELUM dieksekusi)

| Komponen | File | Catatan |
|---|---|---|
| Tabel `jg_pembayaran` | `src/Installer.php` | DDL baru + `DB_VERSION` bump |
| Tabel `jg_rekening_bank` | `src/Installer.php` | DDL baru, sama bump `DB_VERSION` |
| Kolom `biaya_pendaftaran` | `src/Installer.php`, `GelombangController` | Tambah ke form Gelombang |
| Kolom `kode_unik_pembayaran` | `src/Installer.php` (ALTER `jg_pendaftaran`) | Diisi otomatis saat transisi ke `berkas_diverifikasi` |
| `PembayaranRepository` | `src/Repository/PembayaranRepository.php` | `insert()`, `findByPendaftaran()`, `deleteByPendaftaran()` |
| `RekeningBankRepository` | `src/Repository/RekeningBankRepository.php` | `findAllAktif()`, `findById()`, `insert()`, `update()`, `delete()` |
| `RekeningBankController` (admin CRUD) | `src/Admin/RekeningBankController.php` + menu baru | Pola sama dengan `TipeBerkasController` |
| Generator kode unik | Method baru, dipanggil dari `PendaftarController::handleUpdateStatus()` saat `$newStatus === 'berkas_diverifikasi'` | Random 1–999 + retry-loop unik per gelombang |
| Enum `PembayaranDitolak` | `src/Enum/StatusPendaftaran.php` | + revisi `allowedTransitions()` |
| Upload handler | `PendaftaranController::handleUploadPembayaran()` | Hook `admin_post_jg_upload_pembayaran`, validasi file + rekening + transisi status |
| **Refactor `renderDetailPendaftaran()`** | `RegistrasiController.php` | Tambah data dokumen (tipe berkas + status) & data pembayaran (rekening aktif, total, kode unik) ke variabel yang dikirim ke template — sebelumnya cuma data view-only |
| **Refactor besar template detail** | `templates/frontend/detail/index.php` | Tambah section "Dokumen Persyaratan" (form upload inline, pindahan dari `berkas/upload.php`) + section "Bukti Pembayaran" (form upload inline, baru). Logic locked/unlocked per section sesuai tabel status di atas |
| **Pensiunkan route lama** | `RegistrasiController::renderUploadBerkas()`, `templates/frontend/berkas/upload.php` | Tidak dipakai lagi setelah upload dipindah ke section inline — dihapus atau di-redirect ke halaman detail |
| Section admin | `templates/admin/pendaftar/detail.php` | Section "Bukti Pembayaran" + badge perbandingan nominal otomatis |
| Preview file aman | Reuse `wp_ajax_jg_preview_berkas` pattern, endpoint baru `jg_preview_pembayaran` | Private storage, capability check sama |
| CTA dashboard mahasiswa | `templates/frontend/dashboard/index.php` | Tombol tetap ada, tapi semua mengarah ke halaman detail (bukan lagi ke halaman upload terpisah) |
| Trigger notifikasi (hook saja, tanpa kirim email) | `PendaftarController::handleUpdateStatus()` | `do_action('jg_pendaftaran_status_changed', ...)` — lihat bagian Notifikasi |

---

## Notifikasi — Hook Disiapkan, Pengiriman Email Ditunda

Sesuai jawaban Anda: notifikasi **dibutuhkan secara konsep** (mahasiswa harus tahu kapan mereka boleh lanjut bayar, dan kapan ditolak), tapi **implementasi pengiriman email untuk seluruh sistem ditunda** — bukan cuma untuk fitur ini.

Supaya tidak perlu refactor besar nanti saat `arsitektur-notifikasi-email.md` akhirnya dibangun, rencana ini menyiapkan **titik hook saja**:

```php
// di dalam PendaftarController::handleUpdateStatus(), setelah update berhasil
do_action('jg_pendaftaran_status_changed', $pendaftaranId, $oldStatus, $newStatus);
```

Saat sistem email dibangun nanti, cukup `add_action('jg_pendaftaran_status_changed', ...)` di tempat lain — tidak perlu sentuh `PendaftarController` lagi. Untuk saat ini, hook ini **tidak punya listener apapun** (no-op), mahasiswa tetap hanya tahu lewat dashboard (badge status + CTA kontekstual yang sudah ada).

---

## Yang Sengaja TIDAK Dibangun (Out of Scope)

- **Payment gateway** (Midtrans, Xendit, dll) — sesuai asumsi v1 di `arsitektur-overview.md`, cukup upload bukti transfer manual.
- **Verifikasi otomatis nominal via OCR/API mutasi bank** — pencocokan tetap visual manual oleh admin, kode unik hanya membantu mempersempit pencarian, bukan otomatisasi penuh.
- **Pengiriman email notifikasi** — hook disiapkan (lihat bagian Notifikasi), tapi tidak ada email yang benar-benar terkirim di iterasi ini.
- **Reuse tabel ini untuk Daftar Ulang (step 8)** — sesuai keputusan, scope khusus biaya pendaftaran. Step 8 didesain terpisah nanti.
- **Cicilan / pembayaran bertahap** — diasumsikan satu kali bayar lunas.

---

## Dampak ke Dokumen Lain (akan direvisi setelah rancangan ini disetujui)

- `arsitektur-overview.md` — diagram state machine & urutan pipeline perlu direvisi mengikuti urutan baru (verifikasi → pembayaran, bukan sebaliknya).
- `arsitektur-verifikasi-berkas.md` — mekanisme verifikasi per-dokumen (terima/tolak, kolom `jg_berkas.status`) **tidak berubah**. Yang berubah: halaman tempat upload-nya terjadi — dokumen di doc itu mengasumsikan `templates/frontend/berkas/upload.php` sebagai halaman terpisah (Step 3), padahal sesuai keputusan "Satu Halaman Terpadu" di atas, halaman itu **dilebur** jadi section di halaman detail. Perlu ditambah catatan revisi di doc tersebut setelah implementasi merge ini selesai.

---

## Status Pertanyaan Terbuka

Semua 3 pertanyaan sebelumnya **sudah terjawab** dan tercermin di revisi dokumen ini:

| # | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Rekening tujuan tunggal/banyak? | Banyak — tabel `jg_rekening_bank`, mahasiswa pilih saat upload |
| 2 | Nominal harus pas atau boleh beda? | Harus pas — dibantu kode unik 3 digit per pendaftaran |
| 3 | Perlu notifikasi? | Perlu secara konsep, hook disiapkan; pengiriman email ditunda (project-wide) |

---

## Status Implementasi

**Semua komponen di tabel "Komponen yang Perlu Dibuat" sudah dieksekusi** (2026-06-25):

- Skema DB: `jg_pembayaran` (revisi), `jg_rekening_bank` (baru), `jg_gelombang.biaya_pendaftaran`, `jg_pendaftaran.kode_unik_pembayaran` — `DB_VERSION` bump ke `4`.
- `StatusPendaftaran` enum: case `PembayaranDitolak` + urutan transisi direvisi sesuai pipeline baru.
- `PembayaranRepository`, `RekeningBankRepository`, `KodeUnikPembayaranGenerator` (service).
- Admin: `RekeningBankController` (CRUD rekening), field "Biaya Pendaftaran" di form Gelombang, generate kode unik otomatis di `PendaftarController::handleUpdateStatus()` saat transisi ke `berkas_diverifikasi`, hook `do_action('jg_pendaftaran_status_changed', ...)`, section "Bukti Pembayaran" di halaman detail admin (badge perbandingan nominal otomatis).
- Frontend: `PendaftaranController::handleUploadPembayaran()` + `handlePreviewPembayaran()`. Halaman "Detail Pendaftaran" (`templates/frontend/detail/index.php`) di-refactor total jadi satu halaman terpadu dengan 3 section (Formulir, Dokumen Persyaratan dengan upload inline, Bukti Pembayaran) sesuai status — section terkunci ditampilkan dengan `opacity:.5` + pesan penjelasan.
- Halaman lama `templates/frontend/berkas/upload.php` **dihapus** — route `upload-berkas` lama tetap dipetakan (untuk bookmark lama) tapi langsung me-render halaman detail.

**Belum dieksekusi (sesuai scope dokumen ini):** pengiriman email notifikasi (hook saja, no-op), payment gateway, OCR nominal — semua memang sengaja di luar scope (lihat "Yang Sengaja TIDAK Dibangun").
