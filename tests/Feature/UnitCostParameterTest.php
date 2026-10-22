<?php

namespace Tests\Feature;

use App\Models\UnitCostComponent;
use App\Models\UnitCostParameter;
use App\Models\UnitCostParameterEntry;
use App\Models\UnitCostPeriod;
use App\Models\UnitCostResultSnapshot;
use App\Services\UnitCost\ParameterEvaluationService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\UnitCostTestSchema;
use Tests\TestCase;

class UnitCostParameterTest extends TestCase
{
    use UnitCostTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUnitCost();

        // Test parameter fokus memakai komponen sendiri agar tidak bergantung
        // pada seluruh master contoh.
        UnitCostParameter::query()->update(['is_active' => false]);
    }

    public function test_save_value_and_formula_chain(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'test_b', 'B', sortOrder: 20, sourceType: 'derived');
        $c = $this->makeParameter($component, 'test_c', 'C', sortOrder: 30, sourceType: 'derived');
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 2'],
            $c->id => ['input_type' => 'formula', 'formula_text' => '{test_b} + 5'],
        ])->assertRedirect();

        $this->assertEntry($period, $a, $this->unitCostUnit, 'valid', 10.0);
        $this->assertEntry($period, $b, $this->unitCostUnit, 'valid', 20.0);
        $this->assertEntry($period, $c, $this->unitCostUnit, 'valid', 25.0);
        $this->assertSame(2, DB::table('unit_cost_formula_versions')->count());
    }

    public function test_invalid_formula_is_preserved_and_old_result_is_not_used(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'test_b', 'B', sortOrder: 20, sourceType: 'derived');
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 2'],
        ])->assertRedirect();

        $this->assertEntry($period, $b, $this->unitCostUnit, 'valid', 20.0);

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} +'],
        ])->assertRedirect();

        $invalid = $this->entryFor($period, $b, $this->unitCostUnit);
        $this->assertSame('{test_a} +', $invalid->formula_text);
        $this->assertSame(UnitCostParameterEntry::STATUS_INVALID, $invalid->validation_status);
        $this->assertNull($invalid->result_value);
        $this->assertNotNull($invalid->formula_version_id);

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} + 3'],
        ])->assertRedirect();

        $this->assertEntry($period, $b, $this->unitCostUnit, 'valid', 13.0);
        $this->assertSame(3, DB::table('unit_cost_formula_versions')->count());
    }

    public function test_unknown_reference_cycle_and_division_by_zero_are_invalid(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $unknown = $this->makeParameter($component, 'test_unknown', 'Unknown', sortOrder: 20, sourceType: 'derived');
        $cycleA = $this->makeParameter($component, 'test_cycle_a', 'Cycle A', sortOrder: 30, sourceType: 'derived');
        $cycleB = $this->makeParameter($component, 'test_cycle_b', 'Cycle B', sortOrder: 40, sourceType: 'derived');
        $divide = $this->makeParameter($component, 'test_divide', 'Divide', sortOrder: 50, sourceType: 'derived');
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $unknown->id => ['input_type' => 'formula', 'formula_text' => '{tidak_ada} + 1'],
            $cycleA->id => ['input_type' => 'formula', 'formula_text' => '{test_cycle_b} + 1'],
            $cycleB->id => ['input_type' => 'formula', 'formula_text' => '{test_cycle_a} + 1'],
            $divide->id => ['input_type' => 'formula', 'formula_text' => '{test_a} / 0'],
        ])->assertRedirect();

        $unknownEntry = $this->entryFor($period, $unknown, $this->unitCostUnit);
        $this->assertSame('invalid', $unknownEntry->validation_status);
        $this->assertStringContainsString('tidak ditemukan', $unknownEntry->validation_error);

        $cycleEntryA = $this->entryFor($period, $cycleA, $this->unitCostUnit);
        $cycleEntryB = $this->entryFor($period, $cycleB, $this->unitCostUnit);
        $this->assertSame('invalid', $cycleEntryA->validation_status);
        $this->assertSame('invalid', $cycleEntryB->validation_status);
        $this->assertStringContainsString('Siklus', $cycleEntryA->validation_error);

        $divideEntry = $this->entryFor($period, $divide, $this->unitCostUnit);
        $this->assertSame('invalid', $divideEntry->validation_status);
        $this->assertStringContainsString('Pembagian dengan nol', $divideEntry->validation_error);
    }

    public function test_formula_uses_parameter_code_as_global_variable(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'param_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'param_b', 'B', sortOrder: 20, sourceType: 'derived');

        // Kolom lama `variable` sengaja dibuat berbeda untuk membuktikan formula memakai `code`.
        $a->forceFill(['variable' => 'var_a'])->saveQuietly();
        $b->forceFill(['variable' => 'var_b'])->saveQuietly();

        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{param_a} * 2'],
        ])->assertRedirect();

        $this->assertEntry($period, $b, $this->unitCostUnit, 'valid', 20.0);

        $evaluation = app(ParameterEvaluationService::class)->evaluate($period, $this->unitCostUnit);
        $this->assertSame(10.0, $evaluation->values['param_a']);
        $this->assertSame(20.0, $evaluation->values['param_b']);
        $this->assertArrayNotHasKey('var_a', $evaluation->values);
        $this->assertArrayNotHasKey('var_b', $evaluation->values);

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{var_a} * 2'],
        ])->assertRedirect();

        $invalid = $this->entryFor($period, $b, $this->unitCostUnit);
        $this->assertSame(UnitCostParameterEntry::STATUS_INVALID, $invalid->validation_status);
        $this->assertNull($invalid->result_value);
        $this->assertStringContainsString('var_a', (string) $invalid->validation_error);
    }

    public function test_missing_dependency_is_incomplete_not_zero(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'test_b', 'B', sortOrder: 20, sourceType: 'derived');
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => ''],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 2'],
        ])->assertRedirect();

        $entryA = $this->entryFor($period, $a, $this->unitCostUnit);
        $entryB = $this->entryFor($period, $b, $this->unitCostUnit);

        $this->assertSame('incomplete', $entryA->validation_status);
        $this->assertSame('incomplete', $entryB->validation_status);
        $this->assertNull($entryA->result_value);
        $this->assertNull($entryB->result_value);
        $this->assertStringContainsString('belum lengkap', mb_strtolower((string) $entryB->validation_error));
    }

    public function test_period_and_unit_entries_are_isolated(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A');
        $periodA = $this->makePeriod('UC-A', 'Periode A');
        $periodB = $this->makePeriod('UC-B', 'Periode B');

        $this->save($periodA, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
        ])->assertRedirect();

        $this->save($periodA, $this->unitCostUnitB, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 20],
        ])->assertRedirect();

        $this->save($periodB, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 30],
        ])->assertRedirect();

        $this->assertSame(10.0, (float) $this->entryFor($periodA, $a, $this->unitCostUnit)->input_value);
        $this->assertSame(20.0, (float) $this->entryFor($periodA, $a, $this->unitCostUnitB)->input_value);
        $this->assertSame(30.0, (float) $this->entryFor($periodB, $a, $this->unitCostUnit)->input_value);
    }

    public function test_method_change_clears_fields_but_keeps_formula_history(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'test_b', 'B', sortOrder: 20);
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 2'],
        ])->assertRedirect();

        $this->assertSame(1, DB::table('unit_cost_formula_versions')->count());

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'value', 'input_value' => 99],
        ])->assertRedirect();

        $entry = $this->entryFor($period, $b, $this->unitCostUnit);
        $this->assertSame('value', $entry->input_type);
        $this->assertSame(99.0, (float) $entry->input_value);
        $this->assertNull($entry->formula_text);
        $this->assertNull($entry->formula_version_id);
        $this->assertSame(1, DB::table('unit_cost_formula_versions')->count());

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 2'],
        ])->assertRedirect();

        $this->assertSame(1, DB::table('unit_cost_formula_versions')->count());
        $this->assertEntry($period, $b, $this->unitCostUnit, 'valid', 20.0);
    }

    public function test_preview_does_not_persist_entries_or_formula_versions(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'test_b', 'B', sortOrder: 20, sourceType: 'derived');
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
        ])->assertRedirect();

        $entryCount = DB::table('unit_cost_parameter_entries')->count();
        $versionCount = DB::table('unit_cost_formula_versions')->count();

        $response = $this->actingAs($this->unitCostUser)->postJson(route('calculations.parameters.preview'), [
            'period_id' => $period->id,
            'unit_id' => $this->unitCostUnit->id,
            'component' => (string) $component->id,
            'entries' => [
                $a->id => ['input_type' => 'value', 'input_value' => 10],
                $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 4'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('rows.'.$b->id.'.status', 'valid')
            ->assertJsonPath('rows.'.$b->id.'.result_value', 40);

        $this->assertSame($entryCount, DB::table('unit_cost_parameter_entries')->count());
        $this->assertSame($versionCount, DB::table('unit_cost_formula_versions')->count());
        $this->assertDatabaseMissing('unit_cost_parameter_entries', [
            'period_id' => $period->id,
            'parameter_id' => $b->id,
        ]);
    }

    public function test_unrelated_formula_is_not_recalculated_when_another_component_changes(): void
    {
        $componentA = $this->makeComponent('TESTA');
        $componentB = $this->makeComponent('TESTB');
        $a = $this->makeParameter($componentA, 'test_a', 'A');
        $bInput = $this->makeParameter($componentB, 'test_b_input', 'B Input', sortOrder: 10);
        $bFormula = $this->makeParameter($componentB, 'test_b_formula', 'B Formula', sortOrder: 20, sourceType: 'derived');
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $componentA, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
        ])->assertRedirect();

        $this->save($period, $this->unitCostUnit, $componentB, [
            $bInput->id => ['input_type' => 'value', 'input_value' => 5],
            $bFormula->id => ['input_type' => 'formula', 'formula_text' => '{test_b_input} * 2'],
        ])->assertRedirect();

        $bEntry = $this->entryFor($period, $bFormula, $this->unitCostUnit);
        $bEntry->forceFill(['updated_at' => now()->subDay()])->save();
        $oldUpdatedAt = $bEntry->fresh()->updated_at;
        $oldResult = (float) $bEntry->fresh()->result_value;

        $this->save($period, $this->unitCostUnit, $componentA, [
            $a->id => ['input_type' => 'value', 'input_value' => 20],
        ])->assertRedirect();

        $bEntry->refresh();
        $this->assertTrue($bEntry->updated_at->equalTo($oldUpdatedAt));
        $this->assertSame($oldResult, (float) $bEntry->result_value);
        $this->assertSame(20.0, (float) $this->entryFor($period, $a, $this->unitCostUnit)->input_value);
    }

    public function test_derived_parameter_can_use_value_input_when_method_changed(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $derived = $this->makeParameter($component, 'test_derived', 'Derived', sortOrder: 20, sourceType: 'derived');
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 5],
            $derived->id => ['input_type' => 'value', 'input_value' => 7],
        ])->assertSessionHasNoErrors();

        $entry = $this->entryFor($period, $derived, $this->unitCostUnit);

        $this->assertSame('value', $entry->input_type);
        $this->assertSame(7.0, (float) $entry->input_value);
        $this->assertSame('valid', $entry->validation_status);
    }

    public function test_permission_view_and_edit_are_separated(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A');
        $period = $this->makePeriod();

        $viewer = $this->makeUnitCostUser(['view_unit_cost_results'], 'viewer-unit-cost@example.com');

        $this->actingAs($viewer)
            ->get(route('calculations.parameters.edit', [
                'period' => $period,
                'component' => $component->id,
                'unit_id' => $this->unitCostUnit->id,
            ]))
            ->assertOk();

        $this->actingAs($viewer)
            ->put(route('calculations.parameters.update', [
                'period' => $period,
                'component' => $component->id,
            ]), [
                'unit_id' => $this->unitCostUnit->id,
                'entries' => [
                    $a->id => ['input_type' => 'value', 'input_value' => 10],
                ],
            ])
            ->assertForbidden();

        $editor = $this->makeUnitCostUser(['edit_unit_cost_parameters'], 'editor-unit-cost@example.com');

        $this->actingAs($editor)
            ->put(route('calculations.parameters.update', [
                'period' => $period,
                'component' => $component->id,
            ]), [
                'unit_id' => $this->unitCostUnit->id,
                'entries' => [
                    $a->id => ['input_type' => 'value', 'input_value' => 10],
                ],
            ])
            ->assertRedirect();
    }

    public function test_scoped_user_only_edits_values_and_cannot_overwrite_formula(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'test_b', 'B', sortOrder: 20, sourceType: 'derived');
        $global = $this->makeParameter($component, 'test_global', 'Global', sortOrder: 30, scope: 'global');
        $period = $this->makePeriod();

        $this->unitCostUnit->unitCostComponents()->attach($component->id);

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 2'],
            $global->id => ['input_type' => 'value', 'input_value' => 5],
        ])->assertRedirect();

        $operator = $this->makeUnitCostUser(
            ['input_unit_cost'],
            'operator-unit-cost@example.com',
            $this->unitCostUnit,
        );

        $this->actingAs($operator)
            ->get(route('calculations.parameters.edit', [
                'period' => $period,
                'component' => $component->id,
                'unit_id' => $this->unitCostUnit->id,
            ]))
            ->assertOk()
            ->assertSee('Anda hanya dapat mengisi nilai.')
            ->assertDontSee('name="entries['.$b->id.'][formula_text]"', false)
            ->assertDontSee('name="entries['.$b->id.'][input_type]"', false)
            ->assertDontSee('name="entries['.$global->id.'][input_type]"', false);

        $this->actingAs($operator)
            ->put(route('calculations.parameters.update', [
                'period' => $period,
                'component' => $component->id,
            ]), [
                'unit_id' => $this->unitCostUnit->id,
                'entries' => [
                    $a->id => ['input_type' => 'value', 'input_value' => 20],
                    $b->id => ['input_type' => 'formula', 'formula_text' => '{test_a} * 99'],
                    $global->id => ['input_type' => 'value', 'input_value' => 999],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(20.0, (float) $this->entryFor($period, $a, $this->unitCostUnit)->input_value);
        $this->assertSame('{test_a} * 2', $this->entryFor($period, $b, $this->unitCostUnit)->formula_text);
        $this->assertSame(40.0, (float) $this->entryFor($period, $b, $this->unitCostUnit)->result_value);

        $globalEntry = UnitCostParameterEntry::query()
            ->where('period_id', $period->id)
            ->where('parameter_id', $global->id)
            ->whereNull('unit_id')
            ->firstOrFail();

        $this->assertSame(5.0, (float) $globalEntry->input_value);
    }

    public function test_scoped_user_cannot_access_global_or_unassigned_component(): void
    {
        $assigned = $this->makeComponent('ASSIGNED');
        $other = $this->makeComponent('OTHER');
        $this->unitCostUnit->unitCostComponents()->attach($assigned->id);

        $period = $this->makePeriod();

        $operator = $this->makeUnitCostUser(
            ['input_unit_cost'],
            'operator-scope-component@example.com',
            $this->unitCostUnit,
        );

        $this->actingAs($operator)
            ->get(route('calculations.parameters.edit', [
                'period' => $period,
                'component' => 'global',
                'unit_id' => $this->unitCostUnit->id,
            ]))
            ->assertForbidden();

        $this->actingAs($operator)
            ->get(route('calculations.parameters.edit', [
                'period' => $period,
                'component' => $other->id,
                'unit_id' => $this->unitCostUnit->id,
            ]))
            ->assertForbidden();
    }

    public function test_approval_creates_immutable_snapshot(): void
    {
        $component = $this->makeComponent();
        $a = $this->makeParameter($component, 'test_a', 'A', sortOrder: 10);
        $b = $this->makeParameter($component, 'test_b', 'B', sortOrder: 20);
        $period = $this->makePeriod();

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 10],
            $b->id => ['input_type' => 'value', 'input_value' => 20],
        ])->assertRedirect();

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.periods.approve', $period), [
                'unit_id' => $this->unitCostUnit->id,
            ])
            ->assertRedirect();

        $snapshot = UnitCostResultSnapshot::query()->firstOrFail();
        $payload = $snapshot->payload;

        $this->assertSame('approved', $snapshot->status);
        $this->assertSame(10.0, (float) $payload['values']['test_a']);
        $this->assertSame(20.0, (float) $payload['values']['test_b']);

        $this->save($period, $this->unitCostUnit, $component, [
            $a->id => ['input_type' => 'value', 'input_value' => 99],
            $b->id => ['input_type' => 'value', 'input_value' => 20],
        ])->assertRedirect();

        $snapshot->refresh();
        $this->assertSame(10.0, (float) $snapshot->payload['values']['test_a']);
        $this->assertSame(20.0, (float) $snapshot->payload['values']['test_b']);
    }

    private function makeComponent(string $code = 'TEST'): UnitCostComponent
    {
        return UnitCostComponent::create([
            'code' => $code,
            'name' => 'Komponen Test',
            'category' => 'Test',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeParameter(
        UnitCostComponent $component,
        string $code,
        string $name,
        int $sortOrder = 10,
        string $sourceType = 'manual',
        string $scope = 'unit_period',
        string $unit = 'rupiah',
        string $valueType = 'decimal',
    ): UnitCostParameter {
        return UnitCostParameter::create([
            'component_id' => $component->id,
            'code' => $code,
            'name' => $name,
            'value_type' => $valueType,
            'unit' => $unit,
            'scope' => $scope,
            'source_type' => $sourceType,
            'source_name' => 'Test',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private function makePeriod(string $code = 'UC-TEST', string $name = 'Periode Test'): UnitCostPeriod
    {
        return UnitCostPeriod::create([
            'code' => $code,
            'name' => $name,
            'period_year' => 2026,
            'status' => 'draft',
            'created_by' => $this->unitCostUser->id,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function save(UnitCostPeriod $period, $unit, UnitCostComponent $component, array $entries)
    {
        return $this->actingAs($this->unitCostUser)->put(
            route('calculations.parameters.update', [
                'period' => $period,
                'component' => $component->id,
            ]),
            [
                'unit_id' => $unit?->id,
                'entries' => $entries,
            ]
        );
    }

    private function entryFor(UnitCostPeriod $period, UnitCostParameter $parameter, $unit): UnitCostParameterEntry
    {
        return UnitCostParameterEntry::query()
            ->where('period_id', $period->id)
            ->where('parameter_id', $parameter->id)
            ->where('unit_id', $unit?->id)
            ->firstOrFail();
    }

    private function assertEntry(
        UnitCostPeriod $period,
        UnitCostParameter $parameter,
        $unit,
        string $status,
        float $result,
    ): void {
        $entry = $this->entryFor($period, $parameter, $unit);

        $this->assertSame($status, $entry->validation_status);
        $this->assertSame($result, (float) $entry->result_value);
    }
}
