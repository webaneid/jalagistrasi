<?php
/**
 * Halaman Masuk + Daftar Baru (tab) — tanpa header/footer tema, lihat
 * docs/arsitektur-login-register.md.
 *
 * @var string               $activeTab      'login' | 'register'
 * @var list<string>         $loginErrors
 * @var string               $oldLoginEmail
 * @var string               $loginNonce
 * @var list<string>         $registerErrors
 * @var array<string,string> $registerOld
 * @var string               $registerNonce
 */
defined('ABSPATH') || exit;

require_once JG_PLUGIN_DIR . 'templates/frontend/partials/dark-theme.php';
$theme    = jg_theme_colors();
$colorGen = new \Webane\Jalagistrasi\Service\ColorPaletteGenerator();

// Background: linear-gradient diagonal 4 titik (gelap → terang aksen warna utama →
// gelap lagi → paling gelap) — rasio campur-ke-hitam diturunkan dari contoh referensi
// user (#050e1f → #0a1f48 → #071630 → #040c1a), diterapkan ke warna brand kita supaya
// ikut berubah kalau brand di-custom.
$gradStop1 = $colorGen->mixTowardBlack($theme['brand'], 0.86); // gelap
$gradStop2 = $colorGen->mixTowardBlack($theme['brand'], 0.70); // titik terang aksen
$gradStop3 = $colorGen->mixTowardBlack($theme['brand'], 0.80); // gelap lagi
$gradStop4 = $colorGen->mixTowardBlack($theme['brand'], 0.90); // paling gelap

// Prioritas tampilan identitas institusi: logo > nama institusi > nama situs WP.
// Lihat percakapan "logo prioritas di form login" — pola sama dipakai di halaman
// info pendaftaran publik.
$namaInstitusi = (string) get_option('jalagistrasi_nama_institusi', '');
$logoId        = (int) get_option('jalagistrasi_logo_id', 0);
$logoUrl       = $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'medium') : '';
$namaTampil    = $namaInstitusi !== '' ? $namaInstitusi : (string) get_bloginfo('name');

$old_nama_lengkap = esc_attr($registerOld['nama_lengkap'] ?? '');
$old_email_reg    = esc_attr($registerOld['email'] ?? '');
$old_nomor_wa     = esc_attr($registerOld['nomor_wa'] ?? '');
?>
<div id="jalagistrasi-wrap" x-data="{ tab: '<?php echo esc_js($activeTab); ?>', showPassword: false, showPasswordReg: false }">
<div class="jg-auth-page">

    <div class="jg-auth-card">

        <div class="text-center" style="margin-bottom:28px;">
            <?php if ($logoUrl !== '') : ?>
                <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr($namaTampil); ?>" class="jg-auth-logo">
            <?php else : ?>
                <span class="jg-auth-badge"><?php echo esc_html(mb_strtoupper($namaTampil)); ?></span>
            <?php endif; ?>
            <h1 class="jg-auth-title"><?php esc_html_e('Masuk atau Daftar Baru', 'jalagistrasi'); ?></h1>
        </div>

        <!-- Tab switcher -->
        <div class="jg-auth-tabs">
            <button type="button" class="jg-auth-tab" :class="tab === 'login' ? 'is-active' : ''" @click="tab = 'login'">
                <?php esc_html_e('Masuk', 'jalagistrasi'); ?>
            </button>
            <button type="button" class="jg-auth-tab" :class="tab === 'register' ? 'is-active' : ''" @click="tab = 'register'">
                <?php esc_html_e('Daftar Baru', 'jalagistrasi'); ?>
            </button>
        </div>

        <!-- ============================== TAB: MASUK ============================== -->
        <div x-show="tab === 'login'" x-cloak>
            <?php if (!empty($loginErrors)) : ?>
                <div class="jg-notif jg-notif--danger">
                    <?php foreach ($loginErrors as $error) : ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php echo $loginNonce; // phpcs:ignore WordPress.Security.EscapeOutput -- output wp_nonce_field, sudah aman ?>

                <div class="jg-field">
                    <label for="login_email"><?php esc_html_e('Email', 'jalagistrasi'); ?> <span class="req">*</span></label>
                    <input class="jg-input" type="email" id="login_email" name="email" value="<?php echo esc_attr($oldLoginEmail); ?>"
                           required autocomplete="username" placeholder="nama@email.com">
                </div>

                <div class="jg-field">
                    <label for="login_password"><?php esc_html_e('Password', 'jalagistrasi'); ?> <span class="req">*</span></label>
                    <div class="jg-field-icon">
                        <input class="jg-input" :type="showPassword ? 'text' : 'password'" id="login_password" name="password"
                               required autocomplete="current-password" placeholder="<?php esc_attr_e('Password', 'jalagistrasi'); ?>">
                        <button type="button" @click="showPassword = !showPassword" aria-label="<?php esc_attr_e('Tampilkan/sembunyikan password', 'jalagistrasi'); ?>">
                            <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg x-show="showPassword" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="jg-btn jg-btn--block"><?php esc_html_e('Masuk', 'jalagistrasi'); ?></button>

                <p style="margin:16px 0 0;text-align:center;">
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="jg-link"><?php esc_html_e('Lupa password?', 'jalagistrasi'); ?></a>
                </p>
            </form>
        </div>

        <!-- ============================== TAB: DAFTAR BARU ============================== -->
        <div x-show="tab === 'register'" x-cloak>
            <?php if (!empty($registerErrors)) : ?>
                <div class="jg-notif jg-notif--danger">
                    <?php foreach ($registerErrors as $error) : ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php echo $registerNonce; // phpcs:ignore WordPress.Security.EscapeOutput -- output wp_nonce_field, sudah aman ?>

                <div class="jg-field">
                    <label for="nama_lengkap"><?php esc_html_e('Nama Lengkap', 'jalagistrasi'); ?> <span class="req">*</span></label>
                    <input class="jg-input" type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo $old_nama_lengkap; ?>"
                           required autocomplete="name" placeholder="<?php esc_attr_e('Nama sesuai KTP', 'jalagistrasi'); ?>">
                </div>

                <div class="jg-field">
                    <label for="reg_email"><?php esc_html_e('Email', 'jalagistrasi'); ?> <span class="req">*</span></label>
                    <input class="jg-input" type="email" id="reg_email" name="email" value="<?php echo $old_email_reg; ?>"
                           required autocomplete="email" placeholder="contoh@email.com">
                </div>

                <div class="jg-field">
                    <label for="nomor_wa"><?php esc_html_e('Nomor WhatsApp', 'jalagistrasi'); ?> <span class="req">*</span></label>
                    <input class="jg-input" type="tel" id="nomor_wa" name="nomor_wa" value="<?php echo $old_nomor_wa; ?>"
                           required autocomplete="tel" placeholder="08xxxxxxxxxx">
                </div>

                <div class="jg-field">
                    <label for="reg_password"><?php esc_html_e('Buat Password', 'jalagistrasi'); ?> <span class="req">*</span></label>
                    <div class="jg-field-icon">
                        <input class="jg-input" :type="showPasswordReg ? 'text' : 'password'" id="reg_password" name="password"
                               required autocomplete="new-password" placeholder="<?php esc_attr_e('Minimal 8 karakter', 'jalagistrasi'); ?>">
                        <button type="button" @click="showPasswordReg = !showPasswordReg" aria-label="<?php esc_attr_e('Tampilkan/sembunyikan password', 'jalagistrasi'); ?>">
                            <svg x-show="!showPasswordReg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg x-show="showPasswordReg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                </div>

                <div class="jg-field">
                    <label for="konfirmasi_password"><?php esc_html_e('Konfirmasi Password', 'jalagistrasi'); ?> <span class="req">*</span></label>
                    <input class="jg-input" :type="showPasswordReg ? 'text' : 'password'" id="konfirmasi_password" name="konfirmasi_password"
                           required autocomplete="new-password" placeholder="<?php esc_attr_e('Ulangi password', 'jalagistrasi'); ?>">
                </div>

                <button type="submit" class="jg-btn jg-btn--block"><?php esc_html_e('Daftar Sekarang', 'jalagistrasi'); ?></button>
            </form>
        </div>

    </div>

</div>
</div>

<?php jg_render_base_styles(); ?>
<style>
#jalagistrasi-wrap .jg-auth-page {
    min-height: 100vh;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
    background-color: <?php echo esc_html($gradStop4); ?>;
    background-image: linear-gradient(
        135deg,
        <?php echo esc_html($gradStop1); ?> 0%,
        <?php echo esc_html($gradStop2); ?> 30%,
        <?php echo esc_html($gradStop3); ?> 55%,
        <?php echo esc_html($gradStop4); ?> 100%
    );
}

#jalagistrasi-wrap .jg-auth-card {
    width: 100%;
    max-width: 420px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 28px;
    padding: 36px 32px;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

#jalagistrasi-wrap .jg-auth-logo {
    max-height: 56px;
    max-width: 200px;
    margin: 0 auto 16px;
    display: block;
    object-fit: contain;
    /* Logo asli biasanya berwarna/gelap — paksa siluet putih solid supaya
       kontras di atas kartu glass gelap (sama seperti halaman info publik). */
    filter: brightness(0) invert(1);
}

#jalagistrasi-wrap .jg-auth-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 9999px;
    background: rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.25);
    border: 1px solid rgba(<?php echo esc_html($theme['brandRgb']); ?>, 0.5);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
}

#jalagistrasi-wrap .jg-auth-title {
    margin: 14px 0 0;
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    line-height: 1.3;
}

#jalagistrasi-wrap .jg-auth-tabs {
    display: flex;
    gap: 4px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 4px;
    margin-bottom: 24px;
}

#jalagistrasi-wrap .jg-auth-tab {
    flex: 1;
    padding: 10px 0;
    border: 0;
    background: transparent;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.55);
    cursor: pointer;
    transition: background-color .15s, color .15s;
}

#jalagistrasi-wrap .jg-auth-tab.is-active {
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
}
</style>
