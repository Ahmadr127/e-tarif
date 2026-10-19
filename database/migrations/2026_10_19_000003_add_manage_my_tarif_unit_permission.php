<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAME = 'manage_my_tarif_unit';

    /**
     * Permission untuk operator unit: mengelola buku tarif unit organisasi
     * milik pengguna sendiri (langsung ke unitnya, tanpa daftar unit).
     */
    public function up(): void
    {
        $id = DB::table('permissions')->where('name', self::NAME)->value('id');

        if ($id === null) {
            $id = DB::table('permissions')->insertGetId([
                'name' => self::NAME,
                'display_name' => 'Kelola Buku Tarif Unit Saya',
                'description' => 'Input/edit tarif pada buku tarif unit organisasi milik pengguna sendiri',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Role yang mendapat permission ini.
        foreach (['user'] as $roleName) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $exists = DB::table('role_permission')
                ->where('role_id', $role->id)
                ->where('permission_id', $id)
                ->exists();

            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $id,
                ]);
            }
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
