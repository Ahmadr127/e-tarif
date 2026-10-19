<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * User default aplikasi: hanya "admin" dan "user".
 *
 * Akun "user" ditempatkan di Departemen IT (unit dibuat oleh
 * OrganizationUnitSeeder) supaya fitur "Buku Tarif Unit Saya" (/buku-tarif-saya)
 * langsung bisa dipakai. Password default keduanya: 123
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $userRole = Role::where('name', 'user')->firstOrFail();
        $itUnit = OrganizationUnit::where('code', 'IT')->first();

        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => '123',
                'role_id' => $adminRole->id,
                'organization_unit_id' => null,
            ]
        );

        User::updateOrCreate(
            ['username' => 'user'],
            [
                'name' => 'User',
                'email' => 'user@example.com',
                'password' => '123',
                'role_id' => $userRole->id,
                'organization_unit_id' => $itUnit?->id,
            ]
        );
    }
}
