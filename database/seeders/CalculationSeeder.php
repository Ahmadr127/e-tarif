<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed master calculation engine unit cost.
 *
 * Seluruh label, deskripsi, dan catatan menggunakan bahasa Indonesia. Kode
 * teknis (code) tetap memakai bahasa Inggris agar stabil dipakai di ekspresi
 * formula dan referensi database.
 *
 * Aman dijalankan berulang: data awal hanya di-insert jika belum ada. Data
 * yang sudah dikustomisasi pengguna tidak ditimpa. Nilai rasio alokasi sengaja
 * null karena harus disepakati pemilik data, bukan diasumsikan.
 */
class CalculationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $components = [
                ['code' => 'building', 'name' => 'Bangunan', 'category' => 'building', 'description' => 'Luas dan alokasi bangunan', 'sort_order' => 1, 'is_active' => true],
                ['code' => 'patients', 'name' => 'Pasien', 'category' => 'volume', 'description' => 'Volume pasien tahunan', 'sort_order' => 2, 'is_active' => true],
                ['code' => 'beds', 'name' => 'Tempat Tidur Pasien', 'category' => 'capacity', 'description' => 'Kapasitas tempat tidur', 'sort_order' => 3, 'is_active' => true],
                ['code' => 'asset_depreciation', 'name' => 'Penyusutan Aset Unit', 'category' => 'asset', 'description' => 'Penyusutan aset medis dan nonmedis', 'sort_order' => 4, 'is_active' => true],
                ['code' => 'ciu', 'name' => 'CIU Unit', 'category' => 'volume', 'description' => 'Volume CIU', 'sort_order' => 5, 'is_active' => true],
                ['code' => 'electricity', 'name' => 'Listrik', 'category' => 'utility', 'description' => 'Biaya listrik', 'sort_order' => 6, 'is_active' => true],
                ['code' => 'water', 'name' => 'Air', 'category' => 'utility', 'description' => 'Biaya air', 'sort_order' => 7, 'is_active' => true],
                ['code' => 'internet', 'name' => 'Internet', 'category' => 'utility', 'description' => 'Biaya internet', 'sort_order' => 8, 'is_active' => true],
                ['code' => 'building_depreciation', 'name' => 'Penyusutan Gedung Rumah Sakit', 'category' => 'asset', 'description' => 'Penyusutan gedung', 'sort_order' => 9, 'is_active' => true],
                ['code' => 'salary', 'name' => 'Gaji Pegawai', 'category' => 'personnel', 'description' => 'Biaya SDM', 'sort_order' => 10, 'is_active' => true],
                ['code' => 'cleaning_service', 'name' => 'Biaya Tak Langsung Jasa Kebersihan', 'category' => 'overhead', 'description' => 'Biaya jasa kebersihan', 'sort_order' => 11, 'is_active' => true],
                ['code' => 'security', 'name' => 'Biaya Tak Langsung Keamanan', 'category' => 'overhead', 'description' => 'Biaya keamanan', 'sort_order' => 12, 'is_active' => true],
                ['code' => 'medical_waste', 'name' => 'Biaya Tak Langsung Limbah Medis', 'category' => 'overhead', 'description' => 'Biaya limbah medis', 'sort_order' => 13, 'is_active' => true],
                ['code' => 'laundry', 'name' => 'Biaya Tak Langsung Laundry', 'category' => 'overhead', 'description' => 'Biaya laundry', 'sort_order' => 14, 'is_active' => true],
                ['code' => 'other_overhead', 'name' => 'Biaya Tak Langsung Lainnya', 'category' => 'overhead', 'description' => 'Biaya tak langsung lainnya', 'sort_order' => 15, 'is_active' => true],
                ['code' => 'amenities', 'name' => 'Perlengkapan Kamar', 'category' => 'consumable', 'description' => 'Biaya perlengkapan kamar', 'sort_order' => 16, 'is_active' => true],
                ['code' => 'meals', 'name' => 'Makan', 'category' => 'consumable', 'description' => 'Biaya makan dan snack', 'sort_order' => 17, 'is_active' => true],
            ];

            foreach ($components as $row) {
                DB::table('cost_components')->insertOrIgnore(
                    array_merge($row, ['updated_at' => $now, 'created_at' => $now])
                );
            }

            $componentIds = DB::table('cost_components')->pluck('id', 'code');

            $variables = [
                ['code' => 'jumlah_hari_per_tahun', 'name' => 'Jumlah hari per tahun', 'component_id' => null, 'value_type' => 'integer', 'default_value' => 365, 'unit' => 'hari', 'scope' => 'global', 'source_type' => 'global', 'source_name' => null, 'description' => 'Parameter global; ubah bila kebijakan periode mengharuskan.', 'is_required' => true, 'is_active' => true],
                ['code' => 'days_per_year', 'name' => 'Jumlah hari per tahun (kode lama)', 'component_id' => null, 'value_type' => 'decimal', 'default_value' => 365, 'unit' => 'hari', 'scope' => 'global', 'source_type' => 'global', 'source_name' => null, 'description' => 'Dipertahankan untuk formula historis. Formula baru menggunakan jumlah_hari_per_tahun.', 'is_required' => true, 'is_active' => false],
                ['code' => 'luas_bangunan_tindakan', 'name' => 'Luas bangunan untuk tindakan', 'component_id' => $componentIds['building'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'm2', 'scope' => 'unit', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Contoh Global Variable manual untuk formula persentase luas.', 'is_required' => true, 'is_active' => true],
                ['code' => 'luas_bangunan_keseluruhan', 'name' => 'Luas keseluruhan bangunan', 'component_id' => $componentIds['building'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'm2', 'scope' => 'global', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Contoh Global Variable manual untuk penyebut persentase luas.', 'is_required' => true, 'is_active' => true],
                ['code' => 'persentase_luas_bangunan', 'name' => 'Persentase luas bangunan', 'component_id' => $componentIds['building'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => '%', 'scope' => 'unit_period', 'source_type' => 'derived', 'source_name' => null, 'description' => 'Dihitung otomatis dari formula persentase_luas_bangunan.', 'is_required' => true, 'is_active' => true],
                ['code' => 'biaya_listrik_tahunan', 'name' => 'Biaya listrik tahunan', 'component_id' => $componentIds['electricity'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Contoh Global Variable biaya listrik tahunan.', 'is_required' => true, 'is_active' => true],
                ['code' => 'persentase_alokasi_listrik', 'name' => 'Persentase alokasi listrik', 'component_id' => $componentIds['electricity'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => '%', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 100; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'penyusutan_gedung_tahunan', 'name' => 'Penyusutan gedung tahunan', 'component_id' => $componentIds['building_depreciation'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Tim Aset', 'description' => 'Contoh Global Variable penyusutan gedung tahunan.', 'is_required' => true, 'is_active' => true],
                ['code' => 'building_useful_life_years', 'name' => 'Umur ekonomis gedung', 'component_id' => null, 'value_type' => 'decimal', 'default_value' => 20, 'unit' => 'tahun', 'scope' => 'global', 'source_type' => 'global', 'source_name' => null, 'description' => 'Nilai awal dari referensi Excel; validasi dengan kebijakan aset.', 'is_required' => true, 'is_active' => true],
                ['code' => 'currency_precision', 'name' => 'Presisi tampilan mata uang', 'component_id' => null, 'value_type' => 'decimal', 'default_value' => 2, 'unit' => 'digit', 'scope' => 'global', 'source_type' => 'global', 'source_name' => null, 'description' => 'Untuk pembulatan tampilan, bukan mengurangi presisi perhitungan internal.', 'is_required' => false, 'is_active' => true],
                ['code' => 'building_area_allocated', 'name' => 'Luas bangunan bagian/unit yang dihitung', 'component_id' => $componentIds['building'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'm2', 'scope' => 'unit', 'source_type' => 'integration', 'source_name' => 'GA', 'description' => 'Luas bangunan yang dialokasikan ke unit.', 'is_required' => true, 'is_active' => true],
                ['code' => 'building_area_total', 'name' => 'Luas bangunan keseluruhan', 'component_id' => $componentIds['building'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'm2', 'scope' => 'global', 'source_type' => 'integration', 'source_name' => 'GA', 'description' => 'Total luas bangunan.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_patient_count', 'name' => 'Jumlah pasien selama satu tahun', 'component_id' => $componentIds['patients'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'pasien/tahun', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'RM/Target RS', 'description' => 'Gunakan definisi volume pasien yang konsisten.', 'is_required' => true, 'is_active' => true],
                ['code' => 'bed_count', 'name' => 'Jumlah tempat tidur', 'component_id' => $componentIds['beds'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'tempat tidur', 'scope' => 'unit', 'source_type' => 'integration', 'source_name' => 'GA', 'description' => 'Jumlah tempat tidur aktif untuk unit dan periode.', 'is_required' => true, 'is_active' => true],
                ['code' => 'asset_acquisition_cost', 'name' => 'Total harga perolehan aset unit', 'component_id' => $componentIds['asset_depreciation'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'Daftar aset/Tim Aset', 'description' => 'Idealnya dihitung dari aset aktif dan umur ekonomis masing-masing aset.', 'is_required' => true, 'is_active' => true],
                ['code' => 'asset_useful_life_days', 'name' => 'Umur ekonomis aset dalam hari', 'component_id' => $componentIds['asset_depreciation'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'hari', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'Daftar aset/Tim Aset', 'description' => 'Untuk perhitungan agregat; lebih akurat dihitung per aset.', 'is_required' => false, 'is_active' => true],
                ['code' => 'annual_ciu_count', 'name' => 'Jumlah CIU unit per tahun', 'component_id' => $componentIds['ciu'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'CIU/tahun', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Definisi CIU harus disepakati dengan pemilik data.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_electricity_cost', 'name' => 'Biaya listrik setahun', 'component_id' => $componentIds['electricity'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya listrik pada lingkup sumber yang disepakati.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_water_cost', 'name' => 'Biaya air setahun', 'component_id' => $componentIds['water'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya air pada lingkup sumber yang disepakati.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_internet_cost', 'name' => 'Biaya internet setahun', 'component_id' => $componentIds['internet'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya internet pada lingkup sumber yang disepakati.', 'is_required' => true, 'is_active' => true],
                ['code' => 'building_acquisition_value', 'name' => 'Nilai perolehan gedung', 'component_id' => $componentIds['building_depreciation'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR', 'scope' => 'global', 'source_type' => 'integration', 'source_name' => 'Data sistem/Tim Aset', 'description' => 'Nilai perolehan gedung.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_unit_salary', 'name' => 'Total gaji unit setahun', 'component_id' => $componentIds['salary'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'SDM', 'description' => 'Termasuk komponen gaji sesuai kebijakan.', 'is_required' => true, 'is_active' => true],
                ['code' => 'work_shifts_per_day', 'name' => 'Jumlah shift per hari', 'component_id' => null, 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'shift/hari', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Harus diisi sesuai jadwal operasional; jangan mengasumsikan nilai 3.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_cleaning_cost', 'name' => 'Biaya jasa kebersihan per tahun', 'component_id' => $componentIds['cleaning_service'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya tahunan sebelum alokasi unit.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_security_cost', 'name' => 'Biaya keamanan per tahun', 'component_id' => $componentIds['security'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya tahunan sebelum alokasi unit.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_medical_waste_cost', 'name' => 'Biaya limbah medis per tahun', 'component_id' => $componentIds['medical_waste'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya tahunan sebelum alokasi unit.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_laundry_cost', 'name' => 'Biaya laundry per tahun', 'component_id' => $componentIds['laundry'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya tahunan sebelum alokasi unit.', 'is_required' => true, 'is_active' => true],
                ['code' => 'annual_other_overhead', 'name' => 'Biaya tak langsung lainnya setahun', 'component_id' => $componentIds['other_overhead'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/tahun', 'scope' => 'period', 'source_type' => 'integration', 'source_name' => 'Data sistem', 'description' => 'Biaya tahunan sebelum alokasi unit.', 'is_required' => true, 'is_active' => true],
                ['code' => 'amenities_price_per_room', 'name' => 'Harga perlengkapan kamar', 'component_id' => $componentIds['amenities'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/kamar', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'GA/Daftar Perlengkapan', 'description' => 'Belum tentu biaya harian; perlu frekuensi penggantian/pemakaian.', 'is_required' => false, 'is_active' => true],
                ['code' => 'meal_price', 'name' => 'Harga per menu makan', 'component_id' => $componentIds['meals'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/menu', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'Gizi', 'description' => 'Harga per menu makan.', 'is_required' => false, 'is_active' => true],
                ['code' => 'meals_per_day', 'name' => 'Jumlah makan per hari', 'component_id' => $componentIds['meals'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'menu/hari', 'scope' => 'global', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Parameter pola makan.', 'is_required' => false, 'is_active' => true],
                ['code' => 'snack_price', 'name' => 'Harga per menu snack', 'component_id' => $componentIds['meals'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'IDR/menu', 'scope' => 'unit_period', 'source_type' => 'integration', 'source_name' => 'Gizi', 'description' => 'Harga per menu snack.', 'is_required' => false, 'is_active' => true],
                ['code' => 'snacks_per_day', 'name' => 'Jumlah snack per hari', 'component_id' => $componentIds['meals'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'menu/hari', 'scope' => 'global', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Parameter pola snack.', 'is_required' => false, 'is_active' => true],
                ['code' => 'electricity_allocation_ratio', 'name' => 'Persentase alokasi listrik', 'component_id' => $componentIds['electricity'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'water_allocation_ratio', 'name' => 'Persentase alokasi air', 'component_id' => $componentIds['water'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'internet_allocation_ratio', 'name' => 'Persentase alokasi internet', 'component_id' => $componentIds['internet'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'building_allocation_ratio', 'name' => 'Persentase alokasi gedung', 'component_id' => $componentIds['building_depreciation'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'derived', 'source_name' => null, 'description' => 'Default dihitung dari luas unit / luas total.', 'is_required' => true, 'is_active' => true],
                ['code' => 'cleaning_allocation_ratio', 'name' => 'Persentase alokasi cleaning service', 'component_id' => $componentIds['cleaning_service'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'security_allocation_ratio', 'name' => 'Persentase alokasi security', 'component_id' => $componentIds['security'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'medical_waste_allocation_ratio', 'name' => 'Persentase alokasi limbah medis', 'component_id' => $componentIds['medical_waste'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'laundry_allocation_ratio', 'name' => 'Persentase alokasi laundry', 'component_id' => $componentIds['laundry'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
                ['code' => 'other_overhead_allocation_ratio', 'name' => 'Persentase alokasi biaya tak langsung lainnya', 'component_id' => $componentIds['other_overhead'], 'value_type' => 'decimal', 'default_value' => null, 'unit' => 'rasio 0-1', 'scope' => 'unit_period', 'source_type' => 'manual', 'source_name' => null, 'description' => 'Nilai 0 sampai 1; metode alokasi harus disetujui.', 'is_required' => true, 'is_active' => true],
            ];

            foreach ($variables as $row) {
                DB::table('calculation_variables')->insertOrIgnore(
                    array_merge($row, ['updated_at' => $now, 'created_at' => $now])
                );
            }

            $formulas = [
                ['code' => 'building_allocation_ratio', 'name' => 'Persentase luas bangunan', 'component_id' => $componentIds['building'], 'expression' => 'building_area_allocated / building_area_total', 'result_unit' => 'rasio 0-1', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Mengikuti Excel: luas bagian dibagi luas keseluruhan.', 'is_active' => true],
                ['code' => 'average_patients_per_day', 'name' => 'Rata-rata pasien per hari', 'component_id' => $componentIds['patients'], 'expression' => 'annual_patient_count / days_per_year', 'result_unit' => 'pasien/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Pastikan jumlah pasien tahunan adalah volume yang relevan.', 'is_active' => true],
                ['code' => 'asset_depreciation_per_day', 'name' => 'Penyusutan aset per hari', 'component_id' => $componentIds['asset_depreciation'], 'expression' => 'asset_acquisition_cost / asset_useful_life_days', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Perlu umur ekonomis; rumus Excel harga perolehan / pasien tahunan / 365 tidak dianggap valid untuk depresiasi tanpa klarifikasi.', 'is_active' => true],
                ['code' => 'ciu_per_day', 'name' => 'CIU per hari', 'component_id' => $componentIds['ciu'], 'expression' => 'annual_ciu_count / days_per_year', 'result_unit' => 'CIU/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Dari Excel: CIU tahunan / hari per tahun.', 'is_active' => true],
                ['code' => 'ciu_per_bed_per_day', 'name' => 'CIU per tempat tidur per hari', 'component_id' => $componentIds['ciu'], 'expression' => 'ciu_per_day / bed_count', 'result_unit' => 'CIU/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Validasi apakah keluaran memang CIU per tempat tidur, bukan per pasien.', 'is_active' => true],
                ['code' => 'electricity_allocated_year', 'name' => 'Pembebanan listrik unit per tahun', 'component_id' => $componentIds['electricity'], 'expression' => 'annual_electricity_cost * electricity_allocation_ratio', 'result_unit' => 'IDR/tahun', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Ganti faktor angka 3 pada Excel dengan rasio alokasi eksplisit.', 'is_active' => true],
                ['code' => 'electricity_allocated_day', 'name' => 'Pembebanan listrik unit per hari', 'component_id' => $componentIds['electricity'], 'expression' => 'electricity_allocated_year / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'electricity_per_bed_day', 'name' => 'Listrik per tempat tidur per hari', 'component_id' => $componentIds['electricity'], 'expression' => 'electricity_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Excel menyebut per pasien tetapi membagi dengan jumlah bed; perlu konfirmasi basisnya.', 'is_active' => true],
                ['code' => 'water_allocated_year', 'name' => 'Pembebanan air unit per tahun', 'component_id' => $componentIds['water'], 'expression' => 'annual_water_cost * water_allocation_ratio', 'result_unit' => 'IDR/tahun', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'water_allocated_day', 'name' => 'Pembebanan air unit per hari', 'component_id' => $componentIds['water'], 'expression' => 'water_allocated_year / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'water_per_bed_day', 'name' => 'Air per tempat tidur per hari', 'component_id' => $componentIds['water'], 'expression' => 'water_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Konfirmasi basis alokasi bed atau pasien.', 'is_active' => true],
                ['code' => 'internet_allocated_year', 'name' => 'Pembebanan internet unit per tahun', 'component_id' => $componentIds['internet'], 'expression' => 'annual_internet_cost * internet_allocation_ratio', 'result_unit' => 'IDR/tahun', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'internet_allocated_day', 'name' => 'Pembebanan internet unit per hari', 'component_id' => $componentIds['internet'], 'expression' => 'internet_allocated_year / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'internet_per_bed_day', 'name' => 'Internet per tempat tidur per hari', 'component_id' => $componentIds['internet'], 'expression' => 'internet_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Konfirmasi basis alokasi bed atau pasien.', 'is_active' => true],
                ['code' => 'building_depreciation_year', 'name' => 'Penyusutan gedung per tahun', 'component_id' => $componentIds['building_depreciation'], 'expression' => 'building_acquisition_value / building_useful_life_years', 'result_unit' => 'IDR/tahun', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Nilai umur ekonomis awal 20 tahun berasal dari Excel dan harus disahkan.', 'is_active' => true],
                ['code' => 'building_depreciation_day', 'name' => 'Penyusutan gedung per hari', 'component_id' => $componentIds['building_depreciation'], 'expression' => 'building_depreciation_year / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'building_depreciation_unit_day', 'name' => 'Penyusutan gedung untuk unit per hari', 'component_id' => $componentIds['building_depreciation'], 'expression' => 'building_depreciation_day * building_allocation_ratio', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'building_depreciation_per_bed_day', 'name' => 'Penyusutan gedung per tempat tidur per hari', 'component_id' => $componentIds['building_depreciation'], 'expression' => 'building_depreciation_unit_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'salary_per_day', 'name' => 'Gaji unit per hari', 'component_id' => $componentIds['salary'], 'expression' => 'annual_unit_salary / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'salary_per_shift', 'name' => 'Gaji unit per shift', 'component_id' => $componentIds['salary'], 'expression' => 'salary_per_day / work_shifts_per_day', 'result_unit' => 'IDR/shift', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'salary_per_bed_per_shift', 'name' => 'Gaji per tempat tidur per shift', 'component_id' => $componentIds['salary'], 'expression' => 'salary_per_shift / bed_count', 'result_unit' => 'IDR/tempat tidur/shift', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Excel memakai /shift/bed; basis pasien perlu dikonfirmasi.', 'is_active' => true],
                ['code' => 'cleaning_cost_day', 'name' => 'Biaya cleaning service per hari', 'component_id' => $componentIds['cleaning_service'], 'expression' => 'annual_cleaning_cost / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'cleaning_allocated_day', 'name' => 'Biaya kebersihan unit per hari', 'component_id' => $componentIds['cleaning_service'], 'expression' => 'cleaning_cost_day * cleaning_allocation_ratio', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'cleaning_per_bed_day', 'name' => 'Biaya kebersihan per tempat tidur per hari', 'component_id' => $componentIds['cleaning_service'], 'expression' => 'cleaning_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'security_cost_day', 'name' => 'Biaya security per hari', 'component_id' => $componentIds['security'], 'expression' => 'annual_security_cost / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'security_allocated_day', 'name' => 'Biaya keamanan unit per hari', 'component_id' => $componentIds['security'], 'expression' => 'security_cost_day * security_allocation_ratio', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'security_per_bed_day', 'name' => 'Biaya keamanan per tempat tidur per hari', 'component_id' => $componentIds['security'], 'expression' => 'security_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'medical_waste_cost_day', 'name' => 'Biaya limbah medis per hari', 'component_id' => $componentIds['medical_waste'], 'expression' => 'annual_medical_waste_cost / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'medical_waste_allocated_day', 'name' => 'Limbah medis unit per hari', 'component_id' => $componentIds['medical_waste'], 'expression' => 'medical_waste_cost_day * medical_waste_allocation_ratio', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'medical_waste_per_bed_day', 'name' => 'Biaya limbah medis per tempat tidur per hari', 'component_id' => $componentIds['medical_waste'], 'expression' => 'medical_waste_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'laundry_cost_day', 'name' => 'Biaya laundry per hari', 'component_id' => $componentIds['laundry'], 'expression' => 'annual_laundry_cost / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'laundry_allocated_day', 'name' => 'Biaya laundry unit per hari', 'component_id' => $componentIds['laundry'], 'expression' => 'laundry_cost_day * laundry_allocation_ratio', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'laundry_per_bed_day', 'name' => 'Biaya laundry per tempat tidur per hari', 'component_id' => $componentIds['laundry'], 'expression' => 'laundry_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'other_overhead_cost_day', 'name' => 'Biaya tak langsung lainnya per hari', 'component_id' => $componentIds['other_overhead'], 'expression' => 'annual_other_overhead / days_per_year', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'other_overhead_allocated_day', 'name' => 'Biaya tak langsung lainnya unit per hari', 'component_id' => $componentIds['other_overhead'], 'expression' => 'other_overhead_cost_day * other_overhead_allocation_ratio', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'other_overhead_per_bed_day', 'name' => 'Biaya tak langsung lainnya per tempat tidur per hari', 'component_id' => $componentIds['other_overhead'], 'expression' => 'other_overhead_allocated_day / bed_count', 'result_unit' => 'IDR/tempat tidur/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => null, 'is_active' => true],
                ['code' => 'meal_cost_per_room_day', 'name' => 'Biaya makan per kamar per hari', 'component_id' => $componentIds['meals'], 'expression' => '(meal_price * meals_per_day) + (snack_price * snacks_per_day)', 'result_unit' => 'IDR/kamar/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Formula mengikuti Excel; volume pemakaian aktual dapat diperlukan.', 'is_active' => true],
                ['code' => 'amenities_cost_per_room', 'name' => 'Biaya perlengkapan kamar', 'component_id' => $componentIds['amenities'], 'expression' => 'amenities_price_per_room', 'result_unit' => 'IDR/kamar', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Perlu dikalikan frekuensi pemakaian jika hasil yang diminta biaya per hari.', 'is_active' => true],
                ['code' => 'persentase_luas_bangunan', 'name' => 'Persentase luas bangunan', 'component_id' => $componentIds['building'], 'expression' => '({luas_bangunan_tindakan} / {luas_bangunan_keseluruhan}) * 100', 'result_unit' => '%', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Contoh formula free-text dengan referensi Global Variable.', 'is_active' => true],
                ['code' => 'biaya_listrik_unit_tahunan', 'name' => 'Biaya listrik unit tahunan', 'component_id' => $componentIds['electricity'], 'expression' => '{biaya_listrik_tahunan} * {persentase_alokasi_listrik} / 100', 'result_unit' => 'IDR/tahun', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Contoh alokasi listrik memakai persentase 0-100.', 'is_active' => true],
                ['code' => 'biaya_listrik_unit_per_hari', 'name' => 'Biaya listrik unit per hari', 'component_id' => $componentIds['electricity'], 'expression' => '{biaya_listrik_unit_tahunan} / {jumlah_hari_per_tahun}', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Contoh formula bertingkat yang memakai hasil formula lain.', 'is_active' => true],
                ['code' => 'penyusutan_gedung_per_hari', 'name' => 'Penyusutan gedung per hari', 'component_id' => $componentIds['building_depreciation'], 'expression' => '{penyusutan_gedung_tahunan} / {jumlah_hari_per_tahun}', 'result_unit' => 'IDR/hari', 'result_scope' => 'unit_period', 'version' => 1, 'source_note' => 'Contoh formula pembagian dengan parameter global.', 'is_active' => true],
            ];

            foreach ($formulas as $row) {
                // Formula baru disimpan dengan referensi {kode_variabel}.
                // Data yang sudah ada tidak ditimpa agar kustomisasi aman.
                $row['expression'] = $this->toBracedExpression((string) $row['expression']);

                DB::table('calculation_formulas')->insertOrIgnore(
                    array_merge($row, ['updated_at' => $now, 'created_at' => $now])
                );
            }
        });
    }

    /**
     * Ubah identifier pada data seed lama menjadi referensi {kode_variabel}.
     * Formula yang sudah memakai kurawal tidak diubah.
     */
    private function toBracedExpression(string $expression): string
    {
        if (str_contains($expression, '{')) {
            return $expression;
        }

        $aliases = [
            'days_per_year' => 'jumlah_hari_per_tahun',
        ];

        $functions = [
            'abs' => true,
            'ceil' => true,
            'floor' => true,
            'sqrt' => true,
            'round' => true,
            'min' => true,
            'max' => true,
        ];

        return (string) preg_replace_callback(
            '/\b[A-Za-z_][A-Za-z0-9_]*\b/',
            static function (array $match) use ($aliases, $functions): string {
                $identifier = $match[0];

                if (array_key_exists($identifier, $functions)) {
                    return $identifier;
                }

                $identifier = $aliases[$identifier] ?? $identifier;

                return '{'.$identifier.'}';
            },
            $expression
        );
    }
}
