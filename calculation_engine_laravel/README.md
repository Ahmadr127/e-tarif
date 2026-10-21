# Unit Cost Calculation Engine — Laravel Starter

Dokumen ini adalah rancangan teknis dan panduan awal untuk calculation engine
unit cost rumah sakit. Isi folder ini masih berupa **starter**: migration,
seeder, dan rancangan alur. Parser formula, service kalkulasi, UI, dan
integrasi data belum diimplementasikan di folder ini.

Task implementasi lengkap ada di:

- `../docs/TASK-calculation-engine.md`

## Status

| Bagian | Status |
|---|---|
| Skema database | Tersedia sebagai migration starter |
| Komponen, variabel, formula awal | Tersedia sebagai seeder |
| Formula parser/evaluator | Belum diimplementasikan |
| Dependency resolver | Belum diimplementasikan |
| Calculation service | Belum diimplementasikan |
| UI master data dan run | Belum diimplementasikan |
| Integrasi GA/RM/SDM/Gizi | Belum diimplementasikan |
| Export hasil | Belum diimplementasikan |
| Test | Belum tersedia di folder starter |

## Tujuan

Calculation engine ini dirancang untuk:

1. Menyimpan komponen biaya, variabel input, dan formula secara terstruktur.
2. Menghitung unit cost per unit organisasi dan periode.
3. Menjaga hasil kalkulasi historis tetap dapat diaudit.
4. Memisahkan input manual, input integrasi, dan nilai turunan formula.
5. Menghindari eksekusi kode dinamis yang tidak aman.

## Isi Folder

```text
calculation_engine_laravel/
├── README.md
└── database/
    ├── migrations/
    │   └── 2026_10_21_000001_create_unit_cost_calculation_tables.php
    └── seeders/
        └── CalculationSeeder.php
```

## Kebutuhan

- PHP 8.2+
- Laravel 11/12
- Database yang mendukung `json` dan foreign key
- Tabel `users` sudah ada jika `calculation_runs.created_by` digunakan

## Instalasi

1. Salin migration ke folder migration aplikasi.
2. Salin seeder ke folder seeder aplikasi.
3. Pastikan tabel `users` sudah ada.
4. Jalankan migration dan seeder:

```bash
php artisan migrate
php artisan db:seed --class=CalculationSeeder
```

Seeder bersifat idempotent menggunakan `updateOrInsert`, sehingga aman
dijalankan ulang untuk memperbarui master awal.

## Struktur Tabel

### `cost_components`

Master komponen biaya.

Kolom penting:

- `code` — kode unik, misalnya `electricity`
- `name` — nama tampilan
- `category` — `building`, `volume`, `capacity`, `asset`, `utility`,
  `personnel`, `overhead`, `consumable`
- `description`
- `sort_order`
- `is_active`

### `calculation_variables`

Parameter input dan nilai turunan.

Kolom penting:

- `code` — kode unik variabel
- `value_type` — `decimal`, `integer`, `boolean`, `string`
- `default_value`
- `unit`
- `scope` — `global`, `unit`, `period`, `unit_period`
- `source_type` — `global`, `manual`, `integration`, `derived`
- `source_name` — misalnya `GA`, `RM/Target RS`, `SDM`, `Gizi`
- `is_required`
- `is_active`

### `calculation_formulas`

Definisi formula dan versinya.

Kolom penting:

- `code` — kode unik formula
- `expression` — ekspresi DSL, bukan kode PHP
- `result_unit`
- `result_scope`
- `version`
- `effective_from`, `effective_until`
- `source_note`
- `validation_notes`
- `is_active`

### `calculation_runs`

Satu proses kalkulasi untuk unit/periode.

Kolom penting:

- `run_code` — kode unik run
- `unit_code` — unit yang dihitung
- `period_year`
- `status` — `draft`, `calculated`, `approved`, `failed`
- `formula_version`
- `parameter_snapshot`
- `total_unit_cost`
- `created_by`
- `calculated_at`
- `notes`

### `calculation_details`

Snapshot detail per komponen/formula pada satu run.

Kolom penting:

- `calculation_run_id`
- `code`
- `name`
- `detail_type` — `input`, `formula`, `allocation`
- `input_snapshot`
- `expression`
- `result_value`
- `result_unit`
- `source_name`
- `notes`

## Komponen Awal

Seeder menyediakan komponen:

| Code | Nama | Kategori |
|---|---|---|
| `building` | Bangunan | building |
| `patients` | Pasien | volume |
| `beds` | Bed Pasien | capacity |
| `asset_depreciation` | Penyusutan Unit | asset |
| `ciu` | CIU Unit | volume |
| `electricity` | Listrik | utility |
| `water` | Air | utility |
| `internet` | Internet | utility |
| `building_depreciation` | Penyusutan Gedung Rumah Sakit | asset |
| `salary` | Gaji Pegawai | personnel |
| `cleaning_service` | Overhead Cleaning Service | overhead |
| `security` | Overhead Security | overhead |
| `medical_waste` | Overhead Limbah Medis | overhead |
| `laundry` | Overhead Laundry | overhead |
| `other_overhead` | Overhead Lainnya | overhead |
| `amenities` | Amenities | consumable |
| `meals` | Makan | consumable |

## Variabel Awal

Beberapa variabel global memiliki nilai awal:

| Code | Nilai awal | Catatan |
|---|---:|---|
| `days_per_year` | 365 | Dapat disesuaikan dengan kebijakan periode |
| `building_useful_life_years` | 20 | Berasal dari referensi Excel; perlu pengesahan |
| `currency_precision` | 2 | Hanya untuk tampilan |

Variabel lain sengaja `null` karena harus diisi dari unit, periode, atau
integrasi. Nilai rasio alokasi **tidak boleh** diisi dengan asumsi `0,03` atau
`3`.

Contoh variabel input:

- `building_area_allocated`, `building_area_total` — GA
- `annual_patient_count` — RM/target RS
- `bed_count` — GA
- `annual_unit_salary` — SDM
- `meal_price`, `snack_price` — Gizi
- `electricity_allocation_ratio`, `water_allocation_ratio`,
  `internet_allocation_ratio` — manual, rentang `0..1`

## Formula Awal

Formula disimpan sebagai ekspresi DSL. Berikut daftar formula dari seeder:

| Code | Expression |
|---|---|
| `building_allocation_ratio` | `building_area_allocated / building_area_total` |
| `average_patients_per_day` | `annual_patient_count / days_per_year` |
| `asset_depreciation_per_day` | `asset_acquisition_cost / asset_useful_life_days` |
| `ciu_per_day` | `annual_ciu_count / days_per_year` |
| `ciu_per_bed_per_day` | `ciu_per_day / bed_count` |
| `electricity_allocated_year` | `annual_electricity_cost * electricity_allocation_ratio` |
| `electricity_allocated_day` | `electricity_allocated_year / days_per_year` |
| `electricity_per_bed_day` | `electricity_allocated_day / bed_count` |
| `water_allocated_year` | `annual_water_cost * water_allocation_ratio` |
| `water_allocated_day` | `water_allocated_year / days_per_year` |
| `water_per_bed_day` | `water_allocated_day / bed_count` |
| `internet_allocated_year` | `annual_internet_cost * internet_allocation_ratio` |
| `internet_allocated_day` | `internet_allocated_year / days_per_year` |
| `internet_per_bed_day` | `internet_allocated_day / bed_count` |
| `building_depreciation_year` | `building_acquisition_value / building_useful_life_years` |
| `building_depreciation_day` | `building_depreciation_year / days_per_year` |
| `building_depreciation_unit_day` | `building_depreciation_day * building_allocation_ratio` |
| `building_depreciation_per_bed_day` | `building_depreciation_unit_day / bed_count` |
| `salary_per_day` | `annual_unit_salary / days_per_year` |
| `salary_per_shift` | `salary_per_day / work_shifts_per_day` |
| `salary_per_bed_per_shift` | `salary_per_shift / bed_count` |
| `cleaning_cost_day` | `annual_cleaning_cost / days_per_year` |
| `cleaning_allocated_day` | `cleaning_cost_day * cleaning_allocation_ratio` |
| `cleaning_per_bed_day` | `cleaning_allocated_day / bed_count` |
| `security_cost_day` | `annual_security_cost / days_per_year` |
| `security_allocated_day` | `security_cost_day * security_allocation_ratio` |
| `security_per_bed_day` | `security_allocated_day / bed_count` |
| `medical_waste_cost_day` | `annual_medical_waste_cost / days_per_year` |
| `medical_waste_allocated_day` | `medical_waste_cost_day * medical_waste_allocation_ratio` |
| `medical_waste_per_bed_day` | `medical_waste_allocated_day / bed_count` |
| `laundry_cost_day` | `annual_laundry_cost / days_per_year` |
| `laundry_allocated_day` | `laundry_cost_day * laundry_allocation_ratio` |
| `laundry_per_bed_day` | `laundry_allocated_day / bed_count` |
| `other_overhead_cost_day` | `annual_other_overhead / days_per_year` |
| `other_overhead_allocated_day` | `other_overhead_cost_day * other_overhead_allocation_ratio` |
| `other_overhead_per_bed_day` | `other_overhead_allocated_day / bed_count` |
| `meal_cost_per_room_day` | `(meal_price * meals_per_day) + (snack_price * snacks_per_day)` |
| `amenities_cost_per_room` | `amenities_price_per_room` |

Catatan penting:

- Formula saling bergantung. Kalkulasi harus mengurutkan formula berdasarkan
  dependensi, bukan urutan baris database.
- `building_allocation_ratio` ada sebagai variabel `derived` sekaligus formula.
  Resolver harus memakai hasil formula tersebut saat menghitung, bukan meminta
  input manual.
- Beberapa label Excel menyebut “per pasien”, tetapi ekspresinya membagi dengan
  `bed_count`. Basis denominator masih perlu dikonfirmasi.

## Aturan Formula DSL

Formula **wajib** dievaluasi dengan parser yang aman:

- Jangan gunakan PHP `eval()`, `create_function`, atau eksekusi kode dinamis.
- Gunakan allowlist:
  - operator `+`, `-`, `*`, `/`, tanda kurung;
  - fungsi matematika yang disetujui;
  - nama variabel/formula yang terdaftar.
- Tolak akses properti, pemanggilan fungsi sewenang-wenang, dan karakter asing.
- Validasi seluruh referensi variabel sebelum run dijalankan.
- Deteksi siklus dependensi.
- Tangani pembagian dengan nol sebagai error kalkulasi yang jelas.
- Jangan mengubah versi formula yang sudah dipakai run historis.

## Alur Kalkulasi yang Direncanakan

1. Pilih unit, periode, dan versi formula.
2. Kumpulkan nilai variabel dari:
   - input manual;
   - data global;
   - integrasi/import;
   - formula turunan.
3. Validasi variabel wajib, tipe data, dan rentang nilai.
4. Susun dependency graph formula.
5. Deteksi siklus dan formula yang tidak dapat diselesaikan.
6. Evaluasi formula dalam urutan topologis.
7. Simpan snapshot input, ekspresi, hasil, dan sumber ke
   `calculation_details`.
8. Simpan status run dan total yang sudah disepakati.
9. Tampilkan error per kode variabel/formula jika gagal.

## Validasi dan Error

Minimal error yang harus ditangani:

- variabel wajib kosong;
- nilai bukan angka;
- pembagian dengan nol;
- referensi variabel tidak dikenal;
- formula tidak aktif;
- formula siklus;
- unit/periode tidak valid;
- input rasio di luar `0..1`;
- konflik nilai manual vs nilai integrasi.

Error harus menyebut kode variabel/formula agar dapat ditelusuri.

## Snapshot dan Audit

`calculation_runs` dan `calculation_details` adalah sumber audit:

- Run historis tidak boleh dihitung ulang secara diam-diam.
- Perubahan formula harus membuat versi baru.
- Input yang dipakai harus disimpan pada `parameter_snapshot` dan/atau
  `calculation_details`.
- `created_by` dan `calculated_at` harus terisi.
- Status `approved` hanya diberikan setelah hasil diperiksa.

## Scope dan Unit

Schema awal memakai `calculation_runs.unit_code`. Sebelum implementasi,
tentukan apakah nilainya:

- ID unit organisasi,
- kode unit organisasi,
- atau kode unit eksternal.

Jika aplikasi mengelola lebih dari satu rumah sakit, pertimbangkan
tenant/organization scoping.

## Asumsi dan Pertanyaan Terbuka

1. Apakah biaya per pasien menggunakan jumlah pasien atau `bed_count`?
2. Berapa nilai rasio alokasi listrik, air, internet, dan overhead?
3. Komponen apa saja yang dijumlahkan menjadi `total_unit_cost`?
4. Apakah periode hanya tahunan atau perlu bulan/tanggal?
5. Apakah formula boleh berlaku berbeda per unit?
6. Apakah perlu approval workflow terpisah?
7. Apakah perlu multi-tenant/multi-RS?
8. Apakah integrasi eksternal memakai API, import file, atau input manual?
9. Apakah depresiasi aset dihitung agregat atau per aset?
10. Siapa pemilik keputusan nilai dan formula?

`total_unit_cost` sengaja nullable sampai definisi output final disepakati.

## Keamanan

- Formula diperlakukan sebagai data tidak tepercaya.
- Parser dan validator harus menjadi satu-satunya jalur evaluasi.
- Batasi ukuran ekspresi dan kedalaman dependensi.
- Jangan menampilkan stack trace atau ekspresi mentah ke pengguna biasa.
- Gunakan permission untuk mengubah formula dan menyetujui hasil.

## Testing yang Dibutuhkan

- Unit test parser: operator, tanda kurung, variabel, fungsi, input invalid.
- Unit test dependency resolver: urutan, siklus, formula hilang.
- Feature test validasi input wajib dan pembagian dengan nol.
- Feature test snapshot run dan immutability.
- Feature test permission.
- Feature test UI master data dan run.
- Regression test modul tarif yang sudah ada.

## Deployment dan Rollback

1. Backup database.
2. Jalankan migration setelah keputusan skema final.
3. Jalankan seeder untuk master awal.
4. Clear cache aplikasi.
5. Uji satu unit/periode dengan data contoh.
6. Verifikasi snapshot dan hasil.
7. Rollback migration hanya setelah memastikan data aman.

Migration production tidak boleh dijalankan tanpa persetujuan eksplisit.

## Referensi Task

Rincian pekerjaan, acceptance criteria, dan urutan implementasi ada di:

- `../docs/TASK-calculation-engine.md`
