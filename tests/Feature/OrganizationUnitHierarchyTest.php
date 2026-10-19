<?php

namespace Tests\Feature;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi: OrganizationUnit::ancestors() dulu mengembalikan
 * Illuminate\Support\Collection padahal deklarasi return-nya
 * Illuminate\Database\Eloquent\Collection, sehingga halaman edit unit
 * (yang memakai full_path) melempar TypeError.
 *
 * Catatan: tidak memakai RefreshDatabase karena salah satu migration
 * (index trigram/fulltext) khusus PostgreSQL — skema dibuat manual seperti
 * test fitur lain di repo ini.
 */
class OrganizationUnitHierarchyTest extends TestCase
{
    protected User $admin;

    protected OrganizationUnit $root;

    protected OrganizationUnit $mid;

    protected OrganizationUnit $leaf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
        $this->createTables();
        $this->truncateTables();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $permission = Permission::create([
            'name' => 'manage_organization_units',
            'display_name' => 'Kelola Unit Organisasi',
        ]);
        $role->permissions()->attach($permission->id);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role_id' => $role->id,
        ]);

        $rootType = OrganizationType::create(['name' => 'holding', 'display_name' => 'Holding', 'level' => 1]);
        $midType = OrganizationType::create(['name' => 'rumah-sakit', 'display_name' => 'Rumah Sakit', 'level' => 2]);
        $leafType = OrganizationType::create(['name' => 'departemen', 'display_name' => 'Departemen', 'level' => 3]);

        $this->root = OrganizationUnit::create([
            'name' => 'Direktur Utama',
            'code' => 'DIRUT',
            'type_id' => $rootType->id,
            'is_active' => true,
        ]);

        $this->mid = OrganizationUnit::create([
            'name' => 'RS Sehat',
            'code' => 'RS',
            'type_id' => $midType->id,
            'parent_id' => $this->root->id,
            'is_active' => true,
        ]);

        $this->leaf = OrganizationUnit::create([
            'name' => 'Departemen SIRS',
            'code' => 'SIRS',
            'type_id' => $leafType->id,
            'parent_id' => $this->mid->id,
            'is_active' => true,
        ]);
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
    }

    protected function truncateTables(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach ([
            'activity_logs', 'organization_units', 'organization_types', 'users',
            'roles', 'permissions', 'role_permission',
        ] as $table) {
            DB::table($table)->delete();
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function test_ancestors_returns_eloquent_collection_from_nearest_parent(): void
    {
        $ancestors = $this->leaf->ancestors();

        $this->assertInstanceOf(EloquentCollection::class, $ancestors);
        $this->assertSame([$this->mid->id, $this->root->id], $ancestors->pluck('id')->all());
    }

    public function test_ancestors_is_empty_for_root_unit(): void
    {
        $ancestors = $this->root->ancestors();

        $this->assertInstanceOf(EloquentCollection::class, $ancestors);
        $this->assertTrue($ancestors->isEmpty());
    }

    public function test_full_path_attribute_joins_the_hierarchy(): void
    {
        $this->assertSame('Direktur Utama > RS Sehat > Departemen SIRS', $this->leaf->full_path);
        $this->assertSame('Direktur Utama', $this->root->full_path);
    }

    public function test_edit_page_renders_full_path_without_type_error(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('organization-units.edit', $this->leaf));

        $response->assertOk();
        $response->assertSee('Edit Unit Organisasi');
        $response->assertSee('Direktur Utama > RS Sehat > Departemen SIRS');
    }
}
