# TASK — Alur Unit Cost: Periode → Komponen → Parameter

## Tujuan

Membangun ulang alur Kalkulasi Unit Cost tanpa tombol Hitung. Pengguna memilih
periode, membuka komponen, mengisi nilai atau rumus pada form parameter, lalu
menekan **Simpan**. Hasil formula dihitung otomatis oleh backend dan preview
hanya untuk tampilan.

## Alur Utama

1. **Periode** (`/calculations`)
   - Daftar periode dengan tahun, kode, nama, status, jumlah komponen,
     parameter terkonfigurasi, parameter belum lengkap, dan aksi.
   - CRUD periode, aktivasi, penutupan, dan pencegahan duplikasi kode/tahun+nama.
2. **Komponen** (`/calculations/periods/{period}`)
   - Daftar komponen aktif pada periode.
   - Menampilkan jumlah parameter, parameter terkonfigurasi, belum lengkap,
     error, status kelengkapan, dan waktu perubahan terakhir.
   - Konteks unit dipilih di halaman ini; entry unit disimpan terpisah.
3. **Parameter** (`/calculations/periods/{period}/components/{component}`)
   - Tabel parameter: No., Nama Parameter, Variable Global, Sumber Data, Satuan,
     Metode Input, Nilai/Rumus, Hasil, Status.
   - Metode input: **Input Nilai** atau **Input Rumus**.
   - Satu tombol utama: **Simpan**. Tidak ada tombol Hitung/Calculate.
   - Preview diperbarui otomatis dengan debounce dan endpoint backend.
4. **Master** (`/calculations/master`)
   - CRUD komponen dan parameter master.
   - Setiap parameter memiliki `code` internal dan `variable` global yang unik.
   - Variable global dipakai sebagai referensi `{variable}` pada formula dan
     dapat dipakai lintas komponen.
   - Komponen yang masih memiliki parameter tidak dapat dihapus; parameter yang
     sudah memiliki entry atau riwayat formula juga tidak dapat dihapus.

## Master Seeder

`UnitCostSeeder` mengisi 18 komponen dan 61 parameter aktif:

- 59 parameter bisnis sesuai pengelompokan Bangunan, Pasien, Bed, Penyusutan
  Unit, CIU, Listrik, Air, Internet, Penyusutan Gedung, Gaji, Cleaning,
  Security, Limbah Medis, Laundry, Overhead Lainnya, Amenities, dan Makan.
- 2 variabel konfigurasi: `rasio_alokasi_unit` (mewakili angka 3 pada sumber)
  dan `umur_manfaat_gedung_tahun` (mewakili 20 tahun).

Formula memakai variable global, bukan nomor baris atau angka hardcode:
`{jumlah_hari_setahun}`, `{rasio_alokasi_unit}`, dan
`{umur_manfaat_gedung_tahun}`. Rumus acuan disimpan sebagai versi 1 pada
periode yang masih dapat diedit agar form langsung terisi; entry baru dibuat
saat pengguna menekan **Simpan**.

## Skema Database

Migration: `2026_10_21_100000_create_unit_cost_tables.php`.

| Tabel | Fungsi |
| --- | --- |
| `unit_cost_components` | Master komponen. |
| `unit_cost_parameters` | Master parameter: `code` internal, `variable` global unik, tipe, satuan, scope, sumber, wajib/opsional. |
| `unit_cost_periods` | Periode kalkulasi dengan status `draft`, `active`, `closed`. |
| `unit_cost_formula_versions` | Riwayat versi ekspresi formula per parameter+periode. |
| `unit_cost_parameter_entries` | Konfigurasi periode+parameter+unit: metode, nilai, teks formula, versi formula yang dipakai, hasil, status, error. |
| `unit_cost_result_snapshots` | Snapshot resmi saat approval; menyimpan payload nilai, rumus, versi, dan hasil. |

`unit_cost_parameter_entries.scope_key` unik untuk kombinasi
`periode + parameter + unit` dan menangani `unit_id` NULL tanpa masalah unique
constraint.

## Aturan Formula

- Referensi memakai `{variable}` milik parameter, bukan kode internal.
- Operator: `+ - * /`, tanda kurung, konstanta.
- Fungsi: `abs`, `ceil`, `floor`, `sqrt`, `round`, `min`, `max`.
- Tidak ada `eval()`; parser hanya menerima token yang diizinkan.
- Referensi tidak dikenal, siklus, pembagian nol, dan hasil NaN/Infinity
  menghasilkan status **invalid**.
- Nilai kosong tidak dianggap nol. Formula yang bergantung pada input kosong
  berstatus **belum lengkap**.
- Formula invalid tetap disimpan teksnya, hasil lama dikosongkan, dan error
  ditampilkan di bawah field.

## Status

| Status | Arti |
| --- | --- |
| `valid` | Input/formula valid dan hasil tersedia. |
| `incomplete` | Input wajib belum diisi atau dependensi belum lengkap. |
| `invalid` | Sintaks formula salah, referensi tidak dikenal, siklus, pembagian nol, atau bergantung pada formula invalid. |

## Versioning dan Snapshot

- Saat teks formula berubah, dibuat versi baru di `unit_cost_formula_versions`.
- Entry menyimpan `formula_version_id` yang dipakai.
- Beralih ke Input Nilai mengosongkan `formula_id`/`formula_text`/versi pada
  entry, tetapi riwayat formula tetap ada.
- Approval membuat `unit_cost_result_snapshots` berisi ekspresi, versi, nilai,
  dan hasil. Snapshot tidak diubah ketika entry periode berubah setelahnya.
- Approval ditolak jika masih ada parameter aktif yang belum valid.

## Permission

| Permission | Fungsi |
| --- | --- |
| `manage_unit_cost_periods` | Kelola periode. |
| `manage_unit_cost_masters` | CRUD master komponen/parameter dan variable global. |
| `edit_unit_cost_parameters` | Ubah nilai/rumus parameter. |
| `view_unit_cost_results` | Lihat form, hasil, dan riwayat. |
| `approve_unit_cost_results` | Setujui hasil dan buat snapshot. |
| `manage_calculations` | Kompatibilitas role lama. |
| `approve_calculations` | Kompatibilitas approval lama. |

Middleware `permission` mendukung beberapa permission dengan pemisah `|`.
Validasi permission dilakukan di backend, bukan hanya menyembunyikan tombol.

## Optimasi

- Eager loading komponen/parameter/formula version.
- Agregasi status per periode dan komponen.
- Indeks pada periode, parameter, entry, dan formula version.
- Saat Simpan, hanya parameter yang berubah dan dependen transitifnya yang
  dievaluasi ulang; formula lain memakai hasil tersimpan.
- Preview tidak menulis entry, versi formula, atau snapshot.

## Migration dan Deployment

- Migration lama calculation engine (`2026_10_21_000001`–`000003`) dihapus dari
  source karena alur dibangun ulang.
- Migration baru: `2026_10_21_100000_create_unit_cost_tables.php`,
  `2026_10_21_100001_add_unit_cost_permissions.php`, dan
  `2026_10_21_100002_add_variable_to_unit_cost_parameters.php` (menambah
  `variable` unik dan mengisi nilainya dari `code` untuk data lama).
- Tabel lama yang sudah terlanjur ada di database lokal tidak dipakai lagi dan
  tidak dihapus otomatis untuk menghindari kehilangan data.
- **Migration/deployment production belum dijalankan dan menunggu otorisasi.**

## Test

```bash
php artisan test tests/Feature/UnitCostPeriodTest.php
php artisan test tests/Feature/UnitCostParameterTest.php
php artisan test tests/Feature/UnitCostMasterTest.php
php artisan test tests/Unit/UnitCost/FormulaParserTest.php
php artisan test --filter=UnitCost
```

Cakupan test: CRUD/aktivasi/penutupan periode, CRUD komponen/parameter master,
variable global unik, formula memakai variable (bukan kode internal), duplikasi,
permission, form parameter, input nilai, formula bertingkat, formula invalid,
referensi tidak dikenal, siklus, pembagian nol, input kurang, isolasi
periode/unit, perubahan metode, preview tanpa persist, evaluasi terdampak,
rollback transaksi, snapshot approval, dan tidak adanya route/tombol Hitung.

## Keputusan Bisnis yang Masih Terbuka

- Definisi denominator unit cost (bed vs pasien vs tindakan).
- Rasio alokasi dan definisi `total_unit_cost`.
- Granularitas periode dan multi-RS.
- Aturan approval final per unit/periode.
