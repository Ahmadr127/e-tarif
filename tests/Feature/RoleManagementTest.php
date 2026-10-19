<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Halaman /roles: CRUD role lewat modal + matriks permission (checkbox
 * per kolom role). Skema dibuat manual mengikuti test fitur lain di repo ini.
 */
class RoleManagementTest extends TestCase
{
    protected Role $adminRole;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
        $this->createTables();
        $this->truncateTables();

        $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $this->adminRole->permissions()->attach(
            Permission::create(['name' => 'manage_roles', 'display_name' => 'Kelola Roles'])->id
        );

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role_id' => $this->adminRole->id,
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
                $table->rememberToken();
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
            'activity_logs', 'users', 'role_permission', 'permissions', 'roles',
        ] as $table) {
            DB::table($table)->delete();
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function test_index_renders_modal_button_and_permission_matrix(): void
    {
        $operator = Role::create(['name' => 'operator', 'display_name' => 'Operator']);

        $response = $this->actingAs($this->admin)->get(route('roles.index'));

        $response->assertOk();
        // Tambah role lewat modal (bukan form di kolom kiri).
        $response->assertSee('Tambah Role');
        $response->assertSee('roleModal', false);
        $response->assertDontSee('Atur Permissions');

        // Matriks: permission sebagai baris, role sebagai kolom.
        $response->assertSee('Kelola Permission');
        $response->assertSee('name="permissions['.$this->adminRole->id.'][]"', false);
        $response->assertSee('name="permissions['.$operator->id.'][]"', false);
        $response->assertSee('Kelola Roles');
    }

    public function test_matrix_groups_permissions_by_prefix(): void
    {
        Permission::create(['name' => 'view_dashboard', 'display_name' => 'Lihat Dashboard']);
        Permission::create(['name' => 'delete_tarifs', 'display_name' => 'Hapus Tarif']);

        $response = $this->actingAs($this->admin)->get(route('roles.index'));

        $response->assertOk();
        $response->assertSee('Kelola');
        $response->assertSee('Lihat');
        $response->assertSee('Hapus');
    }

    public function test_store_creates_role_from_modal_payload(): void
    {
        $permission = Permission::create(['name' => 'view_dashboard', 'display_name' => 'Lihat Dashboard']);

        $response = $this->actingAs($this->admin)->post(route('roles.store'), [
            'name' => 'operator',
            'display_name' => 'Operator',
            'description' => 'Role operator',
            'is_active' => '1',
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHasNoErrors();

        $role = Role::where('name', 'operator')->firstOrFail();
        $this->assertSame('Operator', $role->display_name);
        $this->assertTrue($role->is_active);
        $this->assertTrue($role->permissions->contains('id', $permission->id));
    }

    public function test_update_changes_role_fields(): void
    {
        $role = Role::create(['name' => 'operator', 'display_name' => 'Operator']);

        $response = $this->actingAs($this->admin)->put(route('roles.update', $role), [
            'name' => 'operator',
            'display_name' => 'Operator Unit',
            'description' => 'Diperbarui',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('roles.index'));
        $role->refresh();
        $this->assertSame('Operator Unit', $role->display_name);
        $this->assertFalse($role->is_active);
    }

    public function test_sync_permissions_applies_checkbox_matrix_for_all_roles(): void
    {
        $view = Permission::create(['name' => 'view_dashboard', 'display_name' => 'Lihat Dashboard']);
        $manage = Permission::create(['name' => 'manage_users', 'display_name' => 'Kelola Users']);

        $operator = Role::create(['name' => 'operator', 'display_name' => 'Operator']);
        $operator->permissions()->attach($manage->id);

        $response = $this->actingAs($this->admin)->put(route('roles.permissions.sync'), [
            'permissions' => [
                $this->adminRole->id => [$view->id, $manage->id],
                $operator->id => [$view->id],
            ],
        ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$view->id, $manage->id],
            $this->adminRole->permissions()->pluck('permissions.id')->all()
        );
        $this->assertSame(
            [$view->id],
            $operator->permissions()->pluck('permissions.id')->all()
        );
    }

    public function test_sync_permissions_clears_role_that_is_not_checked(): void
    {
        $permission = Permission::create(['name' => 'view_dashboard', 'display_name' => 'Lihat Dashboard']);

        $operator = Role::create(['name' => 'operator', 'display_name' => 'Operator']);
        $operator->permissions()->attach($permission->id);

        $this->actingAs($this->admin)->put(route('roles.permissions.sync'), [
            'permissions' => [
                $this->adminRole->id => [$permission->id],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, $operator->permissions()->get());
    }

    public function test_sync_permissions_rejects_unknown_permission(): void
    {
        $this->actingAs($this->admin)->put(route('roles.permissions.sync'), [
            'permissions' => [
                $this->adminRole->id => [99999],
            ],
        ])->assertSessionHasErrors('permissions.'.$this->adminRole->id.'.0');
    }

    public function test_sync_permissions_requires_manage_roles_permission(): void
    {
        $other = Role::create(['name' => 'operator', 'display_name' => 'Operator']);

        $user = User::create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => 'secret',
            'role_id' => $other->id,
        ]);

        $this->actingAs($user)
            ->put(route('roles.permissions.sync'), ['permissions' => []])
            ->assertForbidden();
    }
}
