<?php

namespace Database\Seeders;

use App\Models\UnitCostComponent;
use App\Models\UnitCostParameter;
use Illuminate\Database\Seeder;

class UnitCostSeeder extends Seeder
{
    /**
     * Master komponen dan parameter unit cost.
     *
     * Seeder ini hanya mengisi definisi master. Nilai dan rumus tetap disimpan
     * per periode/unit pada form parameter, sehingga tidak ada dua sumber
     * kebenaran untuk hasil kalkulasi.
     */
    public function run(): void
    {
        $components = [
            ['code' => 'SDM', 'name' => 'Sumber Daya Manusia', 'category' => 'Biaya', 'sort_order' => 10],
            ['code' => 'ASET', 'name' => 'Aset dan Penyusutan', 'category' => 'Biaya', 'sort_order' => 20],
            ['code' => 'OVERHEAD', 'name' => 'Overhead', 'category' => 'Biaya', 'sort_order' => 30],
            ['code' => 'LANGSUNG', 'name' => 'Biaya Langsung', 'category' => 'Biaya', 'sort_order' => 40],
            ['code' => 'VOLUME', 'name' => 'Volume Layanan', 'category' => 'Volume', 'sort_order' => 50],
            ['code' => 'HASIL', 'name' => 'Hasil Kalkulasi', 'category' => 'Hasil', 'sort_order' => 60],
        ];

        $componentIds = [];

        foreach ($components as $component) {
            $model = UnitCostComponent::query()->updateOrCreate(
                ['code' => $component['code']],
                array_merge($component, ['is_active' => true])
            );

            $componentIds[$component['code']] = $model->id;
        }

        $parameters = [
            // Konteks global.
            [
                'code' => 'luas_bangunan_keseluruhan',
                'name' => 'Luas Bangunan Keseluruhan',
                'component' => null,
                'value_type' => 'decimal',
                'unit' => 'm2',
                'scope' => 'global',
                'source_type' => 'manual',
                'source_name' => 'Data Sarana',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'luas_bangunan_tindakan',
                'name' => 'Luas Bangunan Area Tindakan',
                'component' => null,
                'value_type' => 'decimal',
                'unit' => 'm2',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'Data Sarana',
                'is_required' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'jumlah_pasien',
                'name' => 'Jumlah Pasien',
                'component' => 'VOLUME',
                'value_type' => 'integer',
                'unit' => 'pasien',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'SIMRS',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'jumlah_tindakan',
                'name' => 'Jumlah Tindakan',
                'component' => 'VOLUME',
                'value_type' => 'integer',
                'unit' => 'tindakan',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'SIMRS',
                'is_required' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'biaya_sdm_total',
                'name' => 'Total Biaya SDM',
                'component' => 'SDM',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'Keuangan',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'biaya_aset_total',
                'name' => 'Total Biaya Aset',
                'component' => 'ASET',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'Keuangan',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'biaya_overhead_total',
                'name' => 'Total Biaya Overhead',
                'component' => 'OVERHEAD',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'Keuangan',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'biaya_langsung_total',
                'name' => 'Total Biaya Langsung',
                'component' => 'LANGSUNG',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'Keuangan',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'persentase_luas',
                'name' => 'Persentase Luas Area Tindakan',
                'component' => 'HASIL',
                'value_type' => 'decimal',
                'unit' => 'rasio',
                'scope' => 'unit_period',
                'source_type' => 'derived',
                'source_name' => 'Formula',
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'alokasi_sdm',
                'name' => 'Alokasi Biaya SDM',
                'component' => 'HASIL',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'derived',
                'source_name' => 'Formula',
                'is_required' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'alokasi_aset',
                'name' => 'Alokasi Biaya Aset',
                'component' => 'HASIL',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'derived',
                'source_name' => 'Formula',
                'is_required' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'alokasi_overhead',
                'name' => 'Alokasi Biaya Overhead',
                'component' => 'HASIL',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'derived',
                'source_name' => 'Formula',
                'is_required' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'total_biaya',
                'name' => 'Total Biaya Tindakan',
                'component' => 'HASIL',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'derived',
                'source_name' => 'Formula',
                'is_required' => true,
                'sort_order' => 50,
            ],
            [
                'code' => 'biaya_per_pasien',
                'name' => 'Biaya per Pasien',
                'component' => 'HASIL',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'derived',
                'source_name' => 'Formula',
                'is_required' => true,
                'sort_order' => 60,
            ],
            [
                'code' => 'biaya_per_tindakan',
                'name' => 'Biaya per Tindakan',
                'component' => 'HASIL',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'derived',
                'source_name' => 'Formula',
                'is_required' => true,
                'sort_order' => 70,
            ],
        ];

        foreach ($parameters as $parameter) {
            $componentCode = $parameter['component'];
            unset($parameter['component']);
            $parameter['variable'] = $parameter['variable'] ?? $parameter['code'];

            UnitCostParameter::query()->updateOrCreate(
                ['code' => $parameter['code']],
                array_merge($parameter, [
                    'component_id' => $componentCode === null ? null : ($componentIds[$componentCode] ?? null),
                    'is_active' => true,
                ])
            );
        }
    }
}
