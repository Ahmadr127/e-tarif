<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{name: string, display_name: string, description: string}>
     */
    private const PERMISSIONS = [
        [
            'name' => 'manage_calculations',
            'display_name' => 'Kelola Calculation Engine',
            'description' => 'Mengelola komponen, variabel, formula, dan menjalankan kalkulasi unit cost',
        ],
        [
            'name' => 'approve_calculations',
            'display_name' => 'Setujui Kalkulasi Unit Cost',
            'description' => 'Menyetujui hasil kalkulasi unit cost',
        ],
    ];

    public function up(): void
    {
        $adminId = DB::table('roles')->where('name', 'admin')->value('id');

        foreach (self::PERMISSIONS as $row) {
            $permissionId = DB::table('permissions')->where('name', $row['name'])->value('id');

            if ($permissionId === null) {
                $permissionId = DB::table('permissions')->insertGetId(array_merge($row, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            if ($adminId === null) {
                continue;
            }

            $exists = DB::table('role_permission')
                ->where('role_id', $adminId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $adminId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $names = array_column(self::PERMISSIONS, 'name');
        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
