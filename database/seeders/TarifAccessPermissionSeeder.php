<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission untuk aksi ubah (edit/update) dan hapus pada:
 * - Daftar Tarif            : edit_tarifs, delete_tarifs
 * - Buku Tarif admin        : edit_tarif_units, delete_tarif_units
 * - Buku Tarif unit sendiri : edit_my_tarif_unit, delete_my_tarif_unit
 *
 * Aman dijalankan berulang (firstOrCreate + syncWithoutDetaching).
 */
class TarifAccessPermissionSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, display_name: string, description: string, roles: array<int, string>}>
     */
    private const PERMISSIONS = [
        [
            'name' => 'edit_tarifs',
            'display_name' => 'Ubah Tarif',
            'description' => 'Mengubah data tarif pada Daftar Tarif',
            'roles' => ['admin'],
        ],
        [
            'name' => 'delete_tarifs',
            'display_name' => 'Hapus Tarif',
            'description' => 'Menghapus data tarif pada Daftar Tarif',
            'roles' => ['admin'],
        ],
        [
            'name' => 'edit_tarif_units',
            'display_name' => 'Ubah Buku Tarif',
            'description' => 'Mengubah tarif pada Buku Tarif unit organisasi',
            'roles' => ['admin'],
        ],
        [
            'name' => 'delete_tarif_units',
            'display_name' => 'Hapus Buku Tarif',
            'description' => 'Menghapus tarif dari Buku Tarif unit organisasi',
            'roles' => ['admin'],
        ],
        [
            'name' => 'edit_my_tarif_unit',
            'display_name' => 'Ubah Buku Tarif Unit Saya',
            'description' => 'Mengubah tarif pada Buku Tarif unit organisasi sendiri',
            'roles' => ['user'],
        ],
        [
            'name' => 'delete_my_tarif_unit',
            'display_name' => 'Hapus Buku Tarif Unit Saya',
            'description' => 'Menghapus tarif dari Buku Tarif unit organisasi sendiri',
            'roles' => ['user'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $data) {
            $permission = Permission::firstOrCreate(
                ['name' => $data['name']],
                [
                    'display_name' => $data['display_name'],
                    'description' => $data['description'],
                ]
            );

            $roleIds = Role::whereIn('name', $data['roles'])->pluck('id');

            if ($roleIds->isNotEmpty()) {
                $permission->roles()->syncWithoutDetaching($roleIds->all());
            }
        }
    }
}
