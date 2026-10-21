<?php

namespace Tests\Feature;

use App\Http\Services\Calculation\DependencyResolver;
use App\Http\Services\Calculation\FormulaParser;
use App\Models\CalculationFormula;
use App\Models\CalculationVariable;
use App\Models\Role;
use Database\Seeders\CalculationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\CalculationTestSchema;
use Tests\TestCase;

class CalculationSchemaTest extends TestCase
{
    use CalculationTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCalculation();
    }

    public function test_migration_membuat_seluruh_tabel_dan_kolom_penting(): void
    {
        foreach ([
            'cost_components',
            'calculation_variables',
            'calculation_formulas',
            'calculation_runs',
            'calculation_details',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak dibuat.");
        }

        $this->assertTrue(Schema::hasColumns('calculation_formulas', [
            'code',
            'expression',
            'version',
            'effective_from',
            'effective_until',
        ]));

        $this->assertTrue(Schema::hasColumns('calculation_runs', [
            'organization_unit_id',
            'unit_code',
            'period_year',
            'status',
            'formula_version',
            'parameter_snapshot',
        ]));

        $this->assertTrue(Schema::hasColumns('calculation_details', [
            'calculation_run_id',
            'formula_id',
            'formula_version',
            'input_snapshot',
            'result_value',
        ]));
    }

    public function test_seeder_idempotent_dan_label_bahasa_indonesia(): void
    {
        $componentCount = DB::table('cost_components')->count();
        $variableCount = DB::table('calculation_variables')->count();
        $formulaCount = DB::table('calculation_formulas')->count();

        $this->seed(CalculationSeeder::class);

        $this->assertSame($componentCount, DB::table('cost_components')->count());
        $this->assertSame($variableCount, DB::table('calculation_variables')->count());
        $this->assertSame($formulaCount, DB::table('calculation_formulas')->count());

        $this->assertDatabaseHas('cost_components', [
            'code' => 'beds',
            'name' => 'Tempat Tidur Pasien',
        ]);

        $this->assertDatabaseHas('calculation_variables', [
            'code' => 'bed_count',
            'name' => 'Jumlah tempat tidur',
            'unit' => 'tempat tidur',
        ]);
    }

    public function test_kode_formula_boleh_punya_beberapa_versi_tetapi_tidak_boleh_duplikat(): void
    {
        $formula = CalculationFormula::query()
            ->where('code', 'average_patients_per_day')
            ->where('version', 1)
            ->firstOrFail();

        CalculationFormula::create([
            'code' => $formula->code,
            'name' => 'Rata-rata pasien per hari (versi 2)',
            'component_id' => $formula->component_id,
            'expression' => 'annual_patient_count / 200',
            'result_unit' => 'pasien/hari',
            'result_scope' => 'unit_period',
            'version' => 2,
            'is_active' => true,
        ]);

        $this->assertSame(2, CalculationFormula::query()
            ->where('code', 'average_patients_per_day')
            ->count());

        $this->expectException(QueryException::class);

        CalculationFormula::create([
            'code' => $formula->code,
            'name' => 'Duplikat versi 2',
            'expression' => 'annual_patient_count / 300',
            'version' => 2,
            'is_active' => true,
        ]);
    }

    public function test_semua_formula_seeder_dapat_diparse_dan_referensinya_valid(): void
    {
        $parser = new FormulaParser;
        $formulas = CalculationFormula::query()->where('is_active', true)->orderBy('code')->get();
        $formulaCodes = $formulas->pluck('code')->all();
        $variables = CalculationVariable::query()->where('is_active', true)->get()->keyBy('code');

        $this->assertNotEmpty($formulas);

        foreach ($formulas as $formula) {
            $dependencies = $parser->dependencies((string) $formula->expression);

            foreach ($dependencies as $dependency) {
                $this->assertTrue(
                    in_array($dependency, $formulaCodes, true) || $variables->has($dependency),
                    "Formula '{$formula->code}' mereferensikan '{$dependency}' yang tidak terdaftar."
                );

                if ($variables->has($dependency) && $variables->get($dependency)->source_type === CalculationVariable::SOURCE_DERIVED) {
                    $this->assertContains(
                        $dependency,
                        $formulaCodes,
                        "Variabel turunan '{$dependency}' tidak memiliki formula aktif."
                    );
                }
            }
        }

        $ordered = (new DependencyResolver($parser))->resolve($formulas);

        $this->assertCount($formulas->count(), $ordered);
    }

    public function test_permission_migration_menambahkan_izin_ke_role_admin(): void
    {
        $admin = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
        ]);

        $migration = require database_path(
            'migrations/2026_10_21_000002_add_calculation_permissions.php'
        );
        $migration->up();

        $manageId = DB::table('permissions')->where('name', 'manage_calculations')->value('id');
        $approveId = DB::table('permissions')->where('name', 'approve_calculations')->value('id');

        $this->assertNotNull($manageId);
        $this->assertNotNull($approveId);

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $admin->id,
            'permission_id' => $manageId,
        ]);

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $admin->id,
            'permission_id' => $approveId,
        ]);
    }
}
