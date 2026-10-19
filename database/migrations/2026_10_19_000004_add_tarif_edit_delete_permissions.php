<?php

use Database\Seeders\TarifAccessPermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission ubah/hapus untuk Daftar Tarif dan Buku Tarif.
     * Logika sama dengan TarifAccessPermissionSeeder (aman dijalankan berulang).
     */
    public function up(): void
    {
        (new TarifAccessPermissionSeeder)->run();
    }

    public function down(): void
    {
        $names = [
            'edit_tarifs', 'delete_tarifs',
            'edit_tarif_units', 'delete_tarif_units',
            'edit_my_tarif_unit', 'delete_my_tarif_unit',
        ];

        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
