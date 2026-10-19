<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAME = 'manage_tarif_units';

    public function up(): void
    {
        $exists = DB::table('permissions')->where('name', self::NAME)->exists();
        if ($exists) {
            return;
        }

        $id = DB::table('permissions')->insertGetId([
            'name' => self::NAME,
            'display_name' => 'Kelola Tarif Unit',
            'description' => 'Mengelola pemetaan tarif ke unit organisasi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // assign ke role admin
        $admin = DB::table('roles')->where('name', 'admin')->first();
        if ($admin) {
            DB::table('role_permission')->insert([
                'role_id' => $admin->id,
                'permission_id' => $id,
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('name', self::NAME)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
