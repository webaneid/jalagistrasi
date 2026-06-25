# Arsitektur Alamat Wilayah (Provinsi–Kabupaten–Kecamatan–Desa) — Plugin Jalagistrasi

**Tanggal:** 2026-06-26
**Status:** v1.0 — rancangan, menunggu persetujuan sebelum coding
**Author:** Webane Indonesia

---

## Latar Belakang & Masalah

Field alamat saat ini (lihat `src/Service/DefaultFormTemplate.php`, default template per gelombang) ada 7 field, semuanya bertipe `text` bebas:

| nama_field | label | wajib |
|---|---|---|
| `alamat_jalan` | Alamat Jalan | ✓ |
| `alamat_dusun` | Dusun | — |
| `alamat_rt` | RT | — |
| `alamat_rw` | RW | — |
| `alamat_kelurahan` | Kelurahan / Desa | ✓ |
| `alamat_kecamatan` | Kecamatan | ✓ |
| `alamat_kode_pos` | Kode Pos | — |

**Masalah:**
1. Kelurahan & kecamatan diisi bebas (rawan typo, variasi ejaan, beda kapitalisasi) — data tidak bisa direkap/difilter dengan akurat oleh panitia.
2. **Provinsi & kabupaten/kota sama sekali tidak ditangkap** — tidak ada field untuk itu di template sekarang.
3. Tidak ada sumber data wilayah resmi yang dipakai sebagai rujukan.

**Keputusan:** ganti `alamat_kelurahan` + `alamat_kecamatan` (dan secara implisit menambahkan kabupaten/kota + provinsi yang sebelumnya tidak ada) dengan **satu field autocomplete** yang menyimpan kode wilayah resmi Kemendagri. Field jalan/dusun/RT/RW/kode pos tetap teks bebas seperti sekarang — itu detail yang memang tidak ada di data wilayah resmi.

---

## Sumber Data

Dataset: [`cahyadsn/wilayah`](https://github.com/cahyadsn/wilayah) — open source, lisensi MIT, acuan **Kepmendagri No 300.2.2-2430 Tahun 2025**, plus data BIG & Ditjen Kependudukan Kemendagri. Mencakup 38 provinsi (termasuk pemekaran Papua), 416 kabupaten/kota, dan ±75.000 desa/kelurahan. Aktif di-maintain.

**Keputusan: vendor, bukan live API.** File data (CSV hasil olahan, lihat format di bawah) di-commit ke dalam plugin (`data/wilayah.csv` atau sejenisnya). Tidak ada panggilan jaringan ke pihak ketiga saat runtime — alasan: pendaftaran mahasiswa adalah jalur kritis, tidak boleh gagal karena API eksternal down/rate-limited.

**Update data:** kalau Kemendagri merilis pemekaran/penggabungan wilayah baru (jarang, biasanya tahunan), proses manual: developer re-download dataset terbaru dari `cahyadsn/wilayah`, jalankan ulang script transformasi (lihat bagian Import), commit file baru, lalu admin jalankan ulang "Sync Data Wilayah" dari wp-admin.

---

## Skema Database

### Tabel baru: `jg_wilayah`

Satu tabel flat — hierarki provinsi/kabupaten/kecamatan/desa disimpan **di dalam string `kode`** (format Kemendagri, dipisah titik): `11` (provinsi) → `11.01` (kabupaten) → `11.01.01` (kecamatan) → `11.01.01.2001` (desa). Level ditentukan dari jumlah segmen kode (split by `.`).

```sql
CREATE TABLE {prefix}jg_wilayah (
  kode          VARCHAR(13)  NOT NULL,   -- "11.01.01.2001"
  nama          VARCHAR(100) NOT NULL,   -- nama level ini saja, "Kebayoran Baru"
  level         TINYINT UNSIGNED NOT NULL, -- 1=provinsi, 2=kabupaten, 3=kecamatan, 4=desa
  nama_lengkap  VARCHAR(300) NOT NULL,   -- "Kebayoran Baru, Kebayoran Baru, Jakarta Selatan, DKI Jakarta" — hanya diisi untuk level=4, dipakai untuk search
  PRIMARY KEY (kode),
  KEY idx_level (level),
  FULLTEXT KEY ft_nama_lengkap (nama_lengkap)
);
```

Hanya baris `level=4` (desa/kelurahan) yang akan dicari lewat autocomplete — `nama_lengkap` di-precompute saat import dengan JOIN sekali ke 3 level di atasnya, supaya pencarian runtime tidak perlu JOIN sama sekali (cukup `MATCH(nama_lengkap) AGAINST(...)` atau `LIKE` kalau jumlah baris kecil & FULLTEXT MySQL versi lama bermasalah dengan kata pendek).

### Perubahan kecil: `jg_form_jawaban` — tidak ada perubahan schema

Field baru tetap disimpan generik lewat mekanisme yang sudah ada: `nilai_text` = kode desa (mis. `"11.01.01.2001"`), persis seperti field `text` lain. Tidak perlu kolom baru.

---

## Perubahan Kode

### 1. `src/Enum/TipeField.php` — tipe baru

```php
case WilayahAutocomplete = 'wilayah_autocomplete';
```

- `label()` → `'Alamat Wilayah (Provinsi–Desa)'`
- `requiresOptions()` → `false` (bukan options manual, sumbernya tabel `jg_wilayah`)
- `isMultiValue()` → `false` (disimpan sebagai satu kode di `nilai_text`)
- `validateFormat()` → cek `preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', $value)` DAN kode tersebut benar ada di tabel `jg_wilayah` (defense terhadap kode palsu yang dikirim manual lewat devtools).

### 2. Tabel & seed data — `src/Installer.php` + script import

- Tambah DDL `jg_wilayah` di `Installer::createTables()`.
- Tambah method `Installer::importWilayahData()` (atau service terpisah `WilayahImportService`) yang baca file CSV vendored, insert batch ke `jg_wilayah`. Dijalankan otomatis sekali saat aktivasi plugin (kalau tabel kosong), dan tersedia juga sebagai tombol manual "Sync Data Wilayah" di halaman Pengaturan (untuk kasus update data Kemendagri di kemudian hari tanpa perlu reaktivasi plugin).

### 3. Repository baru — `src/Repository/WilayahRepository.php`

```php
findByKode(string $kode): ?object        // untuk resolve kode → nama_lengkap (tampil di admin/detail)
search(string $query, int $limit = 10): array   // untuk endpoint autocomplete
```

### 4. Endpoint AJAX — `jg_search_wilayah`

Didaftarkan di controller frontend yang sudah ada (pola sama dengan `jg_preview_berkas`):

- `wp_ajax_jg_search_wilayah` + `wp_ajax_nopriv_jg_search_wilayah` (perlu nopriv karena dipanggil dari form pendaftaran sebelum tentu sudah login penuh di semua alur — cek lagi saat implementasi apakah perlu login).
- Request: `GET` dengan `q` (string, min 3 karakter) + nonce.
- Response: `[{ "kode": "11.01.01.2001", "label": "Kebayoran Baru, Kebayoran Baru, Jakarta Selatan, DKI Jakarta" }, ...]` maksimal 10 hasil.

### 5. Render frontend — `templates/frontend/form/index.php`

Tambah cabang baru di switch tipe field (sejajar dengan cabang `select`/`radio`/dst yang sudah ada):

```php
elseif ($tipe === 'wilayah_autocomplete') :
    // Kalau sudah ada nilai tersimpan (repopulate / draft), resolve dulu nama_lengkap-nya
    // lewat WilayahRepository::findByKode() supaya user lihat teks, bukan kode mentah.
?>
    <div class="jg-wilayah-autocomplete" x-data="jgWilayahAutocomplete('<?php echo esc_attr($namaField); ?>', '<?php echo esc_attr($prefilledVal); ?>', '<?php echo esc_attr($prefilledLabel); ?>')">
        <input type="hidden" name="<?php echo esc_attr($namaField); ?>" :value="kodeTerpilih">
        <input type="text" class="jg-input" x-model="queryText" @input.debounce.300ms="cari()" placeholder="Ketik nama desa/kelurahan...">
        <div class="jg-wilayah-suggestions" x-show="hasil.length > 0" x-cloak>
            <template x-for="item in hasil" :key="item.kode">
                <button type="button" @click="pilih(item)" x-text="item.label"></button>
            </template>
        </div>
    </div>
<?php endif; ?>
```

(Alpine.js component `jgWilayahAutocomplete` didaftarkan di `assets/js/app.js`, mengikuti pola Alpine yang sudah dipakai untuk tab login & toggle password — fetch ke `admin-ajax.php?action=jg_search_wilayah`, styling pakai class `jg-input`/`jg-card` dark theme yang sudah ada di `dark-theme.php`.)

### 6. Render admin — `templates/admin/pendaftar/detail.php`

Saat field bertipe `wilayah_autocomplete`, resolve kode → `nama_lengkap` lewat `WilayahRepository::findByKode()` sebelum ditampilkan (jangan tampilkan kode mentah ke admin).

### 7. Default template — `src/Service/DefaultFormTemplate.php`

- Hapus `alamat_kelurahan` dan `alamat_kecamatan` dari array default.
- Tambah satu field baru:

```php
[
    'section_name' => 'Biodata Pribadi',
    'nama_field'   => 'alamat_wilayah',
    'label'        => 'Provinsi / Kabupaten / Kecamatan / Desa',
    'tipe'         => 'wilayah_autocomplete',
    'is_required'  => 1,
    'is_core'      => 0,
    'urutan'       => 9,
    'konfigurasi'  => null,
],
```

Urutan field jalan/dusun/RT/RW tetap sebelum field ini; kode pos tetap sesudahnya.

---

## Migrasi Data Lama — Keputusan Penting

**Data jawaban lama TIDAK dimigrasikan otomatis.** Alasan: `alamat_kelurahan`/`alamat_kecamatan` lama berupa teks bebas (variasi ejaan, singkatan) — auto-matching ke kode wilayah resmi berisiko salah pasang tanpa diketahui siapapun. Pendekatan:

1. Field `alamat_kelurahan` & `alamat_kecamatan` yang **sudah ada di gelombang lama** (sudah ada jawabannya) **dibiarkan apa adanya** — tidak dihapus dari `jg_form_field`, tidak disentuh jawabannya. Riwayat pendaftar lama tetap utuh & tampil normal di halaman detail.
2. Field baru (`alamat_wilayah`) **hanya ditambahkan ke gelombang BARU** yang dibuat setelah perubahan ini di-deploy (lewat `DefaultFormTemplate`), atau ke gelombang yang masih `draft` kalau admin mau menambahkannya manual lewat Form Builder.
3. Tidak ada migration script yang mengubah gelombang lama secara otomatis — ini keputusan sadar untuk menghindari data corruption, bukan keterbatasan teknis.

---

## Keputusan Final (2026-06-26)

1. **Format file data** — CSV, sudah di-generate dari `db/wilayah.sql` resmi `cahyadsn/wilayah` (commit terbaru, acuan Kepmendagri No 300.2.2-2138 Tahun 2025). File: `data/wilayah.csv`, kolom `kode,nama,level,nama_lengkap` — `level` & `nama_lengkap` sudah dihitung di muka (offline, sekali) supaya `WilayahImportService` saat runtime cuma bulk-insert tanpa logic tambahan. Total 91.162 baris (38 provinsi, 514 kabupaten/kota, 7.265 kecamatan, 83.345 desa/kelurahan).
2. **Endpoint login** — field ini hanya dipakai di form pendaftaran (`templates/frontend/form/index.php`), yang hanya bisa diakses pendaftar yang sudah login. Endpoint AJAX **tidak perlu** `wp_ajax_nopriv_*`, cukup `wp_ajax_jg_search_wilayah` (logged-in only), konsisten dengan endpoint lain di form pendaftaran (`jg_preview_berkas`, dst).
3. **Belum production** — semua database masih lokal/development. Tidak perlu menjaga kompatibilitas data lama sama sekali. Field `alamat_kelurahan` & `alamat_kecamatan` **dihapus total** dari `DefaultFormTemplate` dan diganti `alamat_wilayah` untuk SEMUA gelombang (lama maupun baru) — termasuk boleh reset/hapus data `jg_form_field` & `jg_form_jawaban` yang terkait dua field lama itu kalau perlu, karena tidak ada data produksi yang harus dijaga.

## Migrasi Data Lama — DIBATALKAN (lihat Keputusan Final #3)

Bagian migrasi hati-hati di bawah ini sudah tidak relevan untuk fase development sekarang — didokumentasikan tetap di sini sebagai catatan keputusan, untuk dipakai ulang nanti KALAU plugin ini sudah production dan ada data pendaftar asli yang harus dijaga saat ada perubahan skema field serupa di masa depan.

---

## Ringkasan File yang Akan Disentuh

| File | Perubahan |
|---|---|
| `src/Installer.php` | DDL tabel `jg_wilayah` + trigger import awal |
| `data/wilayah.csv` (baru) | Data vendored dari `cahyadsn/wilayah` |
| `src/Service/WilayahImportService.php` (baru) | Parse CSV → insert batch + build `nama_lengkap` |
| `src/Repository/WilayahRepository.php` (baru) | `findByKode()`, `search()` |
| `src/Enum/TipeField.php` | Tambah case `WilayahAutocomplete` |
| `src/Frontend/*Controller.php` | Handler AJAX `jg_search_wilayah` |
| `templates/frontend/form/index.php` | Render cabang baru tipe `wilayah_autocomplete` |
| `templates/admin/pendaftar/detail.php` | Resolve kode → nama_lengkap saat tampil |
| `assets/js/app.js` | Alpine component `jgWilayahAutocomplete` |
| `src/Service/DefaultFormTemplate.php` | Ganti 2 field lama jadi 1 field baru |
| Halaman Pengaturan admin | Tombol "Sync Data Wilayah" manual |
