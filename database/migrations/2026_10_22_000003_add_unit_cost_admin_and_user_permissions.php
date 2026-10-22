<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ADMIN_PERMISSION = 'manage_unit_cost';

    private const USER_PERMISSION = 'input_unit_cost';

    /**
     * Permission khusus untuk memisahkan akses admin dan operator unit pada
     * modul Unit Cost.
     *
     * - `manage_unit_cost`     : admin, dapat melihat semua unit/komponen.
     * - `input_unit_cost`      : operator unit, hanya unit sendiri dan hanya
     *                            mengisi nilai (bukan metode/rumus).
     */
    public function up(): void
    {
        $now = now();

        $adminPermissionId = $this->ensurePermission(
            self::ADMIN_PERMISSION,
            'Kelola Unit Cost (Admin)',
            'Akses penuh modul Unit Cost untuk semua unit dan komponen',
            $now,
        );

        $userPermissionId = $this->ensurePermission(
            self::USER_PERMISSION,
            'Input Unit Cost Unit Saya',
            'Input nilai parameter Unit Cost untuk unit organisasi milik pengguna sendiri',
            $now,
        );

        // Role admin dan role lama yang sudah memegang permission admin Unit
        // Cost otomatis mendapatkan permission baru agar akses tidak hilang.
        $legacyPermissionIds = DB::table('permissions')
            ->whereIn('name', [
                'manage_unit_cost_periods',
                'manage_unit_cost_masters',
                'manage_calculations',
            ])
            ->pluck('id');

        $adminRoleIds = DB::table('roles')
            ->where('name', 'admin')
            ->pluck('id')
            ->merge(
                DB::table('role_permission')
                    ->whereIn('permission_id', $legacyPermissionIds)
                    ->pluck('role_id')
            )
            ->unique();

        foreach ($adminRoleIds as $roleId) {
            $this->assign($roleId, $adminPermissionId);
        }

        $userRoleId = DB::table('roles')->where('name', 'user')->value('id');

        if ($userRoleId !== null) {
            $this->assign($userRoleId, $userPermissionId);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [self::ADMIN_PERMISSION, self::USER_PERMISSION])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }

    private function ensurePermission(string $name, string $displayName, string $description, $now): int
    {
        $id = DB::table('permissions')->where('name', $name)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'display_name' => $displayName,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function assign(int $roleId, int $permissionId): void
    {
        DB::table('role_permission')->insertOrIgnore([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }
};
