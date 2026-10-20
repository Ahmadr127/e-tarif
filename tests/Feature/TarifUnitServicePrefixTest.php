<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TarifUnitServicePrefix;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\TarifBookTestSchema;
use Tests\TestCase;

/**
 * CRUD konfigurasi prefix, validasi, authorization, dan migration.
 */
class TarifUnitServicePrefixTest extends TestCase
{
    use TarifBookTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->setUpTarifBook();
    }

    public function test_admin_dapat_menambah_prefix_dan_dinormalisasi(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('tarif-units.prefixes.store', $this->unit),
            ['prefix' => '  jpu  ', 'is_active' => '1', 'notes' => 'Prefix keperawatan']
        );

        $response->assertRedirect(route('tarif-units.prefixes.index', $this->unit));

        $this->assertDatabaseHas('tarif_unit_service_prefixes', [
            'organization_unit_id' => $this->unit->id,
            'prefix' => 'JPU',
            'is_active' => 1,
            'notes' => 'Prefix keperawatan',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'created',
            'subject_type' => TarifUnitServicePrefix::class,
        ]);
    }

    public function test_prefix_duplikat_pada_unit_yang_sama_ditolak_validasi(): void
    {
        $this->addPrefix($this->unit, 'JPU');

        $response = $this->actingAs($this->admin)
            ->from(route('tarif-units.prefixes.create', $this->unit))
            ->post(route('tarif-units.prefixes.store', $this->unit), ['prefix' => 'jpu']);

        $response->assertRedirect(route('tarif-units.prefixes.create', $this->unit));
        $response->assertSessionHasErrors('prefix');
        $this->assertSame(1, TarifUnitServicePrefix::count());
    }

    public function test_prefix_sama_boleh_dipakai_unit_berbeda(): void
    {
        $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($this->admin)
            ->post(route('tarif-units.prefixes.store', $this->otherUnit), ['prefix' => 'JPU'])
            ->assertRedirect(route('tarif-units.prefixes.index', $this->otherUnit));

        $this->assertSame(2, TarifUnitServicePrefix::count());
    }

    public function test_prefix_kosong_atau_mengandung_wildcard_ditolak(): void
    {
        foreach (['', 'JP%', 'JP_U', 'JP U', 'JP*', 'J/P'] as $invalid) {
            $response = $this->actingAs($this->admin)
                ->from(route('tarif-units.prefixes.create', $this->unit))
                ->post(route('tarif-units.prefixes.store', $this->unit), ['prefix' => $invalid]);

            $response->assertSessionHasErrors('prefix');
        }

        $this->assertSame(0, TarifUnitServicePrefix::count());
    }

    public function test_admin_dapat_mengubah_dan_menghapus_prefix(): void
    {
        $prefix = $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($this->admin)
            ->put(route('tarif-units.prefixes.update', [$this->unit, $prefix]), [
                'prefix' => 'jpk',
                'is_active' => '0',
                'notes' => 'Diubah',
            ])
            ->assertRedirect(route('tarif-units.prefixes.index', $this->unit));

        $this->assertDatabaseHas('tarif_unit_service_prefixes', [
            'id' => $prefix->id,
            'prefix' => 'JPK',
            'is_active' => 0,
            'notes' => 'Diubah',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'updated',
            'subject_type' => TarifUnitServicePrefix::class,
            'subject_id' => $prefix->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('tarif-units.prefixes.destroy', [$this->unit, $prefix]))
            ->assertRedirect(route('tarif-units.prefixes.index', $this->unit));

        $this->assertDatabaseMissing('tarif_unit_service_prefixes', ['id' => $prefix->id]);
        $this->assertDatabaseHas('organization_units', ['id' => $this->unit->id]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'deleted',
            'subject_type' => TarifUnitServicePrefix::class,
        ]);
    }

    public function test_unique_constraint_database_mencegah_prefix_ganda(): void
    {
        $this->addPrefix($this->unit, 'JPU');

        $this->expectException(QueryException::class);

        $this->addPrefix($this->unit, 'JPU');
    }

    public function test_operator_unit_tidak_bisa_mengakses_prefix_unit_lain(): void
    {
        $foreign = $this->addPrefix($this->otherUnit, 'LAB');

        $this->actingAs($this->unitUser)
            ->get(route('buku-tarif-saya.prefixes.edit', $foreign))
            ->assertNotFound();

        $this->actingAs($this->unitUser)
            ->put(route('buku-tarif-saya.prefixes.update', $foreign), ['prefix' => 'LAB2'])
            ->assertNotFound();

        $this->actingAs($this->unitUser)
            ->delete(route('buku-tarif-saya.prefixes.destroy', $foreign))
            ->assertNotFound();

        $this->assertDatabaseHas('tarif_unit_service_prefixes', [
            'id' => $foreign->id,
            'prefix' => 'LAB',
        ]);
    }

    public function test_operator_unit_tidak_bisa_memakai_route_admin(): void
    {
        $this->actingAs($this->unitUser)
            ->get(route('tarif-units.prefixes.index', $this->otherUnit))
            ->assertForbidden();
    }

    public function test_unit_selalu_dipaksa_milik_pengguna_saat_menambah_prefix(): void
    {
        $this->actingAs($this->unitUser)
            ->post(route('buku-tarif-saya.prefixes.store'), [
                'prefix' => 'JPU',
                'organization_unit_id' => $this->otherUnit->id,
            ])
            ->assertRedirect(route('buku-tarif-saya.prefixes.index'));

        $this->assertDatabaseHas('tarif_unit_service_prefixes', [
            'organization_unit_id' => $this->unit->id,
            'prefix' => 'JPU',
        ]);
        $this->assertDatabaseMissing('tarif_unit_service_prefixes', [
            'organization_unit_id' => $this->otherUnit->id,
        ]);
    }

    public function test_ubah_dan_hapus_hanya_boleh_dengan_permission_terpisah(): void
    {
        $manageOnlyRole = Role::create(['name' => 'manage-only', 'display_name' => 'Kelola Saja']);
        $manageOnlyRole->permissions()->attach(
            Permission::where('name', 'manage_my_tarif_unit')->firstOrFail()->id
        );

        $manageOnlyUser = User::create([
            'name' => 'Kelola Saja',
            'email' => 'manage-only@example.com',
            'password' => 'secret',
            'role_id' => $manageOnlyRole->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $prefix = $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($manageOnlyUser)
            ->get(route('buku-tarif-saya.prefixes.index'))
            ->assertOk();

        $this->actingAs($manageOnlyUser)
            ->get(route('buku-tarif-saya.prefixes.create'))
            ->assertForbidden();

        $this->actingAs($manageOnlyUser)
            ->put(route('buku-tarif-saya.prefixes.update', $prefix), ['prefix' => 'JPK'])
            ->assertForbidden();

        $this->actingAs($manageOnlyUser)
            ->delete(route('buku-tarif-saya.prefixes.destroy', $prefix))
            ->assertForbidden();
    }

    public function test_prefix_tidak_boleh_diakses_lewat_unit_yang_salah(): void
    {
        $prefix = $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($this->admin)
            ->get(route('tarif-units.prefixes.edit', [$this->otherUnit, $prefix]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->put(route('tarif-units.prefixes.update', [$this->otherUnit, $prefix]), ['prefix' => 'LAB'])
            ->assertNotFound();
    }

    public function test_menonaktifkan_prefix_langsung_mengubah_cakupan_buku_tarif(): void
    {
        $this->makeTarif('JPU01');
        $prefix = $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit', $this->unit))
            ->assertSee('JPU01');

        $this->actingAs($this->admin)
            ->put(route('tarif-units.prefixes.update', [$this->unit, $prefix]), [
                'prefix' => 'JPU',
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit', $this->unit))
            ->assertDontSee('JPU01');
    }

    public function test_prefix_tidak_mengubah_nominal_tarif(): void
    {
        $tarif = $this->makeTarif('JPU01', ['tariff' => 123000]);
        $prefix = $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($this->admin)
            ->put(route('tarif-units.prefixes.update', [$this->unit, $prefix]), [
                'prefix' => 'JPK',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('123000.00', $tarif->fresh()->tariff);
    }

    public function test_escape_like_memakai_karakter_portabel(): void
    {
        $this->assertSame('JPU!%!_!!', TarifUnitServicePrefix::escapeLike('JPU%_!'));
    }

    public function test_migration_drop_membuat_backup_dan_menghapus_tabel_lama(): void
    {
        Schema::create('tarif_unit_mappings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('tarif_id');
            $table->unsignedBigInteger('organization_unit_id');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('tarif_unit_mappings')->insert([
            'tarif_id' => 10,
            'organization_unit_id' => 20,
            'status' => 'active',
            'notes' => 'Data lama',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = glob(storage_path('app/backups/tarif_unit_mappings-*.json')) ?: [];

        $migration = require database_path(
            'migrations/2026_10_20_000002_drop_tarif_unit_mappings_table.php'
        );

        ob_start();
        $migration->up();
        ob_end_clean();

        $this->assertFalse(Schema::hasTable('tarif_unit_mappings'));

        $after = glob(storage_path('app/backups/tarif_unit_mappings-*.json')) ?: [];
        $newFiles = array_values(array_diff($after, $before));
        $this->assertCount(1, $newFiles, 'Migration harus membuat satu file backup.');

        $payload = json_decode(file_get_contents($newFiles[0]), true);
        $this->assertSame(1, $payload['row_count']);
        $this->assertSame('Data lama', $payload['rows'][0]['notes']);

        @unlink($newFiles[0]);
    }
}
