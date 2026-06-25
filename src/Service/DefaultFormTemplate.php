<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

use Webane\Jalagistrasi\Repository\FormSchemaRepository;

/**
 * Seed 34 field default berdasarkan Formulir Pendaftaran Mahasiswa Baru.
 * Dipanggil otomatis saat gelombang baru dibuat.
 */
class DefaultFormTemplate
{
    private FormSchemaRepository $repo;

    public function __construct()
    {
        $this->repo = new FormSchemaRepository();
    }

    public function seedForGelombang(int $gelombangId): void
    {
        // Jangan seed ulang jika field sudah ada
        if ($this->repo->countByGelombang($gelombangId) > 0) {
            return;
        }

        foreach ($this->getFields() as $field) {
            $this->repo->insert(array_merge($field, ['gelombang_id' => $gelombangId]));
        }
    }

    /** @return list<array<string,mixed>> */
    private function getFields(): array
    {
        return [
            // ----------------------------------------------------------------
            // SEKSI 1: Biodata Pribadi
            // ----------------------------------------------------------------
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'nama_lengkap',
                'label'        => 'Nama Lengkap',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 1,
                'urutan'       => 1,
                'konfigurasi'  => ['placeholder' => 'Sesuai Kartu Keluarga / Ijazah', 'max_length' => 200],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'tempat_lahir',
                'label'        => 'Tempat Lahir',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 2,
                'konfigurasi'  => ['placeholder' => 'Kota/Kabupaten tempat lahir', 'max_length' => 100],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'tanggal_lahir',
                'label'        => 'Tanggal Lahir',
                'tipe'         => 'date',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 3,
                'konfigurasi'  => ['min' => '1990-01-01', 'max' => '2015-12-31'],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'jenis_kelamin',
                'label'        => 'Jenis Kelamin',
                'tipe'         => 'radio',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 4,
                'konfigurasi'  => ['options' => ['Laki-laki', 'Perempuan']],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'alamat_jalan',
                'label'        => 'Alamat Jalan',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 5,
                'konfigurasi'  => ['placeholder' => 'Nama jalan / nomor rumah', 'max_length' => 200],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'alamat_dusun',
                'label'        => 'Dusun',
                'tipe'         => 'text',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 6,
                'konfigurasi'  => ['max_length' => 100],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'alamat_rt',
                'label'        => 'RT',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 7,
                'konfigurasi'  => ['placeholder' => '001', 'max_length' => 5],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'alamat_rw',
                'label'        => 'RW',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 8,
                'konfigurasi'  => ['placeholder' => '001', 'max_length' => 5],
            ],
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
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'alamat_kode_pos',
                'label'        => 'Kode Pos',
                'tipe'         => 'text',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 11,
                'konfigurasi'  => ['placeholder' => '45xxx', 'max_length' => 10],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'nik',
                'label'        => 'NIK',
                'tipe'         => 'nik',
                'is_required'  => 1,
                'is_core'      => 1,
                'urutan'       => 12,
                'konfigurasi'  => null,
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'nisn',
                'label'        => 'NISN',
                'tipe'         => 'nisn',
                'is_required'  => 1,
                'is_core'      => 1,
                'urutan'       => 13,
                'konfigurasi'  => null,
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'nomor_hp',
                'label'        => 'No. HP / WhatsApp',
                'tipe'         => 'phone',
                'is_required'  => 1,
                'is_core'      => 1,
                'urutan'       => 14,
                'konfigurasi'  => null,
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'email',
                'label'        => 'Email',
                'tipe'         => 'email',
                'is_required'  => 1,
                'is_core'      => 1,
                'urutan'       => 15,
                'konfigurasi'  => null,
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'agama',
                'label'        => 'Agama',
                'tipe'         => 'select',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 16,
                'konfigurasi'  => ['options' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu']],
            ],
            [
                'section_name' => 'Biodata Pribadi',
                'nama_field'   => 'kewarganegaraan_suku',
                'label'        => 'Kewarganegaraan / Suku',
                'tipe'         => 'text',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 17,
                'konfigurasi'  => ['placeholder' => 'WNI / Sunda, Jawa, dll', 'max_length' => 100],
            ],
            // Catatan: Pas Foto TIDAK lagi bagian dari formulir dinamis ini.
            // Pas Foto otomatis tersedia sebagai dokumen wajib di Step 3 (Upload Berkas)
            // lewat DefaultTipeBerkasSeeder — lihat src/Service/DefaultTipeBerkasSeeder.php.

            // ----------------------------------------------------------------
            // SEKSI 2: Sekolah Asal
            // ----------------------------------------------------------------
            [
                'section_name' => 'Sekolah Asal',
                'nama_field'   => 'jenis_sekolah',
                'label'        => 'Jenis Sekolah',
                'tipe'         => 'select',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 19,
                'konfigurasi'  => ['options' => ['SMA', 'SMK', 'MA', 'Paket C']],
            ],
            [
                'section_name' => 'Sekolah Asal',
                'nama_field'   => 'nama_sekolah',
                'label'        => 'Nama Sekolah',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 20,
                'konfigurasi'  => ['max_length' => 200],
            ],
            [
                'section_name' => 'Sekolah Asal',
                'nama_field'   => 'alamat_sekolah',
                'label'        => 'Alamat Sekolah',
                'tipe'         => 'textarea',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 21,
                'konfigurasi'  => ['max_length' => 300],
            ],
            [
                'section_name' => 'Sekolah Asal',
                'nama_field'   => 'tahun_lulus',
                'label'        => 'Tahun Lulus',
                'tipe'         => 'number',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 22,
                'konfigurasi'  => ['min' => 2000, 'max' => 2030],
            ],
            [
                'section_name' => 'Sekolah Asal',
                'nama_field'   => 'nomor_ijazah',
                'label'        => 'Nomor Ijazah',
                'tipe'         => 'text',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 23,
                'konfigurasi'  => ['max_length' => 100],
            ],

            // ----------------------------------------------------------------
            // SEKSI 3: Biodata Orang Tua
            // ----------------------------------------------------------------
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'nik_ayah',
                'label'        => 'NIK Ayah',
                'tipe'         => 'nik',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 24,
                'konfigurasi'  => null,
            ],
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'nama_ayah',
                'label'        => 'Nama Ayah',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 25,
                'konfigurasi'  => ['max_length' => 200],
            ],
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'tanggal_lahir_ayah',
                'label'        => 'Tanggal Lahir Ayah',
                'tipe'         => 'date',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 26,
                'konfigurasi'  => ['min' => '1950-01-01', 'max' => '2000-12-31'],
            ],
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'pendidikan_ayah',
                'label'        => 'Pendidikan Terakhir Ayah',
                'tipe'         => 'select',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 27,
                'konfigurasi'  => ['options' => ['Tidak Sekolah', 'SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3']],
            ],
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'nik_ibu',
                'label'        => 'NIK Ibu',
                'tipe'         => 'nik',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 28,
                'konfigurasi'  => null,
            ],
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'nama_ibu',
                'label'        => 'Nama Ibu',
                'tipe'         => 'text',
                'is_required'  => 1,
                'is_core'      => 0,
                'urutan'       => 29,
                'konfigurasi'  => ['max_length' => 200],
            ],
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'tanggal_lahir_ibu',
                'label'        => 'Tanggal Lahir Ibu',
                'tipe'         => 'date',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 30,
                'konfigurasi'  => ['min' => '1950-01-01', 'max' => '2000-12-31'],
            ],
            [
                'section_name' => 'Biodata Orang Tua',
                'nama_field'   => 'pendidikan_ibu',
                'label'        => 'Pendidikan Terakhir Ibu',
                'tipe'         => 'select',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 31,
                'konfigurasi'  => ['options' => ['Tidak Sekolah', 'SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3']],
            ],

            // ----------------------------------------------------------------
            // SEKSI 4: Pertanyaan Tambahan
            // ----------------------------------------------------------------
            [
                'section_name' => 'Pertanyaan Tambahan',
                'nama_field'   => 'penghasilan_ayah',
                'label'        => 'Penghasilan Ayah per Bulan',
                'tipe'         => 'radio',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 32,
                'konfigurasi'  => [
                    'options' => [
                        'Di bawah Rp 500.000',
                        'Rp 500.000 – Rp 1.000.000',
                        'Rp 1.000.000 – Rp 2.000.000',
                        'Rp 2.000.000 – Rp 3.000.000',
                        'Rp 3.000.000 – Rp 4.000.000',
                        'Di atas Rp 4.000.000',
                    ],
                ],
            ],
            [
                'section_name' => 'Pertanyaan Tambahan',
                'nama_field'   => 'penghasilan_ibu',
                'label'        => 'Penghasilan Ibu per Bulan',
                'tipe'         => 'radio',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 33,
                'konfigurasi'  => [
                    'options' => [
                        'Di bawah Rp 500.000',
                        'Rp 500.000 – Rp 1.000.000',
                        'Rp 1.000.000 – Rp 2.000.000',
                        'Rp 2.000.000 – Rp 3.000.000',
                        'Rp 3.000.000 – Rp 4.000.000',
                        'Di atas Rp 4.000.000',
                    ],
                ],
            ],
            [
                'section_name' => 'Pertanyaan Tambahan',
                'nama_field'   => 'sumber_informasi',
                'label'        => 'Darimana Anda mengetahui kami?',
                'tipe'         => 'checkbox',
                'is_required'  => 0,
                'is_core'      => 0,
                'urutan'       => 34,
                'konfigurasi'  => [
                    'options' => [
                        'Teman',
                        'Saudara / Keluarga',
                        'Brosur / Spanduk / Poster',
                        'Website',
                        'Instagram',
                        'Facebook',
                        'TikTok',
                        'Iklan',
                        'Pameran Pendidikan',
                        'Presentasi ke Sekolah',
                        'Lainnya',
                    ],
                ],
            ],
        ];
    }
}
