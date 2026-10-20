# TASK: Refactor Buku Tarif Unit Berbasis Prefix Service

Status keseluruhan: **BERJALAN**

Terakhir diupdate: 2026-10-20

## Ringkasan

Mengganti sistem assignment tarif individual (`tarif_unit_mappings`) dengan
pencocokan otomatis prefix `services.code` per unit organisasi
(`tarif_unit_service_prefixes`), sekaligus optimasi query dan rendering
halaman buku tarif. Arsitektur, permission, UI, dan fitur lain dipertahankan.

## Daftar Task

| # | Task | Status | Catatan |
|---|------|--------|---------|
| 1 | Audit dependensi `TarifUnitMapping` dan skema DB | ✅ Selesai | 170.539 tarif, 10.789 service, mapping lama 0 baris |
| 2 | Ukur baseline performa | ✅ Selesai | 25 baris tarif = 39 ms; query prefix JPU = 80 ms |
| 3 | Migration `tarif_unit_service_prefixes` | ✅ Selesai | `2026_10_20_000001_...` |
| 4 | Migration hapus `tarif_unit_mappings` + backup JSON | ✅ Selesai (kode) | Backup ke `storage/app/backups/` sebelum drop |
| 5 | Model `TarifUnitServicePrefix` + relasi model | ✅ Selesai | `OrganizationUnit::servicePrefixes()`, scope `Tarif::forServicePrefixes()` |
| 6 | Service CRUD prefix + authorization | ✅ Selesai (kode) | Activity log, unique per unit, validasi karakter |
| 7 | Query buku tarif berbasis prefix | ✅ Selesai | Filter via `TarifService::applyFilters`, pagination 25/50/100 |
| 8 | Refactor controller + routes | ✅ Selesai | `TarifUnitBookController`, `TarifUnitServicePrefixController` |
| 9 | Refactor views + hapus UI assignment lama | 🔄 Berjalan | index/unit/show/pdf/prefixes sudah ditulis |
| 10 | Hapus model/export/request/service mapping lama | ⏳ Belum | Menunggu verifikasi referensi |
| 11 | Automated test (16 skenario + auth + N+1) | ⏳ Belum | |
| 12 | Jalankan migration, Pint, test suite | ⏳ Belum | Backup DB sebelum migrate |
| 13 | Ukur performa setelah refactor | ⏳ Belum | Query count + EXPLAIN |
| 14 | Laporan akhir + prosedur deploy/rollback | ⏳ Belum | |

## Detail Implementasi

### Skema baru

`tarif_unit_service_prefixes`:

- `id`
- `organization_unit_id` (FK cascade delete/update)
- `prefix` varchar(50)
- `is_active` boolean default true
- `notes` text nullable
- `timestamps`
- unique `(organization_unit_id, prefix)`
- index `(organization_unit_id, is_active)`

### Aturan pencocokan

- Hanya `services.code`, dari awal kode (`PREFIX%`), bukan substring.
- Prefix aktif saja; unit induk tidak mewariskan prefix.
- Tanpa prefix aktif => hasil kosong (tidak pernah menampilkan semua tarif).
- Prefix dinormalisasi uppercase + trim, ditolak jika mengandung spasi/wildcard.
- Wildcard LIKE (`%`, `_`, `\`) di-escape; query memakai parameter binding.

### File baru

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

### File diubah

- `app/Models/OrganizationUnit.php` (relasi `servicePrefixes`)
- `app/Models/Tarif.php` (hapus `unitMappings`, tambah scope prefix)
- `routes/web.php`
- `resources/views/tarif-units/index.blade.php`
- `resources/views/tarif-units/unit.blade.php`
- `resources/views/tarif-units/show.blade.php`
- `resources/views/tarif-units/pdf.blade.php`
- `app/Console/Commands/ImportSimutuUsers.php` (cek prefix saat prune unit)

### File yang akan dihapus

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
- `tests/Feature/TarifUnitMappingTest.php` (diganti test baru)

## Log Progres

- 2026-10-20: audit dependensi, baseline performa, migration prefix, model,
  service, controller, route, dan view utama selesai ditulis.
- 2026-10-20: task file ini dibuat atas permintaan user.

## Catatan / Risiko

- Permission yang dipertahankan: `manage_tarif_units`, `edit_tarif_units`,
  `delete_tarif_units`, `manage_my_tarif_unit`, `edit_my_tarif_unit`,
  `delete_my_tarif_unit`.
- Migration drop melakukan backup JSON otomatis dan membatalkan diri jika
  backup gagal.
- Cache prefix sengaja tidak dipakai (database cache store menambah query;
  query prefix terindeks murah). Akan diverifikasi dengan pengukuran.
