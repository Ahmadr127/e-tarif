<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission granular Unit Cost.
     *
     * Permission lama `manage_calculations` tetap dipertahankan agar role yang
     * sudah ada tidak kehilangan akses. Permission baru ditambahkan ke role
     * admin dan role yang sudah memiliki `manage_calculations`.
     *
     * @var array<int, array{name: string, display_name: string, description: string}>
     */
    private const PERMISSIONS = [
        [
            'name' => 'manage_unit_cost_periods',
            'display_name' => 'Kelola Periode Unit Cost',
            'description' => 'Membuat, mengubah, mengaktifkan, menutup, dan menghapus periode unit cost',
        ],
        [
            'name' => 'manage_unit_cost_masters',
            'display_name' => 'Kelola Master Unit Cost',
            'description' => 'Mengelola master komponen dan parameter unit cost',
        ],
        [
            'name' => 'edit_unit_cost_parameters',
            'display_name' => 'Ubah Nilai dan Rumus Unit Cost',
            'description' => 'Mengisi nilai dan rumus parameter pada periode tertentu',
        ],
        [
            'name' => 'view_unit_cost_results',
            'display_name' => 'Lihat Hasil Unit Cost',
            'description' => 'Melihat hasil, status kelengkapan, dan riwayat unit cost',
        ],
        [
            'name' => 'approve_unit_cost_results',
            'display_name' => 'Setujui Hasil Unit Cost',
            'description' => 'Menyetujui hasil unit cost dan membuat snapshot resmi',
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
            ->where('name', 'manage_calculations')
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
