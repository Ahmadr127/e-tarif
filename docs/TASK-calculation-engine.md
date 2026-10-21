# TASK: Implementasi Calculation Engine Unit Cost

Status keseluruhan: **BELUM DIKERJAKAN**

Terakhir diupdate: 2026-10-21

## Ringkasan

Mengimplementasikan rancangan calculation engine unit cost yang saat ini masih
berupa starter di `calculation_engine_laravel/` ke dalam aplikasi `e-tarif`.
Task ini mencakup penyelesaian skema, model, formula engine, alur kalkulasi,
penyimpanan snapshot audit, UI, pengujian, dan README final.

Rancangan sumber:

- `calculation_engine_laravel/README.md`
- `calculation_engine_laravel/database/migrations/2026_10_21_000001_create_unit_cost_calculation_tables.php`
- `calculation_engine_laravel/database/seeders/CalculationSeeder.php`

Prinsip utama: **jangan menjalankan formula dengan PHP `eval()`**. Formula
harus dievaluasi oleh parser expression dengan allowlist operator, fungsi, dan
nama variabel.

## Tujuan

1. Admin dapat mengelola komponen biaya, variabel, dan formula per unit/periode.
2. Sistem dapat menjalankan kalkulasi secara deterministik dan dapat diaudit.
3. Setiap hasil menyimpan snapshot input, versi formula, dan sumber data.
4. Input yang hilang, nol, atau tidak valid menghasilkan error yang jelas.
5. Perubahan formula tidak mengubah hasil kalkulasi historis.
6. README berisi cara pasang, arsitektur, formula DSL, alur pemakaian, asumsi,
   dan batasan.

## Di Luar Scope

- Integrasi otomatis penuh ke sistem GA, RM, SDM, dan Gizi pada tahap awal.
  Integrasi dapat dimulai dari import manual atau adapter.
- Menetapkan angka alokasi listrik/air/internet/overhead tanpa persetujuan
  pemilik unit cost.
- Menentukan satu `total_unit_cost` final jika definisi bisnisnya belum
  disepakati.
- Mengubah arsitektur, permission, dan fitur e-tarif yang tidak berkaitan.
- Menjalankan migration di production tanpa otorisasi eksplisit.

## Keputusan yang Harus Dikonfirmasi Sebelum Implementasi

| # | Pertanyaan | Dampak | Status |
|---|------------|--------|--------|
| 1 | Denominator beberapa formula memakai `bed_count` atau jumlah pasien? | Hasil per-bed/per-pasien | Menunggu konfirmasi |
| 2 | Nilai rasio alokasi listrik, air, internet, dan overhead | Hasil pembebanan unit | Menunggu konfirmasi |
| 3 | Komponen apa saja yang dijumlahkan menjadi `total_unit_cost`? | Definisi output final | Menunggu konfirmasi |
| 4 | Periode kalkulasi hanya tahunan atau perlu bulan/tanggal? | Skema run dan validasi | Menunggu konfirmasi |
| 5 | `unit_code` memakai kode unit organisasi, ID, atau kode eksternal? | Relasi dan integrasi | Menunggu konfirmasi |
| 6 | Apakah aplikasi perlu tenant/organization scoping multi-RS? | Skema dan authorization | Menunggu konfirmasi |
| 7 | Siapa yang boleh menjalankan, mengubah, dan menyetujui kalkulasi? | Permission dan workflow | Menunggu konfirmasi |

## Daftar Task

| # | Task | Status | Catatan |
|---|------|--------|---------|
| 1 | Audit rancangan starter dan struktur aplikasi e-tarif | TODO | Pastikan tidak ada duplikasi model/tabel |
| 2 | Finalisasi keputusan bisnis dan definisi output | BLOCKED | Lihat tabel keputusan di atas |
| 3 | Adaptasi migration ke aplikasi utama | TODO | Cek foreign key `users`, index, dan konvensi migration |
| 4 | Adaptasi seeder komponen, variabel, dan formula | TODO | Idempotent, tanpa nilai alokasi asumsi |
| 5 | Buat model dan relasi calculation | TODO | Component, Variable, Formula, Run, Detail |
| 6 | Buat formula DSL parser dan validator | TODO | Allowlist operator/fungsi, tanpa `eval()` |
| 7 | Buat dependency resolver dan deteksi siklus | TODO | Urutkan formula berdasarkan dependensi |
| 8 | Buat resolver input variabel per scope | TODO | Global, unit, period, unit_period |
| 9 | Buat validasi input wajib, nol, dan tipe data | TODO | Error harus menyebut kode variabel |
| 10 | Buat calculation service/orchestrator | TODO | Jalankan formula, simpan hasil, hentikan jika gagal |
| 11 | Simpan snapshot input, formula, dan hasil | TODO | Audit trail immutable per run |
| 12 | Buat workflow status run | TODO | draft, calculated, approved, failed |
| 13 | Tambahkan permission dan route | TODO | Ikuti pola permission e-tarif |
| 14 | Buat UI master komponen/variabel/formula | TODO | CRUD, validasi, preview formula |
| 15 | Buat UI run kalkulasi dan detail hasil | TODO | Pilih unit/periode, tampilkan error dan hasil |
| 16 | Buat export hasil kalkulasi | TODO | XLSX/PDF bila dibutuhkan |
| 17 | Buat adapter/import data eksternal | TODO | Minimal manual/import; integrasi bertahap |
| 18 | Tambahkan feature test dan unit test | TODO | Termasuk parser, siklus, input kosong, snapshot |
| 19 | Uji regresi halaman dan permission terkait | TODO | Tidak mengganggu modul tarif |
| 20 | Finalisasi README calculation engine | TODO | Lihat kerangka README di bawah |
| 21 | Dokumentasikan prosedur deploy/rollback | TODO | Migration, seeder, cache, backup |

## Skema Awal dari Rancangan

Tabel yang sudah dirancang:

- `cost_components`: master komponen biaya.
- `calculation_variables`: parameter global/unit/periode dan input eksternal.
- `calculation_formulas`: formula DSL, versi, dan masa berlaku.
- `calculation_runs`: satu proses kalkulasi per unit/periode.
- `calculation_details`: snapshot input, formula, hasil, dan sumber.

Hal yang perlu ditinjau saat adaptasi:

- `calculation_runs.unit_code` sebaiknya dipetakan ke unit organisasi yang
  sudah ada, atau dijelaskan sebagai kode eksternal.
- `created_by` bergantung pada tabel `users`; pastikan migration berjalan
  setelah tabel tersebut.
- `total_unit_cost` tetap nullable sampai definisi penjumlahan final disetujui.
- Index dan foreign key disesuaikan dengan pola database e-tarif.
- Pertimbangkan tenant/organization scoping jika aplikasi dipakai multi-RS.

## Aturan Formula DSL

Wajib:

- Simpan expression sebagai string, bukan kode PHP.
- Gunakan parser dengan allowlist:
  - operator aritmetika dasar;
  - tanda kurung;
  - fungsi matematika yang disetujui;
  - nama variabel yang terdaftar.
- Tolak pemanggilan fungsi, akses properti, dan karakter yang tidak dikenal.
- Deteksi pembagian dengan nol dan input wajib yang kosong.
- Catat versi formula yang dipakai pada setiap run.
- Jangan menimpa formula historis; buat versi baru jika berubah.

## Struktur Implementasi yang Diusulkan

```text
app/Models/
  CostComponent.php
  CalculationVariable.php
  CalculationFormula.php
  CalculationRun.php
  CalculationDetail.php

app/Services/Calculation/
  FormulaParser.php
  FormulaValidator.php
  DependencyResolver.php
  VariableResolver.php
  CalculationService.php
  CalculationSnapshotService.php

app/Http/Controllers/
  CostComponentController.php
  CalculationVariableController.php
  CalculationFormulaController.php
  CalculationRunController.php

app/Http/Requests/Calculation/
resources/views/calculation/
tests/Feature/Calculation/
tests/Unit/Calculation/
```

Nama dan lokasi final dapat disesuaikan dengan konvensi e-tarif yang sudah ada.

## Acceptance Criteria

- [ ] Migration berjalan bersih pada database kosong dan database existing.
- [ ] Seeder idempotent dan tidak mengisi rasio alokasi dengan asumsi.
- [ ] Formula dari seeder dapat diparse dan divalidasi.
- [ ] Tidak ada penggunaan `eval()` atau eksekusi kode dinamis.
- [ ] Siklus dependensi terdeteksi dengan pesan yang jelas.
- [ ] Variabel wajib yang kosong menggagalkan run dengan daftar kode variabel.
- [ ] Pembagian dengan nol ditangani tanpa error 500.
- [ ] Run menyimpan snapshot input, versi formula, dan hasil.
- [ ] Perubahan formula setelah run tidak mengubah detail run lama.
- [ ] UI menampilkan status run dan detail error.
- [ ] Permission mencegah user tanpa izin menjalankan/mengubah kalkulasi.
- [ ] Feature test dan unit test lulus.
- [ ] README final tersedia dan sesuai implementasi.

## Kerangka README Final

README di `calculation_engine_laravel/README.md` (atau README modul di aplikasi
utama) minimal memuat:

1. Ringkasan calculation engine dan batasannya.
2. Kebutuhan sistem dan instalasi.
3. Struktur tabel dan relasi.
4. Daftar komponen, variabel, dan formula awal.
5. Penjelasan formula DSL dan contoh ekspresi.
6. Alur kalkulasi dari input sampai approval.
7. Aturan validasi, pembulatan, dan penyimpanan snapshot.
8. Permission dan peran pengguna.
9. Cara menjalankan test.
10. Asumsi dan pertanyaan bisnis yang masih terbuka.
11. Prosedur deploy, migration, seeder, dan rollback.

## Catatan / Risiko

- Rancangan awal berasal dari Excel dan masih mengandung asumsi denominator
  bed vs pasien.
- Nilai rasio alokasi sengaja null; jangan diisi 0,03 atau 3 tanpa konfirmasi.
- Depresiasi aset agregat kurang akurat dibanding per aset.
- `total_unit_cost` belum memiliki definisi final.
- Integrasi eksternal memerlukan pemetaan kode unit dan periode yang jelas.
- Jangan menjalankan migration production sebelum keputusan bisnis dan
  persetujuan deployment.
