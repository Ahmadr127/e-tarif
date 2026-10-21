<?php

namespace Tests\Feature\Concerns;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\CalculationSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Skema minimal untuk test calculation engine.
 *
 * Test suite proyek ini tidak memakai RefreshDatabase karena sebagian
 * migration memakai fitur PostgreSQL. Karena itu tabel dependency dibuat
 * manual, lalu migration calculation engine dijalankan dari file aslinya agar
 * skema yang diuji tetap sama dengan skema aplikasi.
 */
trait CalculationTestSchema
{
    protected User $calculationUser;

    protected OrganizationUnit $calculationUnit;

    protected function setUpCalculation(): void
    {
        $this->withoutVite();
        $this->createCalculationBaseTables();
        $this->runCalculationMigration();
        $this->seed(CalculationSeeder::class);

        $type = OrganizationType::create([
            'name' => 'unit-kerja',
            'display_name' => 'Unit Kerja',
            'level' => 3,
        ]);

        $this->calculationUnit = OrganizationUnit::create([
            'name' => 'Keperawatan',
            'code' => 'KPR',
            'type_id' => $type->id,
            'is_active' => true,
        ]);

        $this->calculationUser = User::create([
            'name' => 'Admin Kalkulasi',
            'email' => 'calculation@example.com',
            'password' => 'secret',
        ]);
    }

    private function createCalculationBaseTables(): void
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

    private function runCalculationMigration(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'calculation_variable_values',
            'calculation_details',
            'calculation_runs',
            'calculation_formulas',
            'calculation_variables',
            'cost_components',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        $migration = require database_path(
            'migrations/2026_10_21_000001_create_unit_cost_calculation_tables.php'
        );
        $migration->up();

        $valuesMigration = require database_path(
            'migrations/2026_10_21_000003_create_calculation_variable_values_table.php'
        );
        $valuesMigration->up();
    }
}
