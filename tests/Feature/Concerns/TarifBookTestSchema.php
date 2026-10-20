<?php

namespace Tests\Feature\Concerns;

use App\Http\Services\TarifUnitBookService;
use App\Models\JenisTarif;
use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceClass;
use App\Models\Tarif;
use App\Models\TarifUnitServicePrefix;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema dan fixture bersama untuk test buku tarif berbasis prefix.
 *
 * Catatan: tidak memakai RefreshDatabase karena sebagian migration memakai
 * fitur khusus PostgreSQL (trigram/fulltext). Skema dibuat manual seperti
 * test fitur lain di repo ini.
 *
 * Tabel `tarif_unit_mappings` sengaja TIDAK dibuat: aplikasi harus tetap
 * berjalan tanpa tabel tersebut.
 */
trait TarifBookTestSchema
{
    protected User $admin;

    protected User $unitUser;

    protected User $otherUnitUser;

    protected User $plainUser;

    protected OrganizationUnit $unit;

    protected OrganizationUnit $otherUnit;

    protected OrganizationUnit $noPrefixUnit;

    protected OrganizationUnit $inactiveUnit;

    protected function setUpTarifBook(): void
    {
        $this->withoutVite();
        $this->createTarifBookTables();
        $this->truncateTarifBookTables();

        $this->seedAccessControl();

        $type = OrganizationType::create([
            'name' => 'unit-kerja',
            'display_name' => 'Unit Kerja',
            'level' => 3,
        ]);

        $this->unit = OrganizationUnit::create([
            'name' => 'Keperawatan',
            'code' => 'KPR',
            'type_id' => $type->id,
            'is_active' => true,
        ]);

        $this->otherUnit = OrganizationUnit::create([
            'name' => 'Laboratorium',
            'code' => 'LAB',
            'type_id' => $type->id,
            'is_active' => true,
        ]);

        $this->noPrefixUnit = OrganizationUnit::create([
            'name' => 'Unit Tanpa Prefix',
            'code' => 'NOP',
            'type_id' => $type->id,
            'is_active' => true,
        ]);

        $this->inactiveUnit = OrganizationUnit::create([
            'name' => 'Unit Nonaktif',
            'code' => 'NON',
            'type_id' => $type->id,
            'is_active' => false,
        ]);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $unitRole = Role::where('name', 'operator-unit')->firstOrFail();
        $viewerRole = Role::where('name', 'viewer')->firstOrFail();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role_id' => $adminRole->id,
        ]);

        $this->unitUser = User::create([
            'name' => 'Operator Keperawatan',
            'email' => 'operator@example.com',
            'password' => 'secret',
            'role_id' => $unitRole->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->otherUnitUser = User::create([
            'name' => 'Operator Lab',
            'email' => 'operator-lab@example.com',
            'password' => 'secret',
            'role_id' => $unitRole->id,
            'organization_unit_id' => $this->otherUnit->id,
        ]);

        $this->plainUser = User::create([
            'name' => 'Tanpa Izin',
            'email' => 'plain@example.com',
            'password' => 'secret',
            'role_id' => $viewerRole->id,
        ]);
    }

    protected function seedAccessControl(): void
    {
        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Admin']);

        foreach ([
            ['manage_tarif_units', 'Kelola Tarif Unit'],
            ['edit_tarif_units', 'Ubah Tarif Unit'],
            ['delete_tarif_units', 'Hapus Tarif Unit'],
        ] as [$name, $display]) {
            $adminRole->permissions()->attach(
                Permission::create(['name' => $name, 'display_name' => $display])->id
            );
        }

        $unitRole = Role::create(['name' => 'operator-unit', 'display_name' => 'Operator Unit']);

        foreach ([
            ['manage_my_tarif_unit', 'Kelola Buku Tarif Unit Saya'],
            ['edit_my_tarif_unit', 'Ubah Buku Tarif Unit Saya'],
            ['delete_my_tarif_unit', 'Hapus Buku Tarif Unit Saya'],
        ] as [$name, $display]) {
            $unitRole->permissions()->attach(
                Permission::create(['name' => $name, 'display_name' => $display])->id
            );
        }

        Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
    }

    protected function createTarifBookTables(): void
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
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
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

        if (! Schema::hasTable('tarif_unit_service_prefixes')) {
            Schema::create('tarif_unit_service_prefixes', function ($table) {
                $table->id();
                $table->foreignId('organization_unit_id')->constrained('organization_units')->cascadeOnDelete();
                $table->string('prefix', 50);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['organization_unit_id', 'prefix']);
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

        if (! Schema::hasColumn('users', 'organization_unit_id')) {
            Schema::table('users', function ($table) {
                $table->unsignedBigInteger('organization_unit_id')->nullable();
            });
        }
    }

    protected function truncateTarifBookTables(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        foreach ([
            'activity_logs',
            'tarif_unit_service_prefixes',
            'tarifs',
            'jenis_tarifs',
            'providers',
            'services',
            'classes',
            'organization_units',
            'organization_types',
            'users',
            'roles',
            'permissions',
            'role_permission',
        ] as $table) {
            DB::table($table)->delete();
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeTarif(string $serviceCode, array $overrides = []): Tarif
    {
        $jenis = JenisTarif::firstOrCreate(
            ['code' => 'JNS'],
            ['name' => 'Jenis Tarif', 'status' => 'active']
        );

        $provider = Provider::firstOrCreate(
            ['code' => 'PRV'],
            ['name' => 'Provider', 'status' => 'active']
        );

        $service = Service::firstOrCreate(
            ['code' => $serviceCode],
            [
                'name' => 'Layanan '.$serviceCode,
                'description' => 'Deskripsi '.$serviceCode,
                'status' => 'active',
            ]
        );

        $classCode = $overrides['class_code'] ?? 'KLS1';
        unset($overrides['class_code']);

        $class = ServiceClass::firstOrCreate(
            ['code' => $classCode],
            ['name' => 'Kelas '.$classCode, 'status' => 'active']
        );

        return Tarif::create(array_merge([
            'jenis_tarif_id' => $jenis->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'class_id' => $class->id,
            'surgery_type' => 'NON SURGERY',
            'helper' => null,
            'tariff' => 150000,
            'valid_date_from' => '2024-01-01',
            'end_date_to' => '2029-12-31',
        ], $overrides));
    }

    protected function addPrefix(
        OrganizationUnit $unit,
        string $prefix,
        bool $active = true,
        ?string $notes = null
    ): TarifUnitServicePrefix {
        return TarifUnitServicePrefix::create([
            'organization_unit_id' => $unit->id,
            'prefix' => mb_strtoupper($prefix),
            'is_active' => $active,
            'notes' => $notes,
        ]);
    }

    protected function bookService(): TarifUnitBookService
    {
        return app(TarifUnitBookService::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    protected function matchingServiceIds(OrganizationUnit $unit, array $filters = []): array
    {
        return $this->bookService()
            ->query($unit, $filters)
            ->pluck('service_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
