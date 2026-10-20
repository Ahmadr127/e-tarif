# TASK: Refactor Buku Tarif Unit Berbasis Prefix Service

Status keseluruhan: **SELESAI**

Terakhir diupdate: 2026-10-20

## Ringkasan

Mengganti sistem assignment tarif individual (`tarif_unit_mappings`) dengan
pencocokan otomatis prefix `services.code` per unit organisasi
(`tarif_unit_service_prefixes`), sekaligus optimasi query dan rendering
halaman buku tarif. Arsitektur, permission, UI, dan fitur lain dipertahankan.

## Daftar Task

| # | Task | Status | Catatan |
|---|------|--------|---------|
| 1 | Audit dependensi `TarifUnitMapping` dan skema DB | ✅ Selesai | 207.059 tarif, 10.789 service, mapping lama 0 baris |
| 2 | Ukur baseline performa | ✅ Selesai | 25 baris tarif = 48-56 ms; 5 query |
| 3 | Migration `tarif_unit_service_prefixes` | ✅ Selesai | `2026_10_20_000001_...` |
| 4 | Migration hapus `tarif_unit_mappings` + backup JSON | ✅ Selesai | Backup `storage/app/backups/tarif_unit_mappings-20261020-024746.json` |
| 5 | Model `TarifUnitServicePrefix` + relasi model | ✅ Selesai | `OrganizationUnit::servicePrefixes()`, scope `Tarif::forServiceIds()` |
| 6 | Service CRUD prefix + authorization | ✅ Selesai | Activity log, unique per unit, validasi karakter |
| 7 | Query buku tarif berbasis prefix | ✅ Selesai | Resolusi prefix -> service_id, filter via `TarifService::applyFilters` |
| 8 | Refactor controller + routes | ✅ Selesai | `TarifUnitBookController`, `TarifUnitServicePrefixController` |
| 9 | Refactor views + hapus UI assignment lama | ✅ Selesai | index/unit/show/pdf/prefixes |
| 10 | Hapus model/export/request/service mapping lama | ✅ Selesai | Tidak ada referensi aktif tersisa |
| 11 | Automated test | ✅ Selesai | 36 test baru, 139 assertion |
| 12 | Jalankan migration, Pint, test suite | ✅ Selesai | Full suite: 114 pass, 5 gagal pre-existing |
| 13 | Ukur performa setelah refactor | ✅ Selesai | 65 ms / 7 query (constant) |
| 14 | Laporan akhir + prosedur deploy/rollback | ✅ Selesai | Lihat bagian akhir |

## Skema Baru

`tarif_unit_service_prefixes`:

- `id`
- `organization_unit_id` (FK cascade delete/update)
- `prefix` varchar(50)
- `is_active` boolean default true
- `notes` text nullable
- `timestamps`
- unique `(organization_unit_id, prefix)`
- index `(organization_unit_id, is_active)`

## Aturan Pencocokan

- Hanya `services.code`, dari awal kode (`PREFIX%`), bukan substring.
- Prefix aktif saja; unit induk tidak mewariskan prefix.
- Tanpa prefix aktif => hasil kosong (tidak pernah menampilkan semua tarif).
- Prefix dinormalisasi uppercase + trim; spasi/wildcard ditolak.
- LIKE memakai escape `!` (portabel PostgreSQL/SQLite); input memakai binding.
- `services.code` kanonik uppercase (diverifikasi 0 baris lowercase/spasi tepi).

## Implementasi Query

1. Ambil prefix aktif unit (1 query, tabel kecil).
2. Resolusi prefix -> daftar `service_id` (1 query, `code LIKE 'PREFIX%'`).
3. Query tarif:
   - daftar service <= 1500: `whereIn('service_id', $ids)` (binding efisien);
   - daftar service > 1500: subquery `service_id IN (SELECT id ...)` agar
     tidak mengirim ribuan parameter.
4. Filter status/pencarian/periode memakai `TarifService::applyFilters`
   sehingga diterapkan di SQL sebelum pagination.
5. Eager loading 4 relasi (jenis, provider, service, kelas) -> tidak ada N+1.
6. Pagination default 25 (opsi 25/50/100); export XLSX memakai chunk 1000.

## File Baru

- `database/migrations/2026_10_20_000001_create_tarif_unit_service_prefixes_table.php`
- `database/migrations/2026_10_20_000002_drop_tarif_unit_mappings_table.php`
- `app/Models/TarifUnitServicePrefix.php`
- `app/Http/Services/TarifUnitBookService.php`
- `app/Http/Services/TarifUnitServicePrefixService.php`
- `app/Http/Services/TarifUnitBookExportService.php`
- `app/Exports/TarifUnitBookExport.php`
- `app/Http/Controllers/TarifUnitBookController.php`
- `app/Http/Controllers/TarifUnitServicePrefixController.php`
- `app/Http/Requests/TarifUnitServicePrefix/Store.php`
- `app/Http/Requests/TarifUnitServicePrefix/Update.php`
- `resources/views/tarif-units/prefixes/index.blade.php`
- `resources/views/tarif-units/prefixes/form.blade.php`
- `tests/Feature/TarifUnitBookTest.php`
- `tests/Feature/TarifUnitServicePrefixTest.php`
- `tests/Feature/Concerns/TarifBookTestSchema.php`

## File Diubah

- `app/Models/OrganizationUnit.php` (relasi `servicePrefixes`)
- `app/Models/Tarif.php` (hapus `unitMappings`, tambah scope service)
- `routes/web.php`
- `resources/views/tarif-units/index.blade.php`
- `resources/views/tarif-units/unit.blade.php`
- `resources/views/tarif-units/show.blade.php`
- `resources/views/tarif-units/pdf.blade.php`
- `app/Console/Commands/ImportSimutuUsers.php` (cek prefix saat prune unit)

## File Dihapus

- `app/Models/TarifUnitMapping.php`
- `app/Http/Controllers/TarifUnitMappingController.php`
- `app/Http/Services/TarifUnitMappingService.php`
- `app/Http/Services/TarifUnitMappingExportService.php`
- `app/Exports/TarifUnitMappingExport.php`
- `app/Http/Requests/TarifUnitMapping/`
- `resources/views/tarif-units/create.blade.php`
- `resources/views/tarif-units/edit.blade.php`
- `resources/views/tarif-units/partials/`
- `resources/views/components/tarif-input-table.blade.php`
- `resources/views/components/tarif-picker.blade.php`
- `tests/Feature/TarifUnitMappingTest.php`

## Hasil Test

- `TarifUnitBookTest`: 21 test lulus.
- `TarifUnitServicePrefixTest`: 15 test lulus.
- Full suite: **114 lulus, 5 gagal (pre-existing)**.
  - `tests/Feature/ExampleTest` (302 vs 200).
  - 4 test `ImportScanQueueTest` (tabel `jobs` tidak ada di skema test,
    timeout job 3600 vs 1800).
- Tidak ada kegagalan baru dari refactor ini.
- Export XLSX dan PDF ikut diuji (200 OK) dan mengikuti filter prefix.

## Hasil Performa (DB aktual, 207.059 tarif)

| Skenario | Waktu | Query |
|---|---|---|
| Baseline halaman 25 tarif tanpa filter | 48-56 ms | 5 |
| Resolusi prefix (JPU + LAB -> 852 service) | 30 ms | 1 |
| Halaman buku tarif (25 baris, 13.390 hasil) | 65 ms | 7 |
| Query berulang / N+1 | tidak ada | - |

- Implementasi pertama (subquery `UPPER(TRIM(code))` + ORDER BY created_at)
  memindai 76 ribu baris dan butuh 249 ms. Setelah resolusi prefix terpisah
  dan pemilihan IN/subquery adaptif: 65 ms (sekitar 3,8x lebih cepat).
- Execution plan memakai `tarifs_service_id_idx` / `created_at` sesuai
  ukuran hasil; tidak ada index baru karena index yang dibutuhkan sudah ada
  (`service_id`, `created_at`, kombinasi periode).
- Prefix besar (`O`, 7.679 service): subquery path 3,4 ms untuk satu halaman.
- Cache prefix tidak dipakai: query prefix ±3-30 ms dan database cache store
  justru menambah query; risiko data basi lebih besar dari manfaatnya.

## Catatan / Risiko

- Permission dipertahankan: `manage_tarif_units`, `edit_tarif_units`,
  `delete_tarif_units`, `manage_my_tarif_unit`, `edit_my_tarif_unit`,
  `delete_my_tarif_unit`.
- Migration drop membackup data ke JSON dan membatalkan diri jika backup
  gagal. Rollback membuat tabel kosong; restore data dari file JSON manual.
- Migration lama `2026_10_19_000001_create_tarif_unit_mappings_table.php`
  sengaja dipertahankan sebagai histori (fresh install membuat lalu langsung
  men-drop tabel; rollback berantai tetap bersih).
- PDF merender seluruh baris hasil filter (bukan chunk). Untuk dataset sangat
  besar gunakan filter agar file tetap wajar.
- Prefix unit harus diisi admin/operator; tanpa prefix buku tarif kosong
  (by design).
