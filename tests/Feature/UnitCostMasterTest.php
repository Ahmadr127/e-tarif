<?php

namespace Tests\Feature;

use App\Models\UnitCostComponent;
use App\Models\UnitCostParameter;
use App\Models\UnitCostParameterEntry;
use App\Models\UnitCostPeriod;
use Tests\Feature\Concerns\UnitCostTestSchema;
use Tests\TestCase;

class UnitCostMasterTest extends TestCase
{
    use UnitCostTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUnitCost();
    }

    public function test_master_page_shows_component_and_parameter_crud_and_global_variables(): void
    {
        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master'))
            ->assertOk()
            ->assertSee('Master Komponen dan Parameter')
            ->assertSee('Variabel Global')
            ->assertSee('Tambah Komponen')
            ->assertSee('Tambah Parameter')
            ->assertSee('{luas_bangunan_keseluruhan}');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master.components.create'))
            ->assertOk()
            ->assertSee('Kode Komponen');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master.parameters.create'))
            ->assertOk()
            ->assertSee('Variable Global')
            ->assertSee('Kode Parameter');
    }

    public function test_component_crud(): void
    {
        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.master.components.store'), [
                'code' => 'TEST-COMP',
                'name' => 'Komponen Baru',
                'category' => 'Test',
                'description' => 'Komponen untuk test.',
                'sort_order' => 7,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.master'));

        $component = UnitCostComponent::query()->where('code', 'TEST-COMP')->firstOrFail();
        $this->assertSame('Komponen Baru', $component->name);
        $this->assertSame(7, $component->sort_order);
        $this->assertTrue($component->is_active);

        $this->actingAs($this->unitCostUser)
            ->put(route('calculations.master.components.update', $component), [
                'code' => 'TEST-COMP',
                'name' => 'Komponen Diperbarui',
                'category' => 'Test',
                'sort_order' => 8,
                'is_active' => 0,
            ])
            ->assertRedirect(route('calculations.master'));

        $component->refresh();
        $this->assertSame('Komponen Diperbarui', $component->name);
        $this->assertSame(8, $component->sort_order);
        $this->assertFalse($component->is_active);

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.master.components.destroy', $component))
            ->assertRedirect(route('calculations.master'));

        $this->assertDatabaseMissing('unit_cost_components', ['id' => $component->id]);
    }

    public function test_component_with_parameters_cannot_be_deleted(): void
    {
        $component = UnitCostComponent::create([
            'code' => 'TEST-COMP-PARAM',
            'name' => 'Komponen Dengan Parameter',
            'is_active' => true,
        ]);

        UnitCostParameter::create([
            'component_id' => $component->id,
            'code' => 'test_component_parameter',
            'variable' => 'test_component_variable',
            'name' => 'Parameter Komponen',
            'value_type' => 'decimal',
            'scope' => 'unit_period',
            'source_type' => 'manual',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.master.components.destroy', $component))
            ->assertRedirect(route('calculations.master'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('unit_cost_components', ['id' => $component->id]);
    }

    public function test_parameter_crud_uses_unique_global_variable(): void
    {
        $component = UnitCostComponent::create([
            'code' => 'TEST-PARAM-COMP',
            'name' => 'Komponen Parameter',
            'is_active' => true,
        ]);

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.master.parameters.store'), [
                'component_id' => $component->id,
                'code' => 'test_parameter_code',
                'variable' => 'test_parameter_global',
                'name' => 'Parameter Baru',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'source_name' => 'Keuangan',
                'description' => 'Parameter untuk test.',
                'sort_order' => 12,
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.master'));

        $parameter = UnitCostParameter::query()->where('code', 'test_parameter_code')->firstOrFail();
        $this->assertSame('test_parameter_global', $parameter->variable);
        $this->assertSame('test_parameter_global', $parameter->formulaKey());

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master.parameters.edit', $parameter))
            ->assertOk()
            ->assertSee('{test_parameter_global}')
            ->assertSee('Variable Global');

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.master.parameters.store'), [
                'component_id' => $component->id,
                'code' => 'test_parameter_code_duplicate',
                'variable' => 'test_parameter_global',
                'name' => 'Parameter Duplikat',
                'value_type' => 'decimal',
                'scope' => 'unit_period',
                'source_type' => 'manual',
            ])
            ->assertSessionHasErrors('variable');

        $this->actingAs($this->unitCostUser)
            ->put(route('calculations.master.parameters.update', $parameter), [
                'component_id' => $component->id,
                'code' => 'test_parameter_code',
                'variable' => 'test_parameter_global_updated',
                'name' => 'Parameter Diperbarui',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'source_type' => 'manual',
                'sort_order' => 13,
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.master'));

        $parameter->refresh();
        $this->assertSame('test_parameter_global_updated', $parameter->variable);
        $this->assertSame('Parameter Diperbarui', $parameter->name);

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master'))
            ->assertOk()
            ->assertSee('{test_parameter_global_updated}');

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.master.parameters.destroy', $parameter))
            ->assertRedirect(route('calculations.master'));

        $this->assertDatabaseMissing('unit_cost_parameters', ['id' => $parameter->id]);
    }

    public function test_parameter_with_entries_cannot_be_deleted(): void
    {
        $component = UnitCostComponent::create([
            'code' => 'TEST-ENTRY-COMP',
            'name' => 'Komponen Entry',
            'is_active' => true,
        ]);

        $parameter = UnitCostParameter::create([
            'component_id' => $component->id,
            'code' => 'test_entry_parameter',
            'variable' => 'test_entry_variable',
            'name' => 'Parameter Entry',
            'value_type' => 'decimal',
            'scope' => 'global',
            'source_type' => 'manual',
            'is_required' => true,
            'is_active' => true,
        ]);

        $period = UnitCostPeriod::create([
            'code' => 'UC-MASTER-TEST',
            'name' => 'Periode Master Test',
            'period_year' => 2026,
            'status' => 'draft',
            'created_by' => $this->unitCostUser->id,
        ]);

        UnitCostParameterEntry::create([
            'period_id' => $period->id,
            'parameter_id' => $parameter->id,
            'unit_id' => null,
            'scope_key' => UnitCostParameterEntry::scopeKeyFor($period->id, $parameter->id, null),
            'input_type' => 'value',
            'input_value' => 10,
            'result_value' => 10,
            'validation_status' => 'valid',
        ]);

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.master.parameters.destroy', $parameter))
            ->assertRedirect(route('calculations.master'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('unit_cost_parameters', ['id' => $parameter->id]);
    }

    public function test_master_crud_permission_is_required(): void
    {
        $viewer = $this->makeUnitCostUser(['view_unit_cost_results'], 'master-viewer@example.com');

        $this->actingAs($viewer)
            ->get(route('calculations.master'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('calculations.master.components.create'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('calculations.master.parameters.store'), [])
            ->assertForbidden();
    }
}
