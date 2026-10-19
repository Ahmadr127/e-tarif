<?php

namespace Tests\Feature;

use App\Models\JenisTarif;
use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceClass;
use App\Models\Tarif;
use App\Models\TarifUnitMapping;
use App\Models\User;
use App\Http\Services\TarifUnitMappingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Catatan: tidak memakai RefreshDatabase karena salah satu migration
 * (index trigram/fulltext) khusus PostgreSQL — skema dibuat manual seperti
 * test fitur lain di repo ini.
 */
class TarifUnitMappingTest extends TestCase
{
    protected User $admin;

    protected User $unitUser;

    protected Tarif $tarif;

    protected OrganizationUnit $unit;

    protected OrganizationUnit $inactiveUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
        $this->createTables();
        $this->truncateTables();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $permission = Permission::create(['name' => 'manage_tarif_units', 'display_name' => 'Kelola Tarif Unit']);
        $role->permissions()->attach($permission->id);

        // Permission aksi ubah/hapus Buku Tarif admin.
        $role->permissions()->attach(
            Permission::create(['name' => 'edit_tarif_units', 'display_name' => 'Ubah Buku Tarif'])->id
        );
        $role->permissions()->attach(
            Permission::create(['name' => 'delete_tarif_units', 'display_name' => 'Hapus Buku Tarif'])->id
        );

        // Role operator unit: hanya mengelola buku tarif unitnya sendiri.
        $userRole = Role::create(['name' => 'user', 'display_name' => 'Pengguna']);
        $userPermission = Permission::create([
            'name' => 'manage_my_tarif_unit',
            'display_name' => 'Kelola Buku Tarif Unit Saya',
        ]);
        $userRole->permissions()->attach($userPermission->id);

        // Permission aksi ubah/hapus Buku Tarif unit sendiri.
        $userRole->permissions()->attach(
            Permission::create(['name' => 'edit_my_tarif_unit', 'display_name' => 'Ubah Buku Tarif Unit Saya'])->id
        );
        $userRole->permissions()->attach(
            Permission::create(['name' => 'delete_my_tarif_unit', 'display_name' => 'Hapus Buku Tarif Unit Saya'])->id
        );

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role_id' => $role->id,
        ]);

        $type = OrganizationType::create(['name' => 'ruang', 'display_name' => 'Ruang', 'level' => 3]);

        $this->unit = OrganizationUnit::create([
            'name' => 'Ruang Mawar',
            'code' => 'RWM',
            'type_id' => $type->id,
            'is_active' => true,
        ]);

        OrganizationUnit::create([
            'name' => 'Unit Nonaktif',
            'code' => 'NON',
            'type_id' => $type->id,
            'is_active' => false,
        ]);

        $this->inactiveUnit = OrganizationUnit::where('code', 'NON')->firstOrFail();

        $this->unitUser = User::create([
            'name' => 'Operator Unit',
            'email' => 'operator@example.com',
            'password' => 'secret',
            'role_id' => $userRole->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->tarif = $this->makeTarif('KONS01', 'Konsultasi Dokter Umum', 'KLS1', 'Kelas 1');
    }

    protected function createTables(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function ($table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->onDelete('cascade');
                $table->foreignId('permission_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['role_id', 'permission_id']);
            });
        }
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->foreignId('role_id')->nullable();
                $table->unsignedBigInteger('organization_unit_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('organization_types')) {
            Schema::create('organization_types', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->integer('level')->default(1);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('organization_units')) {
            Schema::create('organization_units', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->foreignId('type_id')->constrained('organization_types');
                $table->foreignId('parent_id')->nullable()->constrained('organization_units');
                $table->foreignId('head_id')->nullable()->constrained('users');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('providers')) {
            Schema::create('providers', function ($table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('services')) {
            Schema::create('services', function ($table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function ($table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('jenis_tarifs')) {
            Schema::create('jenis_tarifs', function ($table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tarifs')) {
            Schema::create('tarifs', function ($table) {
                $table->id();
                $table->foreignId('jenis_tarif_id')->nullable()->constrained('jenis_tarifs');
                $table->foreignId('provider_id')->constrained('providers');
                $table->foreignId('service_id')->constrained('services');
                $table->foreignId('class_id')->constrained('classes');
                $table->string('surgery_type')->nullable();
                $table->string('helper')->nullable();
                $table->decimal('tariff', 15, 2);
                $table->date('valid_date_from');
                $table->date('end_date_to');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tarif_unit_mappings')) {
            Schema::create('tarif_unit_mappings', function ($table) {
                $table->id();
                $table->foreignId('tarif_id')->constrained('tarifs')->cascadeOnDelete();
                $table->foreignId('organization_unit_id')->constrained('organization_units')->cascadeOnDelete();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['tarif_id', 'organization_unit_id']);
            });
        }
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($table) {
                $table->id();
                $table->string('log_name')->nullable();
                $table->string('event');
                $table->string('description')->nullable();
                $table->nullableMorphs('subject');
                $table->nullableMorphs('causer');
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->uuid('batch_uuid')->nullable();
                $table->timestamps();
            });
        }

        // Tabel users bisa sudah dibuat test lain tanpa kolom unit organisasi.
        if (! Schema::hasColumn('users', 'organization_unit_id')) {
            Schema::table('users', function ($table) {
                $table->unsignedBigInteger('organization_unit_id')->nullable();
            });
        }
    }

    protected function truncateTables(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach ([
            'activity_logs', 'tarif_unit_mappings', 'tarifs', 'jenis_tarifs', 'providers',
            'services', 'classes', 'organization_units', 'organization_types', 'users',
            'roles', 'permissions', 'role_permission',
        ] as $table) {
            DB::table($table)->delete();
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function makeTarif(string $serviceCode, string $serviceDesc, string $classCode, string $className): Tarif
    {
        $jenis = JenisTarif::firstOrCreate(['code' => 'tarif'], ['name' => 'Tarif', 'status' => 'active']);
        $provider = Provider::firstOrCreate(['code' => 'PRV1'], ['name' => 'Provider Satu', 'status' => 'active']);
        $service = Service::create([
            'code' => $serviceCode,
            'name' => $serviceDesc,
            'description' => $serviceDesc,
            'status' => 'active',
        ]);
        $class = ServiceClass::firstOrCreate(['code' => $classCode], ['name' => $className, 'status' => 'active']);

        return Tarif::create([
            'jenis_tarif_id' => $jenis->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'class_id' => $class->id,
            'surgery_type' => 'NON SURGERY',
            'helper' => null,
            'tariff' => 150000,
            'valid_date_from' => '2024-01-01',
            'end_date_to' => '2029-12-31',
        ]);
    }

    protected function makeMapping(array $overrides = []): TarifUnitMapping
    {
        return TarifUnitMapping::create(array_merge([
            'tarif_id' => $this->tarif->id,
            'organization_unit_id' => $this->unit->id,
            'status' => 'active',
            'notes' => null,
        ], $overrides));
    }

    /**
     * User role user tanpa unit organisasi (mis. akun belum dihubungkan admin).
     */
    protected function makeUserWithoutPermission(): User
    {
        return User::create([
            'name' => 'Biasa',
            'email' => 'biasa@example.com',
            'password' => 'secret',
            'role_id' => Role::where('name', 'user')->value('id'),
        ]);
    }

    public function test_index_requires_manage_tarif_units_permission(): void
    {
        $this->actingAs($this->makeUserWithoutPermission())
            ->get(route('tarif-units.index'))
            ->assertForbidden();
    }

    public function test_index_lists_units_for_input(): void
    {
        $this->makeMapping();

        $response = $this->actingAs($this->admin)->get(route('tarif-units.index'));

        $response->assertOk();
        $response->assertSee('Buku Tarif');
        $response->assertSee('Ruang Mawar');
        $response->assertSee('Input Buku Tarif');
        // Filter default: hanya unit aktif.
        $response->assertDontSee('Unit Nonaktif');
    }

    public function test_unit_page_lists_tarifs_of_the_unit(): void
    {
        $this->makeMapping(['notes' => 'Berlaku mulai Januari']);

        $response = $this->actingAs($this->admin)->get(route('tarif-units.unit', $this->unit));

        $response->assertOk();
        $response->assertSee('Buku Tarif: Ruang Mawar');
        $response->assertSee('KONS01');
        $response->assertSee('Export Excel');
        $response->assertSee('Export PDF');
    }

    public function test_create_page_locks_selected_unit(): void
    {
        $response = $this->actingAs($this->admin)->get(route('tarif-units.create', ['unit_id' => $this->unit->id]));

        $response->assertOk();
        $response->assertSee('Input Buku Tarif');
        $response->assertSee('Ruang Mawar');
        $response->assertSee('name="organization_unit_id" value="'.$this->unit->id.'"', false);
    }

    public function test_create_page_redirects_when_unit_not_chosen(): void
    {
        $response = $this->actingAs($this->admin)->get(route('tarif-units.create'));

        $response->assertRedirect(route('tarif-units.index'));
        $response->assertSessionHas('error');
    }

    public function test_create_page_rejects_inactive_unit(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tarif-units.create', ['unit_id' => $this->inactiveUnit->id]))
            ->assertNotFound();
    }

    public function test_store_creates_mapping_and_writes_activity_log(): void
    {
        $response = $this->actingAs($this->admin)->post(route('tarif-units.store'), [
            'organization_unit_id' => $this->unit->id,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active', 'notes' => 'Mapping awal'],
            ],
        ]);

        $response->assertRedirect(route('tarif-units.unit', $this->unit->id));
        $response->assertSessionHas('success');

        $mapping = TarifUnitMapping::firstWhere('tarif_id', $this->tarif->id);
        $this->assertNotNull($mapping);
        $this->assertSame($this->unit->id, $mapping->organization_unit_id);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'created',
            'subject_type' => TarifUnitMapping::class,
            'subject_id' => $mapping->id,
            'causer_id' => $this->admin->id,
        ]);
    }

    public function test_store_accepts_multiple_rows_at_once(): void
    {
        $second = $this->makeTarif('KONS02', 'Konsultasi Dokter Spesialis', 'KLS1', 'Kelas 1');

        $response = $this->actingAs($this->admin)->post(route('tarif-units.store'), [
            'organization_unit_id' => $this->unit->id,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active', 'notes' => 'Baris satu'],
                ['tarif_id' => $second->id, 'status' => 'inactive', 'notes' => null],
            ],
        ]);

        $response->assertRedirect(route('tarif-units.unit', $this->unit->id));
        $response->assertSessionHasNoErrors();

        $this->assertSame(2, TarifUnitMapping::count());
        $this->assertDatabaseHas('tarif_unit_mappings', [
            'tarif_id' => $this->tarif->id,
            'organization_unit_id' => $this->unit->id,
            'notes' => 'Baris satu',
        ]);
        $this->assertDatabaseHas('tarif_unit_mappings', [
            'tarif_id' => $second->id,
            'organization_unit_id' => $this->unit->id,
            'status' => 'inactive',
        ]);
    }

    public function test_store_rejects_same_tarif_twice_in_payload(): void
    {
        $response = $this->actingAs($this->admin)->post(route('tarif-units.store'), [
            'organization_unit_id' => $this->unit->id,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active'],
                ['tarif_id' => $this->tarif->id, 'status' => 'active'],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0, TarifUnitMapping::count());
    }

    public function test_store_rejects_duplicate_tarif_unit_pair(): void
    {
        $this->makeMapping();

        $response = $this->actingAs($this->admin)->post(route('tarif-units.store'), [
            'organization_unit_id' => $this->unit->id,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active'],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.tarif_id');
        $this->assertSame(1, TarifUnitMapping::count());
    }

    public function test_store_rejects_empty_items(): void
    {
        $response = $this->actingAs($this->admin)->post(route('tarif-units.store'), [
            'organization_unit_id' => $this->unit->id,
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, TarifUnitMapping::count());
    }

    public function test_store_rejects_unknown_unit(): void
    {
        $response = $this->actingAs($this->admin)->post(route('tarif-units.store'), [
            'organization_unit_id' => 99999,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active'],
            ],
        ]);

        $response->assertSessionHasErrors('organization_unit_id');
        $this->assertSame(0, TarifUnitMapping::count());
    }

    public function test_show_displays_mapping_detail(): void
    {
        $mapping = $this->makeMapping();

        $response = $this->actingAs($this->admin)->get(route('tarif-units.show', $mapping));

        $response->assertOk();
        $response->assertSee('Detail Buku Tarif');
        $response->assertSee('Ruang Mawar');
        $response->assertSee('Konsultasi Dokter Umum');
    }

    public function test_edit_page_renders_with_selected_values(): void
    {
        $mapping = $this->makeMapping();

        $response = $this->actingAs($this->admin)->get(route('tarif-units.edit', $mapping));

        $response->assertOk();
        $response->assertSee('Edit Buku Tarif');
        $response->assertSee('KONS01');
    }

    public function test_update_changes_status_and_notes(): void
    {
        $mapping = $this->makeMapping();

        $response = $this->actingAs($this->admin)->put(route('tarif-units.update', $mapping), [
            'tarif_id' => $this->tarif->id,
            'organization_unit_id' => $this->unit->id,
            'status' => 'inactive',
            'notes' => 'Dihentikan sementara',
        ]);

        $response->assertRedirect(route('tarif-units.unit', $this->unit->id));
        $mapping->refresh();
        $this->assertSame('inactive', $mapping->status);
        $this->assertSame('Dihentikan sementara', $mapping->notes);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'updated',
            'subject_type' => TarifUnitMapping::class,
            'subject_id' => $mapping->id,
        ]);
    }

    public function test_update_keeps_own_pair_without_unique_error(): void
    {
        $mapping = $this->makeMapping();

        $response = $this->actingAs($this->admin)->put(route('tarif-units.update', $mapping), [
            'tarif_id' => $this->tarif->id,
            'organization_unit_id' => $this->unit->id,
            'status' => 'active',
            'notes' => 'Tetap sama',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('tarif-units.unit', $this->unit->id));
    }

    public function test_update_rejects_pair_owned_by_other_mapping(): void
    {
        $other = OrganizationUnit::create([
            'name' => 'Ruang Melati',
            'code' => 'RML',
            'type_id' => OrganizationType::first()->id,
            'is_active' => true,
        ]);

        $mapping = $this->makeMapping(['organization_unit_id' => $other->id]);
        $existing = $this->makeMapping();

        $response = $this->actingAs($this->admin)->put(route('tarif-units.update', $mapping), [
            'tarif_id' => $existing->tarif_id,
            'organization_unit_id' => $existing->organization_unit_id,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('tarif_id');
    }

    public function test_unit_page_hides_edit_and_delete_without_those_permissions(): void
    {
        $this->makeMapping();

        $readOnly = Role::create(['name' => 'readonly', 'display_name' => 'Read Only']);
        $readOnly->permissions()->attach(
            Permission::where('name', 'manage_tarif_units')->value('id')
        );

        $user = User::create([
            'name' => 'Pembaca',
            'email' => 'baca@example.com',
            'password' => 'secret',
            'role_id' => $readOnly->id,
        ]);

        $response = $this->actingAs($user)->get(route('tarif-units.unit', $this->unit));

        $response->assertOk();
        $response->assertDontSee('Edit');
        $response->assertDontSee('Hapus');
    }

    public function test_update_and_destroy_require_their_own_permissions(): void
    {
        $mapping = $this->makeMapping();

        $readOnly = Role::create(['name' => 'readonly', 'display_name' => 'Read Only']);
        $readOnly->permissions()->attach(
            Permission::where('name', 'manage_tarif_units')->value('id')
        );

        $user = User::create([
            'name' => 'Pembaca',
            'email' => 'baca@example.com',
            'password' => 'secret',
            'role_id' => $readOnly->id,
        ]);

        $this->actingAs($user)
            ->get(route('tarif-units.edit', $mapping))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('tarif-units.update', $mapping), [
                'tarif_id' => $this->tarif->id,
                'organization_unit_id' => $this->unit->id,
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('tarif-units.destroy', $mapping))
            ->assertForbidden();

        $this->assertSame('active', $mapping->refresh()->status);
    }

    public function test_my_unit_update_and_destroy_require_their_own_permissions(): void
    {
        $mapping = $this->makeMapping();

        $limited = Role::create(['name' => 'limited', 'display_name' => 'Limited']);
        $limited->permissions()->attach(
            Permission::where('name', 'manage_my_tarif_unit')->value('id')
        );

        $user = User::create([
            'name' => 'Terbatas',
            'email' => 'terbatas@example.com',
            'password' => 'secret',
            'role_id' => $limited->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->actingAs($user)
            ->get(route('buku-tarif-saya.edit', $mapping))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('buku-tarif-saya.update', $mapping), [
                'tarif_id' => $this->tarif->id,
                'organization_unit_id' => $this->unit->id,
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('buku-tarif-saya.destroy', $mapping))
            ->assertForbidden();

        $this->assertSame('active', $mapping->refresh()->status);
    }

    public function test_create_page_lists_existing_tarifs_with_delete_button(): void
    {
        $this->makeMapping(['notes' => 'Sudah terdaftar']);

        $response = $this->actingAs($this->admin)
            ->get(route('tarif-units.create', ['unit_id' => $this->unit->id]));

        $response->assertOk();
        $response->assertSee('Sudah ada di buku tarif unit ini');
        $response->assertSee('KONS01');
        $response->assertSee(route('tarif-units.destroy', TarifUnitMapping::first()), false);
    }

    public function test_my_unit_create_page_lists_existing_tarifs(): void
    {
        $this->makeMapping();

        $response = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.create'));

        $response->assertOk();
        $response->assertSee('Sudah ada di buku tarif unit ini');
        $response->assertSee(route('buku-tarif-saya.destroy', TarifUnitMapping::first()), false);
    }

    public function test_destroy_deletes_mapping(): void
    {
        $mapping = $this->makeMapping();

        $response = $this->actingAs($this->admin)->delete(route('tarif-units.destroy', $mapping));

        $response->assertRedirect(route('tarif-units.unit', $this->unit->id));
        $this->assertDatabaseMissing('tarif_unit_mappings', ['id' => $mapping->id]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'deleted',
            'subject_type' => TarifUnitMapping::class,
            'subject_id' => $mapping->id,
        ]);
    }

    public function test_search_tarifs_returns_matching_options(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('tarif-units.search-tarifs', ['q' => 'kons']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->tarif->id);
        $this->assertStringContainsString('KONS01', $response->json('data.0.label'));
    }

    public function test_search_tarifs_ignores_short_query_and_supports_id_prefill(): void
    {
        $short = $this->actingAs($this->admin)->getJson(route('tarif-units.search-tarifs', ['q' => 'k']));
        $short->assertOk();
        $short->assertJsonCount(0, 'data');

        $byId = $this->actingAs($this->admin)->getJson(route('tarif-units.search-tarifs', ['id' => $this->tarif->id]));
        $byId->assertOk();
        $byId->assertJsonPath('data.0.id', $this->tarif->id);
    }

    public function test_search_tarifs_requires_permission(): void
    {
        $this->actingAs($this->makeUserWithoutPermission())
            ->getJson(route('tarif-units.search-tarifs', ['q' => 'kons']))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Mode "Buku Tarif Unit Saya" (role operator unit)
    |--------------------------------------------------------------------------
    */

    public function test_my_unit_page_requires_manage_my_tarif_unit_permission(): void
    {
        $this->actingAs($this->admin)
            ->get(route('buku-tarif-saya.index'))
            ->assertForbidden();
    }

    public function test_my_unit_page_lists_tarifs_of_own_unit(): void
    {
        $this->makeMapping(['notes' => 'Catatan unit saya']);

        $response = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.index'));

        $response->assertOk();
        $response->assertSee('Buku Tarif: Ruang Mawar');
        $response->assertSee('KONS01');
        // Tidak ada jalan kembali ke daftar semua unit.
        $response->assertDontSee('Daftar Unit');
        $response->assertSee('Export PDF');
    }

    public function test_sidebar_shows_direct_buku_tarif_menu_for_unit_user(): void
    {
        $response = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.index'));

        $response->assertOk();
        // Menu Buku Tarif mengarah langsung ke unitnya.
        $response->assertSee(route('buku-tarif-saya.index'), false);
        // Menu admin (daftar unit) tidak tampil.
        $response->assertDontSee(route('tarif-units.index'), false);
    }

    public function test_my_unit_page_warns_when_user_has_no_unit(): void
    {
        $this->actingAs($this->makeUserWithoutPermission())
            ->get(route('buku-tarif-saya.index'))
            ->assertOk()
            ->assertSee('belum terhubung ke unit organisasi');
    }

    public function test_my_unit_create_page_locks_own_unit(): void
    {
        $response = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.create'));

        $response->assertOk();
        $response->assertSee('Input Buku Tarif');
        $response->assertSee('name="organization_unit_id" value="'.$this->unit->id.'"', false);
        $response->assertDontSee('Ganti unit');
    }

    public function test_my_unit_store_creates_mapping_for_own_unit(): void
    {
        $response = $this->actingAs($this->unitUser)->post(route('buku-tarif-saya.store'), [
            // Unit ngawur dari form harus diabaikan.
            'organization_unit_id' => $this->inactiveUnit->id,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active', 'notes' => 'Input unit sendiri'],
            ],
        ]);

        $response->assertRedirect(route('buku-tarif-saya.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tarif_unit_mappings', [
            'tarif_id' => $this->tarif->id,
            'organization_unit_id' => $this->unit->id,
            'notes' => 'Input unit sendiri',
        ]);
    }

    public function test_my_unit_store_rejects_duplicate_pair(): void
    {
        $this->makeMapping();

        $response = $this->actingAs($this->unitUser)->post(route('buku-tarif-saya.store'), [
            'organization_unit_id' => $this->unit->id,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active'],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.tarif_id');
        $this->assertSame(1, TarifUnitMapping::count());
    }

    public function test_my_unit_store_accepts_multiple_rows(): void
    {
        $second = $this->makeTarif('KONS02', 'Konsultasi Dokter Spesialis', 'KLS1', 'Kelas 1');

        $response = $this->actingAs($this->unitUser)->post(route('buku-tarif-saya.store'), [
            'organization_unit_id' => $this->unit->id,
            'items' => [
                ['tarif_id' => $this->tarif->id, 'status' => 'active'],
                ['tarif_id' => $second->id, 'status' => 'active'],
            ],
        ]);

        $response->assertRedirect(route('buku-tarif-saya.index'));
        $this->assertSame(2, TarifUnitMapping::where('organization_unit_id', $this->unit->id)->count());
    }

    public function test_my_unit_create_page_renders_multi_row_table(): void
    {
        $response = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.create'));

        $response->assertOk();
        $response->assertSee('Tambah Baris');
        $response->assertSee('Kode Service');
        $response->assertSee('Jenis Tarif');
        $response->assertSee('Kelas');
        $response->assertSee('Tarif (Rp)');
        $response->assertSee(route('buku-tarif-saya.search-tarifs'), false);
    }

    public function test_my_unit_update_changes_own_mapping(): void
    {
        $mapping = $this->makeMapping();

        $response = $this->actingAs($this->unitUser)->put(route('buku-tarif-saya.update', $mapping), [
            'tarif_id' => $this->tarif->id,
            'organization_unit_id' => $this->unit->id,
            'status' => 'inactive',
            'notes' => 'Dinonaktifkan unit',
        ]);

        $response->assertRedirect(route('buku-tarif-saya.index'));
        $this->assertSame('inactive', $mapping->refresh()->status);
        $this->assertSame('Dinonaktifkan unit', $mapping->notes);
    }

    public function test_my_unit_destroy_deletes_own_mapping(): void
    {
        $mapping = $this->makeMapping();

        $this->actingAs($this->unitUser)
            ->delete(route('buku-tarif-saya.destroy', $mapping))
            ->assertRedirect(route('buku-tarif-saya.index'));

        $this->assertDatabaseMissing('tarif_unit_mappings', ['id' => $mapping->id]);
    }

    public function test_my_unit_cannot_touch_mapping_of_other_unit(): void
    {
        $other = OrganizationUnit::create([
            'name' => 'Ruang Melati',
            'code' => 'RML',
            'type_id' => OrganizationType::first()->id,
            'is_active' => true,
        ]);

        $foreign = $this->makeMapping(['organization_unit_id' => $other->id]);

        $this->actingAs($this->unitUser)
            ->get(route('buku-tarif-saya.edit', $foreign))
            ->assertForbidden();

        $this->actingAs($this->unitUser)
            ->put(route('buku-tarif-saya.update', $foreign), [
                'tarif_id' => $this->tarif->id,
                'organization_unit_id' => $other->id,
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->actingAs($this->unitUser)
            ->delete(route('buku-tarif-saya.destroy', $foreign))
            ->assertForbidden();

        $this->assertSame('active', $foreign->refresh()->status);
    }

    public function test_my_unit_search_tarifs_is_scoped_to_unit_permission(): void
    {
        $response = $this->actingAs($this->unitUser)
            ->getJson(route('buku-tarif-saya.search-tarifs', ['q' => 'kons']));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $this->tarif->id);

        $this->actingAs($this->admin)
            ->getJson(route('buku-tarif-saya.search-tarifs', ['q' => 'kons']))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Export buku tarif unit
    |--------------------------------------------------------------------------
    */

    public function test_unit_export_xlsx_downloads_file_named_by_unit_code(): void
    {
        $this->makeMapping();

        $response = $this->actingAs($this->admin)
            ->get(route('tarif-units.unit.export-xlsx', $this->unit));

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('buku-tarif-RWM', $disposition);
    }

    public function test_unit_export_pdf_downloads_pdf_file(): void
    {
        $this->makeMapping(['notes' => 'Catatan PDF', 'status' => 'inactive']);

        $response = $this->actingAs($this->admin)
            ->get(route('tarif-units.unit.export-pdf', $this->unit));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('buku-tarif-RWM', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_unit_export_view_contains_rows_and_mapping_columns(): void
    {
        $this->makeMapping(['notes' => 'Catatan export', 'status' => 'inactive']);

        $this->view('tarif-units.pdf', [
            'unit' => $this->unit->load(['type', 'parent']),
            'mappings' => $this->mappingService()->getUnitMappingsForExport($this->unit),
            'filters' => [],
        ])
            ->assertSee('Buku Tarif: Ruang Mawar')
            ->assertSee('Kode Service')
            ->assertSee('Tarif (Rp)')
            ->assertSee('Status')
            ->assertSee('Catatan')
            ->assertSee('KONS01')
            ->assertSee('Konsultasi Dokter Umum')
            ->assertSee('Catatan export')
            ->assertSee('Non-Aktif');
    }

    public function test_unit_export_view_follows_active_filters(): void
    {
        $second = $this->makeTarif('KONS02', 'Konsultasi Dokter Spesialis', 'KLS1', 'Kelas 1');
        $this->makeMapping(['notes' => 'Baris pertama']);
        $this->makeMapping(['tarif_id' => $second->id, 'organization_unit_id' => $this->unit->id]);

        $this->view('tarif-units.pdf', [
            'unit' => $this->unit->load(['type', 'parent']),
            'mappings' => $this->mappingService()->getUnitMappingsForExport($this->unit, ['search' => 'KONS02']),
            'filters' => ['search' => 'KONS02'],
        ])
            ->assertSee('KONS02')
            ->assertDontSee('KONS01');
    }

    public function test_unit_export_requires_permission(): void
    {
        $user = $this->makeUserWithoutPermission();

        $this->actingAs($user)
            ->get(route('tarif-units.unit.export-pdf', $this->unit))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('tarif-units.unit.export-xlsx', $this->unit))
            ->assertForbidden();
    }

    public function test_my_unit_export_pdf_and_xlsx_use_own_unit(): void
    {
        $other = OrganizationUnit::create([
            'name' => 'Ruang Melati',
            'code' => 'RML',
            'type_id' => OrganizationType::first()->id,
            'is_active' => true,
        ]);

        $this->makeMapping(['notes' => 'Punya unit sendiri']);
        $this->makeMapping(['organization_unit_id' => $other->id, 'notes' => 'Punya unit lain']);

        $pdf = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.export-pdf'));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringContainsString('buku-tarif-RWM', $pdf->headers->get('content-disposition'));

        $xlsx = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.export-xlsx'));
        $xlsx->assertOk();
        $this->assertStringContainsString('buku-tarif-RWM', $xlsx->headers->get('content-disposition'));

        // Data export terbatas pada unit pengguna.
        $rows = $this->mappingService()->getUnitMappingsForExport($this->unit);
        $this->assertSame(['Punya unit sendiri'], $rows->pluck('notes')->all());
    }

    protected function mappingService(): TarifUnitMappingService
    {
        return app(TarifUnitMappingService::class);
    }
}
