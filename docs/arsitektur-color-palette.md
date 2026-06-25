# Arsitektur Color Palette (Branding) — Plugin Jalagistrasi

**Tanggal:** 2026-06-25
**Status:** v1.0 — diimplementasikan
**Author:** Webane Indonesia

---

## Konteks

Warna brand (`bg-brand-600`, `text-brand-700`, `hover:bg-brand-700`, dst — dipakai luas di semua template frontend: dashboard, form, detail pendaftaran, info publik) saat ini **hardcoded** di compiled CSS (`assets/css/app.css`) lewat CSS custom property `--jg-color-50` s/d `--jg-color-900` (skala biru Tailwind standar). Client butuh bisa ganti warna ini sesuai identitas institusi mereka, tanpa rebuild plugin.

---

## Keputusan

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Jumlah warna yang dikustomisasi | **Satu warna brand utama** (anchor shade 600) | Sesuai arsitektur CSS yang sudah ada — hanya satu skala `--jg-color-*` yang dipakai untuk warna aksi/brand. Warna semantik (sukses/gagal/peringatan) sengaja tidak ikut, karena itu bukan identitas brand, harus tetap universal. |
| Cara generate skala 50–900 | **Interpolasi linear RGB** dari warna yang dipilih (jadi shade 600) menuju putih (shade 50–500) dan menuju hitam (shade 700–900) | Tidak perlu library warna eksternal, cukup matematika RGB sederhana, hasil "cukup baik" tanpa kompleksitas HSL. |
| Cara override tampilan | `wp_add_inline_style()` — suntik `:root{--jg-color-*:...}` setelah `app.css` di-enqueue | Tidak perlu sentuh file compiled, tidak perlu rebuild Tailwind. Inline style otomatis menang di cascade karena urutan sumber (specificity sama, override karena lebih belakang). |
| Default | `#2563eb` (sama dengan warna biru yang sudah dipakai sekarang) | Tidak ada perubahan visual sampai admin secara sadar mengganti. |
| Scope | Hanya halaman **frontend** (yang load `assets/css/app.css`) | Halaman admin pakai native WP CSS, tidak load Tailwind sama sekali — tidak terdampak, dan memang tidak perlu (admin panel bukan permukaan branding client). |

---

## Implementasi

| Komponen | File | Catatan |
|---|---|---|
| Generator skala warna | `src/Service/ColorPaletteGenerator.php` | `generateScale(string $hex): array<int,string>` — kembalikan `[50=>'#...', 100=>'#...', ..., 900=>'#...']` |
| Setting baru | `src/Admin/PengaturanController.php` | `jalagistrasi_warna_brand` (hex, default `#2563eb`) — pakai WP Color Picker native (`wp-color-picker` script/style bawaan WP, bukan custom) |
| Validasi | `PengaturanController::handleSave()` | Regex `^#[0-9a-f]{6}$` (case-insensitive), tolak simpan kalau format salah |
| Override CSS | `src/Plugin.php::enqueueFrontendAssets()` | `wp_add_inline_style('jalagistrasi-app', $css)` setelah `app.css` di-enqueue — isinya `:root{--jg-color-50:...;...;--jg-color-900:...;}` |

**Tidak ada tabel/migrasi DB baru** — murni `wp_options` + inline CSS runtime.

---

## Yang Sengaja TIDAK Dibangun

- **Multi-warna brand** (sekunder, aksen terpisah) — skema CSS saat ini cuma punya satu set variable, belum perlu lebih dari itu.
- **Color picker visual untuk tiap shade (50-900) satu-satu** — terlalu rumit untuk client non-teknis; auto-generate dari satu warna anchor sudah cukup baik untuk kebutuhan branding sederhana.
- **Live preview di halaman Pengaturan** — admin simpan dulu, lihat hasilnya di halaman frontend. Bisa ditambah nanti kalau dirasa perlu.
