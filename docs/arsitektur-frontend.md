# Arsitektur Frontend — Plugin Jalagistrasi

**Tanggal:** 2026-06-24
**Status:** v1.0 — disetujui
**Author:** Webane Indonesia

---

## Konteks

Halaman dashboard pendaftar adalah **gerbang utama** calon mahasiswa. Pertama kali mereka melihat kampus secara digital. Loading lambat = kesan buruk sebelum formulir bahkan dibuka. Ini bukan halaman admin internal — ini halaman publik yang diakses dari berbagai perangkat dan koneksi internet yang tidak bisa kita kendalikan.

**Prinsip desain frontend: ringan dulu, fitur kemudian.**

---

## Opsi yang Dipertimbangkan

### CSS Framework
| Opsi | Berat build | Trade-off |
|---|---|---|
| Bootstrap 5 | ~140KB CSS | Berat, banyak yang tidak terpakai |
| Tailwind CSS (purged) | **~8–15KB CSS** | Hanya class yang dipakai yang di-bundle |
| Pure CSS custom | ~5KB | Lambat dikembangkan, sulit konsisten |

### JS Interactivity
| Opsi | Berat | Trade-off |
|---|---|---|
| jQuery | ~87KB | Sudah ada di WP tapi kita tidak mau tergantung padanya |
| Vue / React | ~100KB+ | Overkill untuk form HTML server-rendered |
| **Alpine.js** | **~15KB gzip** | Cukup untuk kondisional form, tidak perlu SPA |
| Vanilla JS | ~0KB overhead | Bagus untuk yang simpel, tapi repetitif untuk form dinamis |

### Icon
| Opsi | Cara pakai | Berat |
|---|---|---|
| Font Awesome | CSS font | ~400KB font file |
| Bootstrap Icons | CSS font | ~160KB |
| Dashicons | CSS class | 0KB extra (sudah di WP) tapi hanya admin, desain lama |
| **Lucide** (inline SVG) | PHP helper | **0KB HTTP** — SVG di-output langsung ke HTML |

---

## Keputusan Final

| Area | Keputusan |
|---|---|
| CSS Framework | Tailwind CSS via Vite build (hanya class yang dipakai) |
| Custom styles | SCSS untuk variabel brand, overrides, komponen yang tidak fit di Tailwind |
| JS Framework | Alpine.js untuk interaktivitas form (kondisional field, validasi UI) |
| Vanilla JS | Untuk hal-hal simpel yang tidak perlu Alpine (submit handler, flash message) |
| Icons | Lucide — inline SVG via PHP helper. Zero HTTP request untuk icon |
| Build tool | Vite (bukan Webpack/Laravel Mix) — build lebih cepat, config minimal |
| Font | **System font stack** — tidak ada web font di-load. Ringan, tidak ada FOIT/FOUT |
| jQuery | Tidak dipakai — tidak ada `wp_enqueue_script('jquery')` sebagai dependency kita |

---

## Target Performa

| Metrik | Target | Catatan |
|---|---|---|
| Total CSS + JS (frontend) | **< 50KB** (gzip) | Tailwind purged + Alpine.js minimal |
| First Contentful Paint | **< 1.5 detik** | Di koneksi 4G mid-range |
| Tidak ada render-blocking resource | ✓ | CSS di `<head>`, JS di footer atau `defer` |
| Core Web Vitals | Pass | LCP, FID, CLS harus hijau |
| HTTP requests untuk asset | **≤ 3** | 1 CSS, 1 JS, 0 icon request |

---

## Strategi Tailwind untuk WordPress

### Masalah: konflik dengan CSS WordPress admin

WordPress admin (`wp-admin`) punya ratusan class CSS sendiri. Tailwind's `preflight` (CSS reset) akan menimpa styling WP dan merusak tampilan admin jika tidak di-scope.

### Solusi: `important` strategy dengan selector scope

```js
// tailwind.config.js
export default {
  important: '#jalagistrasi-wrap',  // semua utility Tailwind hanya berlaku di dalam wrapper ini
  content: [
    './templates/**/*.php',
    './resources/js/**/*.js',
  ],
  corePlugins: {
    preflight: false,  // matikan CSS reset global — kita tidak mau reset CSS WordPress
  },
  theme: {
    extend: {
      colors: {
        brand: {
          50:  'var(--jg-color-50)',
          500: 'var(--jg-color-500)',
          600: 'var(--jg-color-600)',
          700: 'var(--jg-color-700)',
        },
      },
      fontFamily: {
        sans: [
          'system-ui', '-apple-system', 'BlinkMacSystemFont',
          '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif',
        ],
      },
    },
  },
}
```

Warna brand disimpan sebagai **CSS custom properties** (`--jg-color-*`) — admin kampus bisa override warna via pengaturan plugin tanpa perlu rebuild CSS.

### Dua bundle terpisah

| Bundle | Scope | Isi |
|---|---|---|
| `app.css` + `app.js` | Frontend dashboard pendaftar | Tailwind + Alpine.js + custom SCSS |
| `admin.css` + `admin.js` | wp-admin (halaman plugin panitia) | Tailwind (scoped) + minimal JS |

Masing-masing hanya dienqueue di halaman yang relevan — tidak ada CSS/JS plugin yang load di semua halaman WordPress.

---

## Lucide Icons — PHP Helper

Tidak ada CDN, tidak ada `<link>` ke font icon. Icon di-output langsung sebagai inline SVG oleh fungsi PHP.

```php
// src/Helper/IconHelper.php
namespace Webane\Jalagistrasi\Helper;

final class IconHelper
{
    private static string $iconDir = __DIR__ . '/../../resources/icons/';

    public static function render(string $name, string $class = '', string $size = '20'): string
    {
        $file = self::$iconDir . $name . '.svg';
        if (!file_exists($file)) {
            return '';
        }
        $svg = file_get_contents($file);
        // inject class dan size
        $svg = preg_replace(
            '/<svg/',
            sprintf('<svg class="%s" width="%s" height="%s"', esc_attr($class), esc_attr($size), esc_attr($size)),
            $svg,
            1
        );
        return $svg;
    }
}
```

Pemakaian di template:
```php
<?= IconHelper::render('user', 'text-brand-600 inline-block') ?>
```

Icon SVG file dari Lucide disimpan di `resources/icons/` — hanya icon yang benar-benar dipakai yang di-copy ke sini (tidak perlu 1500+ icon semuanya).

---

## Struktur File Build

```
jalagistrasi/
│
├── package.json               # dependencies: tailwindcss, vite, sass, lucide-static
├── vite.config.js             # entry points, output dir
├── tailwind.config.js         # config di atas
├── postcss.config.js          # autoprefixer
│
├── resources/                 # SOURCE — tidak di-commit ke produksi build
│   ├── css/
│   │   ├── app.scss           # frontend: @tailwind + custom SCSS
│   │   └── admin.scss         # wp-admin: @tailwind + WP-specific overrides
│   ├── js/
│   │   ├── app.js             # frontend: Alpine.js + form logic
│   │   └── admin.js           # wp-admin: Alpine.js + admin UI
│   └── icons/                 # hanya icon Lucide yang dipakai (manual copy)
│       ├── user.svg
│       ├── file-text.svg
│       └── ...
│
└── assets/                    # OUTPUT BUILD — ini yang di-ship dan dienqueue WP
    ├── css/
    │   ├── app.css            # Tailwind purged + compiled SCSS
    │   └── admin.css
    └── js/
        ├── app.js             # Alpine bundled + minified
        └── admin.js
```

`resources/` tidak perlu di-ship ke server produksi (bisa di-.gitignore dari zip distribusi). `assets/` yang di-ship.

---

## Alpine.js untuk Form Dinamis

Alpine.js dipakai untuk interaktivitas yang tidak perlu full-page reload:

```html
<!-- Contoh: conditional field -->
<div x-data="{ jenis_kelamin: '' }">
  <select x-model="jenis_kelamin" name="jenis_kelamin">
    <option value="laki-laki">Laki-laki</option>
    <option value="perempuan">Perempuan</option>
  </select>

  <!-- Field ini hanya muncul jika pilih perempuan -->
  <div x-show="jenis_kelamin === 'perempuan'" x-transition>
    <input type="text" name="nama_gadis_ibu" placeholder="Nama Gadis Ibu">
  </div>
</div>
```

Data kondisi field (aturan `show/hide`) di-generate dari `jg_form_field.konfigurasi` (JSON) oleh PHP saat render template — Alpine hanya mengeksekusi logika yang sudah PHP tentukan. Tidak ada JS yang hardcode logika bisnis.

---

## `vite.config.js`

```js
import { defineConfig } from 'vite'
import path from 'path'

export default defineConfig({
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        app:   path.resolve(__dirname, 'resources/css/app.scss'),
        admin: path.resolve(__dirname, 'resources/css/admin.scss'),
        'app-js':   path.resolve(__dirname, 'resources/js/app.js'),
        'admin-js': path.resolve(__dirname, 'resources/js/admin.js'),
      },
      output: {
        assetFileNames: 'css/[name].css',
        entryFileNames: 'js/[name].js',
      },
    },
    cssMinify: true,
    minify: 'esbuild',
  },
})
```

Build command:
```bash
npm run build        # produksi
npm run dev          # watch mode saat development
```

---

## `package.json`

```json
{
  "name": "jalagistrasi",
  "private": true,
  "scripts": {
    "dev":   "vite build --watch",
    "build": "vite build"
  },
  "devDependencies": {
    "vite": "^5.0.0",
    "sass": "^1.70.0",
    "tailwindcss": "^3.4.0",
    "autoprefixer": "^10.4.0",
    "postcss": "^8.4.0"
  },
  "dependencies": {
    "alpinejs": "^3.13.0"
  }
}
```

Alpine.js masuk sebagai `dependency` (bukan `devDependency`) karena di-bundle ke `app.js`.

---

## Konsekuensi & Trade-off

| Trade-off | Dampak | Keputusan |
|---|---|---|
| No web font | Tampilan bergantung pada font sistem user | Diterima — performa lebih penting; font sistem sudah bagus di semua OS modern |
| Alpine bukan Vue/React | State management terbatas untuk UI yang sangat kompleks | Diterima — form pendaftaran tidak perlu SPA; server-rendered HTML + Alpine sudah lebih dari cukup |
| Tailwind purge — class dinamis bisa ter-strip | Jika class di-generate via PHP string concatenation, purger tidak akan mendeteksinya | **Wajib**: selalu tulis class Tailwind secara lengkap di template, jangan concatenate string class |
| Build step diperlukan | Developer harus `npm install && npm run build` setelah clone | Diterima — ini standar modern; `assets/` di-commit ke repo sehingga server produksi tidak perlu Node.js |

---

## Catatan untuk Developer

1. **Selalu tulis class Tailwind lengkap.** Jangan: `'text-' . $color . '-500'`. Harus: `'text-brand-500'` atau list eksplisit.
2. **Icon baru?** Copy file SVG dari Lucide ke `resources/icons/`, gunakan via `IconHelper::render()`. Jangan pakai CDN icon library di template.
3. **Warna brand** diubah via CSS custom property `--jg-color-*` di pengaturan plugin — tidak perlu rebuild CSS.
4. **Tambah JS baru?** Tetap di `resources/js/`. Jangan `<script>` inline di template kecuali untuk data Alpine yang di-generate server (JSON payload).

---

## Dokumen Terkait

- [arsitektur-overview.md](arsitektur-overview.md)
- [arsitektur-struktur-plugin.md](arsitektur-struktur-plugin.md) *(belum dibuat — struktur folder lengkap)*
