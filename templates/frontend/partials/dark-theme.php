<?php
/**
 * Bahasa desain bersama "dark glass" untuk semua halaman pendaftar (login,
 * dashboard, form, detail, pilih gelombang) — supaya konsisten & tidak
 * duplikasi ratusan baris CSS di tiap template.
 *
 * Dipanggil di awal tiap template: $theme = jg_theme_colors();
 * Lalu sekali render CSS dasar: jg_render_base_styles();
 * Tiap halaman tetap boleh punya <style> tambahan sendiri untuk elemen unik
 * (stepper, tab, document grid, dst).
 */
defined('ABSPATH') || exit;

if (!function_exists('jg_theme_colors')) {
    /**
     * @return array{brand:string,brandRgb:string,bgDasar:string,bgAtas:string}
     */
    function jg_theme_colors(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $colorGen   = new \Webane\Jalagistrasi\Service\ColorPaletteGenerator();
        $warnaBrand = (string) get_option('jalagistrasi_warna_brand', '#2563eb');

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $warnaBrand)) {
            $warnaBrand = '#2563eb';
        }

        $cache = [
            'brand'    => $warnaBrand,
            'brandRgb' => $colorGen->toRgbString($warnaBrand),
            'bgDasar'  => $colorGen->mixTowardBlack($warnaBrand, 0.94),
            'bgAtas'   => $colorGen->mixTowardBlack($warnaBrand, 0.88),
        ];

        return $cache;
    }
}

if (!function_exists('jg_render_base_styles')) {
    function jg_render_base_styles(): void
    {
        $c = jg_theme_colors();
        ?>
        <style>
        #jalagistrasi-wrap .jg-page {
            min-height: 100vh;
            background-color: <?php echo esc_html($c['bgDasar']); ?>;
            background-image: linear-gradient(180deg, <?php echo esc_html($c['bgAtas']); ?> 0%, <?php echo esc_html($c['bgDasar']); ?> 420px);
            padding-bottom: 60px;
        }

        #jalagistrasi-wrap .jg-topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(255, 255, 255, 0.04);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        #jalagistrasi-wrap .jg-topbar-inner {
            max-width: 720px;
            margin: 0 auto;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        #jalagistrasi-wrap .jg-topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        #jalagistrasi-wrap .jg-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.7);
            flex-shrink: 0;
            transition: background-color .15s, color .15s;
        }

        #jalagistrasi-wrap .jg-back:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        #jalagistrasi-wrap .jg-brand {
            font-size: 14px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.85);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #jalagistrasi-wrap .jg-user {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        #jalagistrasi-wrap .jg-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 9999px;
            background: <?php echo esc_html($c['brand']); ?>;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        #jalagistrasi-wrap .jg-user-name {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            display: none;
        }

        @media (min-width: 480px) {
            #jalagistrasi-wrap .jg-user-name { display: inline; }
        }

        #jalagistrasi-wrap .jg-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.6);
            transition: background-color .15s, color .15s;
            flex-shrink: 0;
        }

        #jalagistrasi-wrap .jg-logout:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }

        #jalagistrasi-wrap .jg-container {
            max-width: 720px;
            margin: 0 auto;
            padding: 28px 20px 0;
        }

        #jalagistrasi-wrap .jg-container--narrow {
            max-width: 560px;
        }

        /* Notifikasi / alert */
        #jalagistrasi-wrap .jg-notif {
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        #jalagistrasi-wrap .jg-notif--success { background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3); color: #86efac; }
        #jalagistrasi-wrap .jg-notif--danger  { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #fecaca; }
        #jalagistrasi-wrap .jg-notif--warning { background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); color: #fde68a; }
        #jalagistrasi-wrap .jg-notif p { margin: 0; }
        #jalagistrasi-wrap .jg-notif p + p { margin-top: 4px; }

        /* Card */
        #jalagistrasi-wrap .jg-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 22px;
            padding: 22px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 16px 44px rgba(0, 0, 0, 0.3);
            margin-bottom: 18px;
        }

        #jalagistrasi-wrap .jg-card--flat {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: none;
            backdrop-filter: none;
            padding: 16px 18px;
        }

        #jalagistrasi-wrap .jg-section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.45);
            margin: 0 0 14px;
        }

        #jalagistrasi-wrap .jg-card-title {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
        }

        #jalagistrasi-wrap .jg-card-sub {
            margin: 4px 0 0;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Buttons */
        #jalagistrasi-wrap .jg-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 22px;
            border-radius: 12px;
            background: <?php echo esc_html($c['brand']); ?>;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            transition: filter .15s, opacity .15s;
        }
        #jalagistrasi-wrap .jg-btn:hover { filter: brightness(1.1); }
        #jalagistrasi-wrap .jg-btn:disabled,
        #jalagistrasi-wrap .jg-btn.is-disabled {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.35);
            cursor: not-allowed;
        }

        #jalagistrasi-wrap .jg-btn--block { width: 100%; }
        #jalagistrasi-wrap .jg-btn--small { padding: 8px 16px; font-size: 13px; border-radius: 10px; }

        #jalagistrasi-wrap .jg-btn--outline {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: rgba(255, 255, 255, 0.85);
        }
        #jalagistrasi-wrap .jg-btn--outline:hover { background: rgba(255, 255, 255, 0.1); filter: none; }

        #jalagistrasi-wrap .jg-btn--danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
        }
        #jalagistrasi-wrap .jg-btn--danger:hover { background: rgba(239, 68, 68, 0.25); filter: none; }

        /* Badges */
        #jalagistrasi-wrap .jg-badge {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        #jalagistrasi-wrap .jg-badge--neutral  { background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.6); }
        #jalagistrasi-wrap .jg-badge--action   { background: rgba(<?php echo esc_html($c['brandRgb']); ?>, 0.2); color: #93c5fd; }
        #jalagistrasi-wrap .jg-badge--waiting  { background: rgba(234, 179, 8, 0.15); color: #fde047; }
        #jalagistrasi-wrap .jg-badge--rejected,
        #jalagistrasi-wrap .jg-badge--failed   { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
        #jalagistrasi-wrap .jg-badge--success  { background: rgba(34, 197, 94, 0.15); color: #86efac; }

        /* Form fields */
        #jalagistrasi-wrap .jg-field { margin-bottom: 16px; }
        #jalagistrasi-wrap .jg-field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 6px;
        }
        #jalagistrasi-wrap .jg-field label span.req { color: #f87171; }
        #jalagistrasi-wrap .jg-field-hint {
            margin: 6px 0 0;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
        }

        /*
         * !important SENGAJA dipakai di sini — CSS lama Tailwind versi terang (forms
         * reset, mis. `#jalagistrasi-wrap input[type=email]{background:#fff;color:#111827}`)
         * punya selector berbasis [type=...] yang spesifisitasnya menang melawan .jg-input
         * polos, sehingga semua <input> teks/angka/tanggal jadi putih+teks hitam walau
         * sudah diberi class .jg-input. !important memenangkan ini tanpa perlu menaikkan
         * spesifisitas selector (yang akan merusak elemen non-<input> berclass .jg-input,
         * mis. <p class="jg-input jg-input--readonly"> untuk field auto-fill read-only).
         * select/textarea aman karena rule lama untuk itu pakai tag selector polos
         * (lebih lemah) — !important tetap ditambahkan untuk jaga-jaga konsisten.
         */
        #jalagistrasi-wrap .jg-input,
        #jalagistrasi-wrap .jg-page select,
        #jalagistrasi-wrap .jg-page textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.07) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 14px;
            color: #fff !important;
            transition: border-color .15s, background-color .15s;
            font-family: inherit;
            color-scheme: dark;
        }
        #jalagistrasi-wrap .jg-input::placeholder,
        #jalagistrasi-wrap .jg-page select::placeholder,
        #jalagistrasi-wrap .jg-page textarea::placeholder { color: rgba(255, 255, 255, 0.35) !important; }
        #jalagistrasi-wrap .jg-input:focus,
        #jalagistrasi-wrap .jg-page select:focus,
        #jalagistrasi-wrap .jg-page textarea:focus {
            outline: none;
            border-color: rgba(<?php echo esc_html($c['brandRgb']); ?>, 0.7) !important;
            background: rgba(255, 255, 255, 0.1) !important;
        }
        #jalagistrasi-wrap .jg-page select option { color: #111; }

        /* Browser autofill (nama, alamat, dst) memaksa bg putih + teks hitam sendiri —
           timpa lewat trik box-shadow inset (autofill tidak baca background biasa). */
        #jalagistrasi-wrap input.jg-input:-webkit-autofill,
        #jalagistrasi-wrap input.jg-input:-webkit-autofill:hover,
        #jalagistrasi-wrap input.jg-input:-webkit-autofill:focus,
        #jalagistrasi-wrap input.jg-input:-webkit-autofill:active {
            -webkit-text-fill-color: #fff !important;
            -webkit-box-shadow: 0 0 0 1000px rgba(255, 255, 255, 0.07) inset !important;
            box-shadow: 0 0 0 1000px rgba(255, 255, 255, 0.07) inset !important;
            caret-color: #fff;
            transition: background-color 9999s ease-in-out 0s;
        }

        #jalagistrasi-wrap .jg-field-icon { position: relative; }
        #jalagistrasi-wrap .jg-field-icon input { padding-right: 42px; }
        #jalagistrasi-wrap .jg-field-icon button {
            position: absolute; top: 0; right: 0; height: 100%; width: 42px;
            display: flex; align-items: center; justify-content: center;
            background: transparent; border: 0; color: rgba(255, 255, 255, 0.45); cursor: pointer;
        }
        #jalagistrasi-wrap .jg-field-icon button:hover { color: rgba(255, 255, 255, 0.8); }

        #jalagistrasi-wrap .jg-radio-row,
        #jalagistrasi-wrap .jg-checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.75);
            cursor: pointer;
            margin-bottom: 8px;
        }
        #jalagistrasi-wrap .jg-radio-row input,
        #jalagistrasi-wrap .jg-checkbox-row input { accent-color: <?php echo esc_html($c['brand']); ?>; }

        #jalagistrasi-wrap .jg-link {
            color: <?php echo esc_html($c['brand']); ?>;
            text-decoration: none;
            font-size: 13px;
        }
        #jalagistrasi-wrap .jg-link:hover { text-decoration: underline; }

        #jalagistrasi-wrap .jg-empty {
            text-align: center;
            padding: 60px 20px;
        }
        #jalagistrasi-wrap .jg-empty-title {
            font-size: 15px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            margin: 0 0 4px;
        }
        #jalagistrasi-wrap .jg-empty-sub {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.4);
            margin: 0;
        }

        #jalagistrasi-wrap [x-cloak] { display: none !important; }
        </style>
        <?php
    }
}
