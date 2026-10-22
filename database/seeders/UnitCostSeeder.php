<?php

namespace Database\Seeders;

use App\Models\UnitCostComponent;
use App\Models\UnitCostFormulaVersion;
use App\Models\UnitCostParameter;
use App\Models\UnitCostParameterEntry;
use App\Models\UnitCostPeriod;
use Illuminate\Database\Seeder;

class UnitCostSeeder extends Seeder
{
    /**
     * Master komponen dan parameter unit cost.
     *
     * Seeder ini mengisi definisi master. Nilai dan rumus per periode/unit
     * tetap disimpan sebagai entry. Rumus acuan parameter derived disimpan
     * sebagai default_value master sekaligus versi 1 pada periode yang masih
     * dapat diedit, sehingga form dan evaluasi langsung terisi sebelum entry
     * dibuat saat pengguna menekan Simpan.
     */
    public function run(): void
    {
        $components = [
            ['code' => 'BANGUNAN', 'name' => 'Bangunan', 'category' => 'Volume', 'sort_order' => 10],
            ['code' => 'PASIEN', 'name' => 'Pasien', 'category' => 'Volume', 'sort_order' => 20],
            ['code' => 'BED', 'name' => 'Bed Pasien', 'category' => 'Volume', 'sort_order' => 30],
            ['code' => 'PENYUSUTAN_UNIT', 'name' => 'Penyusutan Unit', 'category' => 'Biaya', 'sort_order' => 40],
            ['code' => 'CIU', 'name' => 'CIU Unit', 'category' => 'Volume', 'sort_order' => 50],
            ['code' => 'LISTRIK', 'name' => 'Listrik', 'category' => 'Biaya', 'sort_order' => 60],
            ['code' => 'AIR', 'name' => 'Air', 'category' => 'Biaya', 'sort_order' => 70],
            ['code' => 'INTERNET', 'name' => 'Internet', 'category' => 'Biaya', 'sort_order' => 80],
            ['code' => 'GEDUNG', 'name' => 'Penyusutan Gedung Rumah Sakit', 'category' => 'Biaya', 'sort_order' => 90],
            ['code' => 'GAJI', 'name' => 'Gaji Pegawai', 'category' => 'Biaya', 'sort_order' => 100],
            ['code' => 'CLEANING', 'name' => 'Overhead Cleaning Service', 'category' => 'Biaya', 'sort_order' => 110],
            ['code' => 'SECURITY', 'name' => 'Overhead Security', 'category' => 'Biaya', 'sort_order' => 120],
            ['code' => 'LIMBAH', 'name' => 'Overhead Limbah Medis', 'category' => 'Biaya', 'sort_order' => 130],
            ['code' => 'LAUNDRY', 'name' => 'Overhead Laundry', 'category' => 'Biaya', 'sort_order' => 140],
            ['code' => 'OVERHEAD_LAIN', 'name' => 'Overhead Lainnya', 'category' => 'Biaya', 'sort_order' => 150],
            ['code' => 'AMENITIES', 'name' => 'Amenities', 'category' => 'Biaya', 'sort_order' => 160],
            ['code' => 'MAKAN', 'name' => 'Makan', 'category' => 'Biaya', 'sort_order' => 170],
            ['code' => 'KONFIGURASI', 'name' => 'Variabel Konfigurasi', 'category' => 'Konfigurasi', 'sort_order' => 180],
        ];

        $componentIds = [];

        foreach ($components as $component) {
            $model = UnitCostComponent::query()->updateOrCreate(
                ['code' => $component['code']],
                array_merge($component, ['is_active' => true])
            );

            $componentIds[$component['code']] = $model->id;
        }

        $manual = static fn (
            string $code,
            string $name,
            ?string $component,
            string $unit,
            string $scope,
            ?string $sourceName,
            int $sortOrder,
            string $valueType = 'decimal',
            ?string $description = null,
        ): array => [
            'code' => $code,
            'variable' => $code,
            'name' => $name,
            'component' => $component,
            'value_type' => $valueType,
            'unit' => $unit,
            'scope' => $scope,
            'default_input_type' => 'value',
            'default_value' => null,
            'source_type' => 'manual',
            'source_name' => $sourceName,
            'description' => $description,
            'formula' => null,
            'is_required' => true,
            'sort_order' => $sortOrder,
        ];

        $systemInput = static fn (
            string $code,
            string $name,
            ?string $component,
            string $unit,
            string $scope,
            int $sortOrder,
            string $valueType = 'decimal',
        ): array => [
            'code' => $code,
            'variable' => $code,
            'name' => $name,
            'component' => $component,
            'value_type' => $valueType,
            'unit' => $unit,
            'scope' => $scope,
            'default_input_type' => 'value',
            'default_value' => null,
            'source_type' => 'manual',
            'source_name' => 'Data Sistem',
            'description' => null,
            'formula' => null,
            'is_required' => true,
            'sort_order' => $sortOrder,
        ];

        $derived = static fn (
            string $code,
            string $name,
            ?string $component,
            string $unit,
            string $scope,
            string $formula,
            int $sortOrder,
            ?string $description = null,
        ): array => [
            'code' => $code,
            'variable' => $code,
            'name' => $name,
            'component' => $component,
            'value_type' => 'decimal',
            'unit' => $unit,
            'scope' => $scope,
            // Rumus acuan juga menjadi default pada master sehingga parameter
            // yang belum punya entry langsung terisi dan dapat dievaluasi.
            'default_input_type' => 'formula',
            'default_value' => $formula,
            'source_type' => 'derived',
            'source_name' => 'Formula',
            'description' => $description,
            'formula' => $formula,
            'is_required' => true,
            'sort_order' => $sortOrder,
        ];

        $parameters = [
            // Bangunan.
            $manual('luas_bangunan_tindakan', 'Luas Bangunan atas Tindakan', 'BANGUNAN', 'm²', 'unit_period', 'GA', 10),
            $manual('luas_bangunan_keseluruhan', 'Luas Bangunan Keseluruhan', 'BANGUNAN', 'm²', 'unit_period', 'GA', 20),
            $derived('persentase_luas_bangunan', 'Presentase Luas Bangunan Bagian yang Dihitung', 'BANGUNAN', '%', 'unit_period', '{luas_bangunan_tindakan} / {luas_bangunan_keseluruhan}', 30, 'Sumber 1/2. Kalikan 100 hanya jika hasil harus berupa persen.'),

            // Pasien.
            $manual('jumlah_pasien_tahunan', 'Jumlah Pasien Selama 1 Tahun pada Unit yang Dihitung', 'PASIEN', 'pasien/tahun', 'unit_period', 'RM / Target RS', 10, 'integer'),
            $manual('jumlah_hari_setahun', 'Jumlah Hari Selama Setahun', 'PASIEN', 'hari', 'unit_period', 'Konfigurasi', 20, 'integer', 'Sumber memakai 365.'),
            $derived('rata_rata_pasien_harian', 'Rata-rata Pasien', 'PASIEN', 'pasien/hari', 'unit_period', '{jumlah_pasien_tahunan} / {jumlah_hari_setahun}', 30),

            // Bed pasien.
            $manual('jumlah_bed', 'Jumlah Bed', 'BED', 'bed', 'unit_period', 'GA', 10, 'integer', 'Rumus lain membagi dengan 7; konfirmasi apakah 7 adalah jumlah bed.'),

            // Penyusutan unit.
            $manual('harga_perolehan_aset_unit', 'Harga Perolehan Aset (Alat Medis dan Non Medis) pada Unit yang Dihitung', 'PENYUSUTAN_UNIT', 'Rp', 'unit_period', 'Unit List Aset / Tim Aset', 10),
            $derived('penyusutan_aset_unit_per_hari', 'Penyusutan Aset pada Unit yang Dihitung per Hari', 'PENYUSUTAN_UNIT', 'Rp/hari', 'unit_period', '{harga_perolehan_aset_unit} / {jumlah_pasien_tahunan} / {jumlah_hari_setahun}', 20, 'Sumber 8/4/365 hari; perlu validasi bisnis.'),

            // CIU unit.
            $manual('jumlah_ciu_unit_tahunan', 'Jumlah CIU Unit per Tahun', 'CIU', 'CIU/tahun', 'unit_period', 'Data Sistem', 10),
            $derived('jumlah_ciu_unit_harian', 'Jumlah CIU Unit per Tahun per Hari', 'CIU', 'CIU/hari', 'unit_period', '{jumlah_ciu_unit_tahunan} / {jumlah_hari_setahun}', 20, 'Sumber 10/365 hari.'),
            $derived('ciu_per_bed', 'CIU per Bed', 'CIU', 'CIU/bed/hari', 'unit_period', '{jumlah_ciu_unit_harian} / {jumlah_bed}', 30, 'Sumber 11/7; denominator perlu dikonfirmasi.'),

            // Listrik.
            $systemInput('biaya_listrik_tahunan', 'Listrik Setahun', 'LISTRIK', 'Rp/tahun', 'unit_period', 10),
            $derived('pembebanan_listrik_unit', 'Pembebanan Listrik Unit', 'LISTRIK', 'Rp', 'unit_period', '{biaya_listrik_tahunan} * {rasio_alokasi_unit}', 20, 'Sumber 13 x 3; angka 3 menjadi {rasio_alokasi_unit}.'),
            $derived('pembebanan_listrik_unit_harian', 'Pembebanan Listrik Unit per Hari', 'LISTRIK', 'Rp/hari', 'unit_period', '{pembebanan_listrik_unit} / {jumlah_hari_setahun}', 30, 'Sumber 14/365 hari.'),
            $derived('pembebanan_listrik_per_bed_harian', 'Pembebanan Listrik Unit per Hari per Pasien', 'LISTRIK', 'Rp/bed/hari', 'unit_period', '{pembebanan_listrik_unit_harian} / {jumlah_bed}', 40, 'Sumber 15/7; denominator perlu dikonfirmasi.'),

            // Air.
            $systemInput('biaya_air_tahunan', 'Air Setahun', 'AIR', 'Rp/tahun', 'unit_period', 10),
            $derived('pembebanan_air_unit', 'Pembebanan Air Unit', 'AIR', 'Rp', 'unit_period', '{biaya_air_tahunan} * {rasio_alokasi_unit}', 20, 'Sumber 17 x 3; angka 3 menjadi {rasio_alokasi_unit}.'),
            $derived('pembebanan_air_unit_harian', 'Pembebanan Air Unit per Hari', 'AIR', 'Rp/hari', 'unit_period', '{pembebanan_air_unit} / {jumlah_hari_setahun}', 30, 'Sumber 18/365 hari.'),
            $derived('pembebanan_air_per_bed_harian', 'Pembebanan Air Unit per Hari per Pasien', 'AIR', 'Rp/bed/hari', 'unit_period', '{pembebanan_air_unit_harian} / {jumlah_bed}', 40, 'Sumber 19/7; denominator perlu dikonfirmasi.'),

            // Internet.
            $systemInput('biaya_internet_tahunan', 'Internet Setahun', 'INTERNET', 'Rp/tahun', 'unit_period', 10),
            $derived('pembebanan_internet_unit', 'Pembebanan Internet Unit', 'INTERNET', 'Rp', 'unit_period', '{biaya_internet_tahunan} * {rasio_alokasi_unit}', 20, 'Sumber 21 x 3; angka 3 menjadi {rasio_alokasi_unit}.'),
            $derived('pembebanan_internet_unit_harian', 'Pembebanan Internet Unit per Hari', 'INTERNET', 'Rp/hari', 'unit_period', '{pembebanan_internet_unit} / {jumlah_hari_setahun}', 30, 'Sumber 22/365 hari.'),
            $derived('pembebanan_internet_per_bed_harian', 'Pembebanan Internet Unit per Hari per Pasien', 'INTERNET', 'Rp/bed/hari', 'unit_period', '{pembebanan_internet_unit_harian} / {jumlah_bed}', 40, 'Sumber 23/7; denominator perlu dikonfirmasi.'),

            // Penyusutan gedung rumah sakit.
            $manual('nilai_perolehan_gedung', 'Nilai Perolehan Gedung', 'GEDUNG', 'Rp', 'unit_period', 'Data Sistem', 10),
            $derived('penyusutan_gedung_tahunan', 'Penyusutan per Tahun', 'GEDUNG', 'Rp/tahun', 'unit_period', '{nilai_perolehan_gedung} / {umur_manfaat_gedung_tahun}', 20, 'Sumber 25/20 tahun.'),
            $derived('penyusutan_gedung_harian', 'Penyusutan per Hari', 'GEDUNG', 'Rp/hari', 'unit_period', '{penyusutan_gedung_tahunan} / {jumlah_hari_setahun}', 30, 'Sumber 26/365 hari.'),
            $derived('pembebanan_penyusutan_gedung_unit', 'Pembebanan Penyusutan Gedung per Unit', 'GEDUNG', 'Rp/hari', 'unit_period', '{penyusutan_gedung_harian} * {rasio_alokasi_unit}', 40, 'Sumber 27 x 3; angka 3 menjadi {rasio_alokasi_unit}.'),
            $derived('penyusutan_gedung_per_bed', 'Pembebanan Penyusutan Gedung per Unit per Bed', 'GEDUNG', 'Rp/bed/hari', 'unit_period', '{pembebanan_penyusutan_gedung_unit} / {jumlah_bed}', 50, 'Sumber 28/7; denominator perlu dikonfirmasi.'),

            // Gaji pegawai.
            $manual('total_gaji_unit_tahunan', 'Total Gaji per Unit Selama Setahun', 'GAJI', 'Rp/tahun', 'unit_period', 'SDM', 10),
            $derived('total_gaji_unit_harian', 'Total Gaji per Hari', 'GAJI', 'Rp/hari', 'unit_period', '{total_gaji_unit_tahunan} / {jumlah_hari_setahun}', 20, 'Sumber 30/365 hari.'),
            $manual('total_shift', 'Total Shift', 'GAJI', 'shift', 'unit_period', 'Manual', 30, 'integer'),
            $derived('beban_gaji_per_bed_shift', 'Beban Gaji per Bed / per Pasien', 'GAJI', 'Rp/bed/shift', 'unit_period', '{total_gaji_unit_harian} / {total_shift} / {jumlah_bed}', 40, 'Sumber 31/32/7; denominator perlu dikonfirmasi.'),

            // Overhead cleaning service.
            $systemInput('biaya_cleaning_tahunan', 'Biaya Cleaning Service per Tahun', 'CLEANING', 'Rp/tahun', 'unit_period', 10),
            $derived('biaya_cleaning_harian', 'Biaya Cleaning Service per Hari', 'CLEANING', 'Rp/hari', 'unit_period', '{biaya_cleaning_tahunan} / {jumlah_hari_setahun}', 20, 'Sumber 34/365 hari.'),
            $derived('pembebanan_cleaning_unit', 'Pembebanan Biaya Cleaning Service per Unit', 'CLEANING', 'Rp/hari', 'unit_period', '{biaya_cleaning_harian} * {rasio_alokasi_unit}', 30, 'Sumber 35 x 3; angka 3 menjadi {rasio_alokasi_unit}.'),
            $derived('cleaning_per_bed', 'Pembebanan Biaya Cleaning Service per Unit per Bed', 'CLEANING', 'Rp/bed/hari', 'unit_period', '{pembebanan_cleaning_unit} / {jumlah_bed}', 40, 'Sumber 36/7.'),

            // Overhead security.
            $systemInput('biaya_security_tahunan', 'Biaya Security per Tahun', 'SECURITY', 'Rp/tahun', 'unit_period', 10),
            $derived('biaya_security_harian', 'Biaya Security per Hari', 'SECURITY', 'Rp/hari', 'unit_period', '{biaya_security_tahunan} / {jumlah_hari_setahun}', 20, 'Sumber 38/365 hari.'),
            $derived('pembebanan_security_unit', 'Pembebanan Biaya Security per Unit', 'SECURITY', 'Rp/hari', 'unit_period', '{biaya_security_harian} * {rasio_alokasi_unit}', 30, 'Sumber 39 x 3; angka 3 menjadi {rasio_alokasi_unit}.'),
            $derived('security_per_bed', 'Pembebanan Biaya Security per Unit per Bed', 'SECURITY', 'Rp/bed/hari', 'unit_period', '{pembebanan_security_unit} / {jumlah_bed}', 40, 'Sumber 40/7.'),

            // Overhead limbah medis.
            $systemInput('biaya_limbah_medis_tahunan', 'Biaya Limbah Medis per Tahun', 'LIMBAH', 'Rp/tahun', 'unit_period', 10),
            $derived('biaya_limbah_medis_harian', 'Biaya Limbah Medis per Hari', 'LIMBAH', 'Rp/hari', 'unit_period', '{biaya_limbah_medis_tahunan} / {jumlah_hari_setahun}', 20, 'Sumber 38/365 hari; nomor sumber duplikat.'),
            $derived('pembebanan_limbah_medis_unit', 'Pembebanan Biaya Limbah Medis per Unit', 'LIMBAH', 'Rp/hari', 'unit_period', '{biaya_limbah_medis_harian} * {rasio_alokasi_unit}', 30, 'Sumber 39 x 3; nomor sumber duplikat.'),
            $derived('limbah_medis_per_bed', 'Pembebanan Biaya Limbah Medis per Unit per Bed', 'LIMBAH', 'Rp/bed/hari', 'unit_period', '{pembebanan_limbah_medis_unit} / {jumlah_bed}', 40, 'Sumber 40/7; nomor sumber duplikat.'),

            // Overhead laundry.
            $systemInput('biaya_laundry_tahunan', 'Biaya Laundry per Tahun', 'LAUNDRY', 'Rp/tahun', 'unit_period', 10),
            $derived('biaya_laundry_harian', 'Biaya Laundry per Hari', 'LAUNDRY', 'Rp/hari', 'unit_period', '{biaya_laundry_tahunan} / {jumlah_hari_setahun}', 20),
            $derived('pembebanan_laundry_unit', 'Pembebanan Biaya Laundry per Unit', 'LAUNDRY', 'Rp/hari', 'unit_period', '{biaya_laundry_harian} * {rasio_alokasi_unit}', 30),
            $derived('laundry_per_bed', 'Pembebanan Biaya Laundry per Unit per Bed', 'LAUNDRY', 'Rp/bed/hari', 'unit_period', '{pembebanan_laundry_unit} / {jumlah_bed}', 40),

            // Overhead lainnya.
            $systemInput('biaya_overhead_lain_tahunan', 'Biaya Overhead Lainnya Setahun', 'OVERHEAD_LAIN', 'Rp/tahun', 'unit_period', 10),
            $derived('biaya_overhead_lain_harian', 'Biaya Overhead Lainnya per Hari', 'OVERHEAD_LAIN', 'Rp/hari', 'unit_period', '{biaya_overhead_lain_tahunan} / {jumlah_hari_setahun}', 20, 'Ejaan dinormalisasi dalam kode variabel.'),
            $derived('pembebanan_overhead_lain_unit', 'Pembebanan Biaya Overhead Lainnya per Unit', 'OVERHEAD_LAIN', 'Rp/hari', 'unit_period', '{biaya_overhead_lain_harian} * {rasio_alokasi_unit}', 30),
            $derived('overhead_lain_per_bed', 'Pembebanan Biaya Overhead Lainnya per Unit per Bed', 'OVERHEAD_LAIN', 'Rp/bed/hari', 'unit_period', '{pembebanan_overhead_lain_unit} / {jumlah_bed}', 40),

            // Amenities.
            $manual('harga_amenities_per_kamar', 'Harga Amenities per Kamar', 'AMENITIES', 'Rp/kamar', 'unit_period', 'GA / Harga Proc', 10),

            // Makan.
            $manual('harga_menu_makan', 'Harga per Menu Makan', 'MAKAN', 'Rp/menu', 'unit_period', 'Gizi', 10),
            $manual('jumlah_makan_per_hari', 'Jumlah Makan per Hari', 'MAKAN', 'menu/hari', 'unit_period', 'Manual', 20, 'integer'),
            $manual('harga_menu_snack', 'Harga per Menu Snack', 'MAKAN', 'Rp/menu', 'unit_period', 'Gizi', 30),
            $manual('jumlah_snack_per_hari', 'Jumlah Snack per Hari', 'MAKAN', 'menu/hari', 'unit_period', 'Manual', 40, 'integer'),
            $derived('biaya_makan_per_kamar_per_hari', 'Biaya Makan per Hari per Kamar', 'MAKAN', 'Rp/kamar/hari', 'unit_period', '({harga_menu_makan} * {jumlah_makan_per_hari}) + ({harga_menu_snack} * {jumlah_snack_per_hari})', 50, 'Sumber (51 x 52) + (53 x 54).'),

            // Variabel konfigurasi.
            $manual('rasio_alokasi_unit', 'Rasio Alokasi Unit', 'KONFIGURASI', 'kali', 'unit_period', 'Konfigurasi', 10, 'decimal', 'Mewakili angka 3 pada rumus pembebanan. Arti dan nilai perlu dikonfirmasi.'),
            $manual('umur_manfaat_gedung_tahun', 'Umur Manfaat Gedung (Tahun)', 'KONFIGURASI', 'tahun', 'unit_period', 'Konfigurasi', 20, 'integer', 'Mewakili 20 tahun pada rumus penyusutan gedung.'),
        ];

        $formulas = [];

        foreach ($parameters as $parameter) {
            $componentCode = $parameter['component'];
            $formula = $parameter['formula'] ?? null;
            unset($parameter['component'], $parameter['formula']);

            UnitCostParameter::query()->updateOrCreate(
                ['code' => $parameter['code']],
                array_merge($parameter, [
                    'component_id' => $componentCode === null ? null : ($componentIds[$componentCode] ?? null),
                    'is_active' => true,
                ])
            );

            if ($formula !== null) {
                $formulas[$parameter['code']] = $formula;
            }
        }

        // Parameter dan komponen dari seeder lama tidak dipakai lagi. Data entry
        // dan riwayat formula tetap disimpan; definisinya hanya dinonaktifkan.
        UnitCostParameter::query()
            ->whereIn('code', [
                'jumlah_tindakan',
                'biaya_sdm_total',
                'biaya_aset_total',
                'biaya_overhead_total',
                'biaya_langsung_total',
                'persentase_luas',
                'alokasi_sdm',
                'alokasi_aset',
                'alokasi_overhead',
                'total_biaya',
                'biaya_per_pasien',
                'biaya_per_tindakan',
                'jumlah_pasien',
                'rata_rata_pasien',
                'harga_perolehan_aset',
                'penyusutan_aset_per_hari',
                'jumlah_ciu_setahun',
                'jumlah_ciu_per_hari',
                'listrik_setahun',
                'pembebanan_listrik_unit_per_hari',
                'pembebanan_listrik_unit_per_hari_per_pasien',
                'air_setahun',
                'pembebanan_air_unit_per_hari',
                'pembebanan_air_unit_per_hari_per_pasien',
                'internet_setahun',
                'pembebanan_internet_unit_per_hari',
                'pembebanan_internet_unit_per_hari_per_pasien',
                'penyusutan_gedung_per_tahun',
                'penyusutan_gedung_per_hari',
                'pembebanan_penyusutan_gedung_unit_per_bed',
                'total_gaji_unit_setahun',
                'total_gaji_unit_per_hari',
                'beban_gaji_per_bed_per_pasien',
                'biaya_cleaning_service_setahun',
                'biaya_cleaning_service_per_hari',
                'pembebanan_biaya_cleaning_service_unit',
                'pembebanan_biaya_cleaning_service_unit_per_bed',
                'biaya_security_setahun',
                'biaya_security_per_hari',
                'pembebanan_biaya_security_unit',
                'pembebanan_biaya_security_unit_per_bed',
                'biaya_limbah_medis_setahun',
                'biaya_limbah_medis_per_hari',
                'pembebanan_biaya_limbah_medis_unit',
                'pembebanan_biaya_limbah_medis_unit_per_bed',
                'biaya_laundry_setahun',
                'biaya_laundry_per_hari',
                'pembebanan_biaya_laundry_unit',
                'pembebanan_biaya_laundry_unit_per_bed',
                'biaya_overhead_lainnya_setahun',
                'biaya_overhead_lainnya_per_hari',
                'pembebanan_biaya_overhead_lainnya_unit',
                'pembebanan_biaya_overhead_lainnya_unit_per_bed',
                'biaya_makan_per_hari_per_kamar',
            ])
            ->update([
                'is_active' => false,
                // Parameter lama tetap dinonaktifkan, tetapi scope-nya ikut
                // diseragamkan agar tidak ada lagi data berscope global.
                'scope' => UnitCostParameter::SCOPE_UNIT_PERIOD,
            ]);

        UnitCostComponent::query()
            ->whereIn('code', ['SDM', 'ASET', 'OVERHEAD', 'LANGSUNG', 'VOLUME', 'HASIL'])
            ->update(['is_active' => false]);

        // Simpan rumus acuan sebagai versi 1 pada periode yang masih dapat
        // diedit. Entry tetap belum dibuat sampai pengguna menekan Simpan.
        $formulaParameters = UnitCostParameter::query()
            ->whereIn('code', array_keys($formulas))
            ->get()
            ->keyBy('code');

        $periods = UnitCostPeriod::query()
            ->where('status', '!=', UnitCostPeriod::STATUS_CLOSED)
            ->get();

        foreach ($periods as $period) {
            foreach ($formulaParameters as $code => $parameter) {
                $hasEntry = UnitCostParameterEntry::query()
                    ->where('period_id', $period->id)
                    ->where('parameter_id', $parameter->id)
                    ->exists();

                $version = UnitCostFormulaVersion::query()->firstOrNew([
                    'parameter_id' => $parameter->id,
                    'period_id' => $period->id,
                    'version' => 1,
                ]);

                if (! $version->exists) {
                    $version->fill([
                        'expression' => $formulas[$code],
                        'created_by' => null,
                    ])->save();
                } elseif (! $hasEntry) {
                    // Perbarui template versi 1 selama pengguna belum punya entry.
                    $version->fill(['expression' => $formulas[$code]])->save();
                }
            }
        }
    }
}
