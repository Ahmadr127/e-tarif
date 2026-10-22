<?php

namespace Tests\Feature\Concerns;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UnitCostSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema minimal untuk test alur Unit Cost.
 *
 * Test suite proyek ini tidak memakai RefreshDatabase karena sebagian
 * migration memakai fitur PostgreSQL. Tabel dependency dibuat manual, lalu
 * migration Unit Cost dijalankan dari file aslinya.
 */
trait UnitCostTestSchema
{
    protected User $unitCostUser;

    protected OrganizationUnit $unitCostUnit;

    protected OrganizationUnit $unitCostUnitB;

    protected Role $unitCostRole;

    protected function setUpUnitCost(): void
    {
        $this->withoutVite();
        $this->createUnitCostBaseTables();
        $this->runUnitCostMigration();
        $this->seed(UnitCostSeeder::class);

        $type = OrganizationType::create([
            'name' => 'unit-kerja',
            'display_name' => 'Unit Kerja',
            'level' => 3,
        ]);

        $this->unitCostUnit = OrganizationUnit::create([
            'name' => 'Keperawatan',
            'code' => 'KPR',
            'type_id' => $type->id,
            'is_active' => true,
        ]);

        $this->unitCostUnitB = OrganizationUnit::create([
            'name' => 'Radiologi',
            'code' => 'RAD',
            'type_id' => $type->id,
            'is_active' => true,
        ]);

        $permissions = [
            'manage_unit_cost',
            'input_unit_cost',
            'manage_unit_cost_periods',
            'manage_unit_cost_masters',
            'manage_unit_cost_components',
            'manage_unit_cost_parameters',
            'view_unit_cost_formula_history',
            'edit_unit_cost_parameters',
            'view_unit_cost_results',
            'approve_unit_cost_results',
            'manage_calculations',
            'approve_calculations',
        ];

        $permissionIds = [];

        foreach ($permissions as $permission) {
            $model = Permission::create([
                'name' => $permission,
                'display_name' => $permission,
                'description' => $permission,
            ]);
            $permissionIds[] = $model->id;
        }

        $this->unitCostRole = Role::create([
            'name' => 'unit-cost-admin',
            'display_name' => 'Unit Cost Admin',
            'is_active' => true,
        ]);

        $now = now();
        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $this->unitCostRole->id,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->unitCostUser = User::create([
            'name' => 'Admin Unit Cost',
            'email' => 'unit-cost@example.com',
            'password' => 'secret',
            'role_id' => $this->unitCostRole->id,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function makeUnitCostUser(
        array $permissions,
        string $email = 'unit-cost-limited@example.com',
        ?OrganizationUnit $unit = null,
    ): User {
        $role = Role::create([
            'name' => 'unit-cost-role-'.str_replace('.', '-', uniqid()),
            'display_name' => 'Unit Cost Limited',
            'is_active' => true,
        ]);

        $permissionIds = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $now = now();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return User::create([
            'name' => 'User '.$email,
            'email' => $email,
            'password' => 'secret',
            'role_id' => $role->id,
            'organization_unit_id' => $unit?->id,
        ]);
    }

    private function createUnitCostBaseTables(): void
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
    }

    private function runUnitCostMigration(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'unit_cost_component_unit',
            'unit_cost_result_snapshots',
            'unit_cost_parameter_entries',
            'unit_cost_formula_versions',
            'unit_cost_periods',
            'unit_cost_parameters',
            'unit_cost_components',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        $migration = require database_path(
            'migrations/2026_10_21_100000_create_unit_cost_tables.php'
        );
        $migration->up();

        $variableMigration = require database_path(
            'migrations/2026_10_21_100002_add_variable_to_unit_cost_parameters.php'
        );
        $variableMigration->up();

        $componentUnitMigration = require database_path(
            'migrations/2026_10_22_000002_create_unit_cost_component_unit_table.php'
        );
        $componentUnitMigration->up();
    }
}
