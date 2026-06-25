<?php
/**
 * Halaman konfirmasi setelah submit pendaftaran berhasil.
 *
 * @var object|null  $pendaftaran   Record pendaftaran
 * @var list<object> $prodiPilihan  Pilihan prodi (join nama)
 * @var string       $namaInstitusi Nama institusi dari setting
 */

defined('ABSPATH') || exit;

$dashboardUrl = remove_query_arg(['action', 'ref'], (string) get_permalink());
?>
<div id="jalagistrasi-wrap">
    <div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-lg">

            <?php if (!$pendaftaran) : ?>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center">
                    <p class="text-gray-500"><?php esc_html_e('Pendaftaran tidak ditemukan.', 'jalagistrasi'); ?></p>
                    <a href="<?php echo esc_url($dashboardUrl); ?>"
                       class="mt-4 inline-block text-sm text-brand-600 hover:text-brand-700">
                        <?php esc_html_e('Kembali ke dashboard', 'jalagistrasi'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">

                    <!-- Ikon sukses -->
                    <div class="flex justify-center mb-5">
                        <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center">
                            <svg class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>

                    <h1 class="text-center text-xl font-bold text-gray-900">
                        <?php esc_html_e('Pendaftaran Berhasil!', 'jalagistrasi'); ?>
                    </h1>

                    <?php if ($namaInstitusi) : ?>
                        <p class="text-center text-sm text-gray-500 mt-1">
                            <?php echo esc_html($namaInstitusi); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Nomor pendaftaran -->
                    <div class="mt-6 rounded-xl bg-brand-50 border border-brand-200 p-4 text-center">
                        <p class="text-xs text-brand-600 font-medium uppercase tracking-wide">
                            <?php esc_html_e('Nomor Pendaftaran', 'jalagistrasi'); ?>
                        </p>
                        <p class="mt-1 text-2xl font-bold text-brand-700 tracking-widest">
                            <?php echo esc_html($pendaftaran->nomor_pendaftaran); ?>
                        </p>
                        <p class="mt-1 text-xs text-brand-500">
                            <?php esc_html_e('Simpan nomor ini sebagai referensi.', 'jalagistrasi'); ?>
                        </p>
                    </div>

                    <!-- Info gelombang -->
                    <div class="mt-5 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500"><?php esc_html_e('Gelombang', 'jalagistrasi'); ?></span>
                            <span class="font-medium text-gray-900"><?php echo esc_html($pendaftaran->gelombang_nama ?? '—'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500"><?php esc_html_e('Status', 'jalagistrasi'); ?></span>
                            <span class="inline-flex items-center rounded-full bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5">
                                <?php esc_html_e('Formulir Disubmit', 'jalagistrasi'); ?>
                            </span>
                        </div>
                        <?php if ($pendaftaran->submitted_at) : ?>
                            <div class="flex justify-between">
                                <span class="text-gray-500"><?php esc_html_e('Dikirim pada', 'jalagistrasi'); ?></span>
                                <span class="text-gray-900"><?php echo esc_html(date_i18n('d M Y, H:i', strtotime($pendaftaran->submitted_at))); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pilihan prodi -->
                    <?php if (!empty($prodiPilihan)) : ?>
                        <div class="mt-5 pt-5 border-t border-gray-100">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">
                                <?php esc_html_e('Pilihan Program Studi', 'jalagistrasi'); ?>
                            </p>
                            <ol class="space-y-1">
                                <?php foreach ($prodiPilihan as $pp) : ?>
                                    <li class="flex items-start gap-2 text-sm">
                                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-medium">
                                            <?php echo (int) $pp->urutan; ?>
                                        </span>
                                        <span class="text-gray-900">
                                            <?php echo esc_html($pp->prodi_nama); ?>
                                            <span class="text-gray-400">(<?php echo esc_html($pp->prodi_kode); ?>)</span>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo esc_url($dashboardUrl); ?>"
                       class="mt-6 block w-full text-center rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium py-2.5 text-sm transition-colors">
                        <?php esc_html_e('Kembali ke Dashboard', 'jalagistrasi'); ?>
                    </a>

                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
