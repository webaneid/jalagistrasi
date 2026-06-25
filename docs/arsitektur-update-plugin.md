# Arsitektur Update Plugin via GitHub — Plugin Jalagistrasi

**Tanggal:** 2026-06-26
**Status:** v1.0 — rancangan, menunggu persetujuan sebelum coding
**Author:** Webane Indonesia

---

## 1. Konteks & Tujuan

Plugin ini tidak didistribusikan lewat wordpress.org (license: proprietary), jadi tidak otomatis dapat badge "Update tersedia" di wp-admin. Tujuan: pasang mekanisme update mandiri yang sumbernya repo GitHub `webaneid/jalagistrasi`, dengan:

1. Tab baru **"Update"** di halaman Pengaturan (`jg-pengaturan`) — info versi terpasang, versi terbaru di GitHub, dan tombol **"Cek Update Sekarang"**.
2. Kalau ada versi lebih baru, WordPress menampilkan notifikasi update standar di halaman Plugins (sama seperti plugin resmi wordpress.org) — link "Update Now" akan otomatis download + extract dari GitHub Release.

Repo GitHub (sudah disiapkan user, belum di-push):
```
https://github.com/webaneid/jalagistrasi.git
```

---

## 2. Keputusan Inti: Pakai Library, Bukan Bangun dari Nol

**Library:** [`yahnis-elsts/plugin-update-checker`](https://github.com/YahnisElsts/plugin-update-checker) (PUC) — via Composer.

**Alasan:** Mekanisme update plugin custom (filter `site_transient_update_plugins` + `plugins_api` + handling rename folder hasil extract GitHub yang formatnya `{repo}-{tag}` bukan nama slug plugin) itu berisiko tinggi kalau ditulis manual — banyak edge case (perbandingan versi, struktur zip GitHub vs zip wordpress.org, asset vs source-code-zip, dst). PUC adalah library matang yang sudah dipakai ribuan plugin custom/premium di luar wordpress.org persis untuk kasus ini, native support GitHub Releases.

```bash
composer require yahnis-elsts/plugin-update-checker
```

PUC dipasang sekali di `src/Plugin.php` (atau service baru `src/Service/UpdateCheckerService.php`), dihook di `plugins_loaded`:

```php
$updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/webaneid/jalagistrasi/',
    JG_PLUGIN_DIR . 'jalagistrasi.php',
    'jalagistrasi'
);
$updateChecker->getVcsApi()->enableReleaseAssets(); // ambil zip dari GitHub Release, bukan auto source-zip
```

PUC membaca versi terpasang dari header `Version:` di `jalagistrasi.php` — **bukan** dari `Plugin::VERSION`. Ini menciptakan dua sumber kebenaran yang harus disinkronkan manual tiap rilis (lihat §6 Checklist Rilis).

---

## 3. Keputusan Final (2026-06-26)

### 3.1. Repo publik — DIPUTUSKAN

Repo `webaneid/jalagistrasi` **publik**. Konsekuensi teknis: PUC bekerja langsung tanpa GitHub token/PAT — tidak ada secret yang perlu disimpan di `wp_options`, tidak ada langkah auth tambahan. Kode di §2 (`PucFactory::buildUpdateChecker(...)`) sudah final apa adanya, tidak perlu `setAuthentication()`.

Catatan kecil yang perlu disadari (bukan blocker, sekadar konsekuensi dari "proprietary" + "publik" berbarengan): source code plugin ini jadi bisa dibaca siapa saja yang punya link repo. Kalau nanti berubah pikiran, tinggal ubah visibility repo ke Private di GitHub lalu tambah token PAT — bagian PUC yang berubah cuma satu baris (`setAuthentication()`), tidak perlu redesain ulang.

### 3.2. Repo butuh `vendor/` ikut di-zip rilis — bukan auto zip GitHub

GitHub punya 2 jenis aset di halaman Release:
1. **Source code (zip)** — auto-generated, isinya HANYA file yang di-commit di tag itu. Plugin ini punya dependency Composer (`phpoffice/phpspreadsheet`, dst) — kalau `vendor/` di-gitignore (rekomendasi §4), source-code-zip otomatis ini **tidak bisa langsung jadi plugin yang jalan** (fatal error `vendor/autoload.php` not found begitu di-update).
2. **Release asset custom** — file yang kita upload manual/lewat CI ke Release, isinya sudah "siap pakai" (vendor/ sudah di-`composer install --no-dev`, assets/ sudah di-build).

**Keputusan: pakai opsi 2.** PUC method `enableReleaseAssets()` (sudah ditulis di §2) memang didesain untuk ambil dari Release asset custom, bukan source-code-zip otomatis. Build asset ini sebaiknya otomatis lewat GitHub Actions (§5), bukan manual zip dari laptop developer (rawan lupa exclude file, rawan beda environment).

---

## 4. `.gitignore` — Belum Ada, Perlu Dibuat dari Awal

Repo masih kosong (belum `git init`). Rekomendasi isi `.gitignore`:

```gitignore
# Dependency — di-build ulang saat rilis (lihat docs/arsitektur-update-plugin.md §5),
# TIDAK di-commit supaya histori git tidak bengkak tiap composer update.
/vendor/
/node_modules/

# Build artifact lokal developer (beda dari assets/ — itu OUTPUT vite yang
# SENGAJA tetap di-commit karena dipakai langsung tanpa build step di server).
.DS_Store
*.log

# Editor/IDE & tooling lokal
.claude/
.vscode/
.idea/

# Test coverage (kalau nanti phpunit dijalankan dengan coverage report)
/coverage/
.phpunit.cache/
```

**Catatan penting:** `assets/` (hasil build Vite — `assets/css/app.css`, `assets/js/app.js`) **TETAP di-commit**, beda dari konvensi umum "jangan commit build artifact". Alasan: arsitektur plugin ini sudah berasumsi `assets/` adalah file matang yang langsung dipakai `wp_enqueue_style/script()` tanpa build step di server produksi (lihat sesi redesign dark-mode sebelumnya — semua perubahan CSS langsung ditulis ke `assets/css/app.css`). Konsisten dengan itu, `assets/` ikut commit.

`data/wilayah.csv` (7MB, data master wilayah) — ikut commit, bukan build artifact, perlu ada di setiap instalasi.

---

## 5. GitHub Actions — Build & Rilis Otomatis Saat Tag Dipush

**File baru:** `.github/workflows/release.yml`

**Trigger:** push tag berformat `v*` (mis. `v1.2.0`).

**Langkah:**
1. Checkout kode.
2. Setup PHP 8.1 + Composer → `composer install --no-dev --optimize-autoloader` (HANYA dependency produksi, exclude `phpunit/phpunit` dkk).
3. Setup Node → `npm ci && npm run build` (regenerate `assets/` dari `resources/`, jaga-jaga kalau ada perubahan belum di-build manual sebelum tag).
4. Zip seluruh folder KECUALI: `.git/`, `.github/`, `node_modules/`, `tests/`, `.claude/`, `.DS_Store`, `*.md` di root (boleh tetap include `docs/` — tidak masalah ikut terdistribusi, cuma nambah ukuran sedikit).
5. Upload hasil zip sebagai **Release asset** ke tag yang sama (pakai `gh release create` atau action `softprops/action-gh-release`).

**Kenapa CI, bukan build manual di laptop:** menghindari "works on my machine" (versi Node/Composer beda, lupa `--no-dev`, lupa rebuild assets/ setelah edit CSS terakhir) — kelas bug yang sama sekali tidak ingin terjadi di update production sebuah sistem PMB yang sedang berjalan.

---

## 6. Checklist Rilis (Manual, per Versi)

Karena ada DUA sumber versi yang harus selalu sinkron:

1. Update `Version:` di header `jalagistrasi.php`.
2. Update `Plugin::VERSION` di `src/Plugin.php`.
3. (Kalau ada perubahan schema DB) update `Plugin::DB_VERSION` + tambah blok migrasi di `runMigrationsIfNeeded()` — pola yang sudah dipakai sejak v5→v6 (migrasi tahun ajaran, import data wilayah).
4. Commit, `git tag vX.Y.Z`, `git push origin vX.Y.Z`.
5. GitHub Actions otomatis build + attach Release asset (§5).
6. Verifikasi: buka halaman Release di GitHub, pastikan asset zip ada dan ukurannya wajar (bukan 0 byte / gagal build).

---

## 7. UI: Tab di Halaman Pengaturan

`src/Admin/PengaturanController.php::renderPage()` saat ini me-render satu halaman flat (form setting + section "Data Wilayah" yang ditambahkan sesi sebelumnya). Diubah jadi tab:

```php
$tab = sanitize_key($_GET['tab'] ?? 'umum');
```

```html
<h2 class="nav-tab-wrapper">
    <a href="?page=jg-pengaturan&tab=umum"   class="nav-tab <?= $tab==='umum' ? 'nav-tab-active' : '' ?>">Umum</a>
    <a href="?page=jg-pengaturan&tab=update" class="nav-tab <?= $tab==='update' ? 'nav-tab-active' : '' ?>">Update</a>
</h2>
```

- **Tab "Umum"** — isi form setting yang sudah ada (logo, warna brand, kontak institusi) + section "Data Wilayah" (sync button) yang sudah dibuat sesi sebelumnya — pindah ke sini supaya tab "Update" khusus update plugin saja.
- **Tab "Update"** — konten baru:
  - Versi terpasang (`Plugin::VERSION`).
  - Versi terbaru yang terdeteksi (dari PUC, lewat `$updateChecker->getUpdateState()` / object update kalau ada).
  - Kapan terakhir dicek (timestamp, simpan di `wp_options` saat checkForUpdates() dipanggil).
  - Tombol **"Cek Update Sekarang"** → form POST ke `admin_post_jg_check_update` (pola sama dengan tombol "Sync Data Wilayah": nonce + capability `manage_options` + redirect dengan flash message).

**Handler baru** di `PengaturanController.php`:

```php
public function handleCheckUpdate(): void
{
    if (!current_user_can('manage_options')) wp_die(...);
    check_admin_referer('jg_check_update');

    $updateChecker = ...; // ambil instance yang sama dipakai Plugin.php
    $updateChecker->checkForUpdates(); // force-refresh, bypass cache interval PUC

    update_option('jalagistrasi_update_last_checked', current_time('mysql'));

    wp_safe_redirect(add_query_arg(['page'=>'jg-pengaturan','tab'=>'update','message'=>'checked'], admin_url('admin.php')));
    exit;
}
```

Register hook di `Plugin.php`: `add_action('admin_post_jg_check_update', [$pengaturanCtrl, 'handleCheckUpdate']);`

---

## 8. Ringkasan File yang Akan Disentuh (Fase Implementasi)

| File | Perubahan |
|---|---|
| `composer.json` | Tambah `yahnis-elsts/plugin-update-checker` |
| `.gitignore` (baru) | Lihat §4 |
| `.github/workflows/release.yml` (baru) | Build + attach Release asset otomatis saat tag `v*` |
| `src/Plugin.php` | Inisialisasi PUC di `plugins_loaded`, register hook `admin_post_jg_check_update` |
| `src/Admin/PengaturanController.php` | Refactor `renderPage()` jadi tab Umum/Update, tambah `handleCheckUpdate()` |
| `jalagistrasi.php` | Header `Version:` jadi acuan PUC (sinkron manual tiap rilis, §6) |

---

## 9. Yang TIDAK Dibahas/Dikerjakan di Fase Ini

- Auto-update tanpa klik (WP core punya fitur "Enable auto-updates" generik per-plugin sejak WP 5.5 — begitu update terdeteksi oleh filter PUC, toggle itu otomatis ikut muncul di halaman Plugins tanpa kerja tambahan; tidak perlu dibangun manual).
- Rollback otomatis kalau update bikin fatal error (WP core ada "fatal error protection" sejak 5.2 yang auto-rollback plugin aktif kalau fatal terdeteksi saat aktivasi — di luar scope plugin ini, sudah ditangani WP core, cukup diketahui ada).
- Notifikasi (WA/email) ke admin saat update tersedia — bisa nanti dibahas terpisah kalau dibutuhkan.
