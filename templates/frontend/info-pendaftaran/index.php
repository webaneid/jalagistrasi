<?php
/**
 * Halaman info publik — cara pendaftaran, gelombang aktif, tombol daftar.
 * Lihat docs/arsitektur-landing-publik.md.
 *
 * @var list<object> $gelombangAktif
 * @var string        $registrasiUrl
 * @var string        $dashboardUrl
 * @var bool          $isLoggedIn
 * @var string        $namaInstitusi
 * @var string        $tahunAjaranAktif
 * @var string        $alamatInstitusi
 * @var string        $telpInstitusi
 * @var string        $emailInstitusi
 */
defined('ABSPATH') || exit;

$adaGelombangAktif = !empty($gelombangAktif);
$ctaUrl   = $isLoggedIn ? $dashboardUrl : $registrasiUrl;
$ctaLabel = $isLoggedIn ? __('Ke Dashboard Saya', 'jalagistrasi') : __('Daftar Sekarang', 'jalagistrasi');

$alurLangkah = [
    __('Buat Akun', 'jalagistrasi'),
    __('Isi Formulir Pendaftaran', 'jalagistrasi'),
    __('Upload Dokumen Persyaratan (KTP, KK, Ijazah, Pas Foto, dll)', 'jalagistrasi'),
    __('Verifikasi oleh Panitia', 'jalagistrasi'),
    __('Upload Bukti Pembayaran', 'jalagistrasi'),
    __('Tes / Seleksi', 'jalagistrasi'),
    __('Pengumuman Hasil', 'jalagistrasi'),
    __('Daftar Ulang', 'jalagistrasi'),
];

$adaKontak = $alamatInstitusi !== '' || $telpInstitusi !== '' || $emailInstitusi !== '';
?>
<div id="jalagistrasi-wrap">
<div class="min-h-screen bg-gray-50">

    <!-- Hero -->
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-2xl mx-auto px-4 py-12 text-center">
            <?php if ($namaInstitusi !== '') : ?>
                <p class="text-sm font-medium text-brand-600 mb-2"><?php echo esc_html($namaInstitusi); ?></p>
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
                <?php esc_html_e('Pendaftaran Mahasiswa Baru', 'jalagistrasi'); ?>
                <?php if ($tahunAjaranAktif !== '') : ?>
                    <span class="block text-lg font-semibold text-gray-500 mt-1"><?php echo esc_html($tahunAjaranAktif); ?></span>
                <?php endif; ?>
            </h1>

            <?php if ($adaGelombangAktif) : ?>
                <a href="<?php echo esc_url($ctaUrl); ?>"
                   class="inline-block mt-4 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold px-8 py-3 text-sm transition-colors">
                    <?php echo esc_html($ctaLabel); ?>
                </a>
            <?php else : ?>
                <p class="mt-4 text-sm text-gray-500 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 inline-block">
                    <?php esc_html_e('Pendaftaran belum dibuka. Pantau halaman ini untuk info terbaru.', 'jalagistrasi'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-10 space-y-8">

        <!-- Gelombang aktif -->
        <?php if ($adaGelombangAktif) : ?>
            <div>
                <h2 class="text-lg font-bold text-gray-900 mb-4"><?php esc_html_e('Gelombang yang Sedang Dibuka', 'jalagistrasi'); ?></h2>
                <div class="space-y-3">
                    <?php foreach ($gelombangAktif as $g) : ?>
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?php echo esc_html($g->nama); ?></h3>
                                    <p class="text-sm text-gray-500 mt-0.5"><?php echo esc_html($g->tahun_akademik); ?></p>
                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-400">
                                        <span>
                                            <?php esc_html_e('Buka:', 'jalagistrasi'); ?>
                                            <?php echo esc_html(date_i18n('d M Y', strtotime($g->tanggal_buka))); ?>
                                        </span>
                                        <span>
                                            <?php esc_html_e('Tutup:', 'jalagistrasi'); ?>
                                            <?php echo esc_html(date_i18n('d M Y', strtotime($g->tanggal_tutup))); ?>
                                        </span>
                                        <?php if ((float) $g->biaya_pendaftaran > 0) : ?>
                                            <span>
                                                <?php esc_html_e('Biaya:', 'jalagistrasi'); ?>
                                                Rp <?php echo esc_html(number_format((float) $g->biaya_pendaftaran, 0, ',', '.')); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="<?php echo esc_url($ctaUrl); ?>"
                                   class="shrink-0 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 transition-colors">
                                    <?php echo esc_html($ctaLabel); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alur pendaftaran -->
        <div>
            <h2 class="text-lg font-bold text-gray-900 mb-4"><?php esc_html_e('Alur Pendaftaran', 'jalagistrasi'); ?></h2>
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <ol class="space-y-3">
                    <?php foreach ($alurLangkah as $i => $langkah) : ?>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold mt-0.5" style="background:var(--jg-secondary-100);color:var(--jg-secondary-700);">
                                <?php echo (int) $i + 1; ?>
                            </span>
                            <span class="text-sm text-gray-700 pt-0.5"><?php echo esc_html($langkah); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>

        <!-- Kontak -->
        <?php if ($adaKontak) : ?>
            <div>
                <h2 class="text-lg font-bold text-gray-900 mb-4"><?php esc_html_e('Informasi Kontak', 'jalagistrasi'); ?></h2>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 space-y-2 text-sm text-gray-600">
                    <?php if ($alamatInstitusi !== '') : ?>
                        <p><?php echo nl2br(esc_html($alamatInstitusi)); ?></p>
                    <?php endif; ?>
                    <?php if ($telpInstitusi !== '') : ?>
                        <p><?php esc_html_e('Telp/WA:', 'jalagistrasi'); ?> <?php echo esc_html($telpInstitusi); ?></p>
                    <?php endif; ?>
                    <?php if ($emailInstitusi !== '') : ?>
                        <p><?php esc_html_e('Email:', 'jalagistrasi'); ?> <?php echo esc_html($emailInstitusi); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
</div>
