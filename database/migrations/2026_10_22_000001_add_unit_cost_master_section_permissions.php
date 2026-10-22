<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission granular per section master Unit Cost.
     *
     * `manage_unit_cost_masters` tetap dipertahankan agar role lama tidak
     * kehilangan akses. Permission baru ditambahkan ke role admin dan role
     * yang sudah memiliki `manage_unit_cost_masters`.
     *
     * @var array<int, array{name: string, display_name: string, description: string}>
     */
    private const PERMISSIONS = [
        [
            'name' => 'manage_unit_cost_components',
            'display_name' => 'Kelola Komponen Unit Cost',
            'description' => 'Mengelola master komponen unit cost',
        ],
        [
            'name' => 'manage_unit_cost_parameters',
            'display_name' => 'Kelola Parameter Unit Cost',
            'description' => 'Mengelola master parameter unit cost',
        ],
        [
            'name' => 'view_unit_cost_formula_history',
            'display_name' => 'Lihat Riwayat Formula Unit Cost',
            'description' => 'Melihat riwayat versi formula unit cost',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $row) {
            $exists = DB::table('permissions')->where('name', $row['name'])->exists();

            if (! $exists) {
                DB::table('permissions')->insert(array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_column(self::PERMISSIONS, 'name'))
            ->pluck('id');

        $legacyPermissionId = DB::table('permissions')
            ->where('name', 'manage_unit_cost_masters')
            ->value('id');

        $roleIds = DB::table('roles')
            ->where('name', 'admin')
            ->when($legacyPermissionId !== null, function ($query) use ($legacyPermissionId): void {
                $query->orWhereIn('id', DB::table('role_permission')
                    ->where('permission_id', $legacyPermissionId)
                    ->pluck('role_id'));
            })
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_column(self::PERMISSIONS, 'name'))
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }
};
