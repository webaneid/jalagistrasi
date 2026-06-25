# Arsitektur Dashboard Admin (Statistik Pendaftaran) — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — diimplementasikan (termasuk filter Tahun Ajaran, lihat `arsitektur-tahun-ajaran.md`)
**Author:** Webane Indonesia

---

## Konteks

Halaman "Dashboard PMB" (`AdminMenu::renderDashboard()`) saat ini cuma 2 kartu link statis ("Kelola Gelombang", "Kelola Program Studi") — bukan dashboard statistik. Admin tidak punya gambaran cepat soal berapa pendaftar, di tahap mana saja mereka, dan mana yang butuh tindakan.

---

## Keputusan

| Keputusan | Pilihan |
|---|---|
| Bentuk tampilan | Kartu angka + tabel — **bukan** grafik/chart, supaya tidak nambah dependency JS dan lebih cepat dibangun/dirawat |
| Scope filter | Bisa filter per gelombang (dropdown, sama pola dengan halaman Data Pendaftar yang sudah ada) atau lihat semua gelombang sekaligus |

---

## Konten Dashboard

### 1. Kartu ringkasan (atas)
- **Total Pendaftar** (semua status kecuali `draft` — draft dianggap belum benar-benar "mendaftar")
- **Menunggu Verifikasi Dokumen** (status = `berkas_diupload`) — actionable, link ke list terfilter
- **Menunggu Verifikasi Pembayaran** (status = `pembayaran_diupload`) — actionable, link ke list terfilter
- **Lulus Seleksi** (status = `diumumkan_lulus` atau lebih lanjut: `daftar_ulang`, `selesai`)

### 2. Tabel breakdown status
Semua `StatusPendaftaran` (kecuali `draft`) + jumlah pendaftar di tiap status, dengan link ke halaman Data Pendaftar yang sudah difilter status tersebut (`?page=jg-pendaftar&status=...`) — reuse filter yang sudah ada, tidak bikin UI baru.

### 3. Tabel Prodi Terpopuler
Program studi yang paling banyak dipilih sebagai **pilihan ke-1** (`jg_pendaftaran_prodi.urutan = 1`) — nama prodi + jumlah pemilih, diurutkan terbanyak. Membantu admin lihat minat pendaftar tanpa buka satu-satu.

### 4. Filter Gelombang
Dropdown di atas (`?gelombang_id=X`, default: semua gelombang) — semua angka di atas otomatis menyesuaikan filter ini, sama pola dengan `PendaftarController::renderPage()` yang sudah ada.

---

## Query yang Dibutuhkan (Repository)

Ditambah ke `PendaftaranRepository`:

```php
/** @return array<string,int> status_value => jumlah */
public function countByStatusGrouped(int $gelombangId = 0): array

public function countTotal(int $gelombangId = 0): int  // exclude draft
```

Ditambah ke `PendaftaranProdiRepository` (atau `ProgramStudiRepository`):

```php
/** @return list<object{prodi_nama:string, jumlah:int}> */
public function findProdiTerpopuler(int $gelombangId = 0, int $limit = 10): array
```

Semua query pakai `GROUP BY` + `COUNT(*)`, single query per kartu/tabel — tidak ada N+1.

---

## Struktur Kode

Saat ini render dashboard ada langsung di `AdminMenu::renderDashboard()` (inline HTML di dalam class menu). Karena kontennya jadi lebih kompleks (butuh query, filter, banyak markup), dipisah jadi controller sendiri **konsisten dengan pola section lain** (`GelombangController`, `PendaftarController`, dst — masing-masing punya controller sendiri):

| Komponen | File | Catatan |
|---|---|---|
| Controller baru | `src/Admin/DashboardController.php` | `renderPage()` — load stats, render template |
| Template baru | `templates/admin/dashboard/index.php` | Kartu + tabel, native WP admin style (postbox, dst — sama seperti halaman lain) |
| `AdminMenu.php` | Ubah `'jg-dashboard' => [$adminMenu, 'renderDashboard']` jadi arahkan ke `DashboardController::renderPage()` | `renderDashboard()` lama dihapus |
| `PendaftaranRepository.php` | + `countByStatusGrouped()`, `countTotal()` | |
| `PendaftaranProdiRepository.php` | + `findProdiTerpopuler()` | |

---

## Yang Sengaja TIDAK Dibangun

- **Grafik/chart visual** (tren harian, dst) — sesuai keputusan, bisa jadi iterasi berikutnya kalau dibutuhkan.
- **Statistik lintas-waktu** (mis. "naik X% dari gelombang sebelumnya") — perlu data historis terstruktur, di luar scope v1.
- **Export statistik ke Excel/PDF** — bagian dari fitur ekspor data yang belum dibangun (`arsitektur-overview.md` §6).
