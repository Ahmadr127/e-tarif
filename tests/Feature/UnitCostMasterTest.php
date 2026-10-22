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

    public function test_master_page_shows_component_and_parameter_crud(): void
    {
        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master', ['section' => 'components']))
            ->assertOk()
            ->assertSee('Master Komponen')
            ->assertSee('Tambah Komponen')
            ->assertSee('Daftar Komponen');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master', ['section' => 'parameters']))
            ->assertOk()
            ->assertSee('Master Parameter')
            ->assertSee('Tambah Parameter')
            ->assertSee('Kode Parameter')
            ->assertSee('{luas_bangunan_keseluruhan}');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master', ['section' => 'formula-history']))
            ->assertOk()
            ->assertSee('Riwayat Versi Formula');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master.components.create'))
            ->assertOk()
            ->assertSee('Kode Komponen');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master.parameters.create'))
            ->assertOk()
            ->assertSee('Kode Parameter')
            ->assertSee('Kode ini juga menjadi variabel global')
            ->assertSee('Metode Input Default')
            ->assertSee('Default Value / Rumus Default')
            ->assertDontSee('Variable Global')
            ->assertDontSee('Nama Sumber Data')
            ->assertDontSee('Sumber Data')
            ->assertDontSee('Deskripsi')
            ->assertDontSee('Urutan');
    }

    public function test_parameter_default_input_method_and_value_are_saved(): void
    {
        $component = UnitCostComponent::create([
            'code' => 'TEST-DEFAULT-COMP',
            'name' => 'Komponen Default',
            'is_active' => true,
        ]);

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.master.parameters.store'), [
                'component_id' => $component->id,
                'code' => 'test_default_parameter',
                'name' => 'Parameter Default',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'default_input_type' => 'formula',
                'default_value' => '{test_default_parameter} * 2',
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.master', ['section' => 'parameters']));

        $parameter = UnitCostParameter::query()->where('code', 'test_default_parameter')->firstOrFail();
        $this->assertSame('formula', $parameter->default_input_type);
        $this->assertSame('{test_default_parameter} * 2', $parameter->default_value);

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master.parameters.edit', $parameter))
            ->assertOk()
            ->assertSee('Input Rumus')
            ->assertSee('{test_default_parameter} * 2', false);

        $this->actingAs($this->unitCostUser)
            ->put(route('calculations.master.parameters.update', $parameter), [
                'component_id' => $component->id,
                'code' => 'test_default_parameter',
                'name' => 'Parameter Default',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'default_input_type' => 'value',
                'default_value' => '12',
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.master', ['section' => 'parameters']));

        $parameter->refresh();
        $this->assertSame('value', $parameter->default_input_type);
        $this->assertSame('12', $parameter->default_value);
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
            ->assertRedirect(route('calculations.master', ['section' => 'components']));

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
            ->assertRedirect(route('calculations.master', ['section' => 'components']));

        $component->refresh();
        $this->assertSame('Komponen Diperbarui', $component->name);
        $this->assertSame(8, $component->sort_order);
        $this->assertFalse($component->is_active);

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.master.components.destroy', $component))
            ->assertRedirect(route('calculations.master', ['section' => 'components']));

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
            'name' => 'Parameter Komponen',
            'value_type' => 'decimal',
            'scope' => 'unit_period',
            'source_type' => 'manual',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.master.components.destroy', $component))
            ->assertRedirect(route('calculations.master', ['section' => 'components']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('unit_cost_components', ['id' => $component->id]);
    }

    public function test_parameter_crud_uses_code_as_global_variable(): void
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
                'name' => 'Parameter Baru',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.master', ['section' => 'parameters']));

        $parameter = UnitCostParameter::query()->where('code', 'test_parameter_code')->firstOrFail();
        $initialSortOrder = $parameter->sort_order;

        $this->assertSame('test_parameter_code', $parameter->variable);
        $this->assertSame('test_parameter_code', $parameter->formulaKey());
        $this->assertSame('manual', $parameter->source_type);
        $this->assertNull($parameter->source_name);
        $this->assertNull($parameter->description);
        $this->assertGreaterThan(0, $initialSortOrder);

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master.parameters.edit', $parameter))
            ->assertOk()
            ->assertSee('{test_parameter_code}')
            ->assertSee('Kode ini juga menjadi variabel global')
            ->assertDontSee('Variable Global');

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.master.parameters.store'), [
                'component_id' => $component->id,
                'code' => 'test_parameter_code',
                'name' => 'Parameter Duplikat',
                'value_type' => 'decimal',
                'scope' => 'unit_period',
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->unitCostUser)
            ->put(route('calculations.master.parameters.update', $parameter), [
                'component_id' => $component->id,
                'code' => 'test_parameter_code_updated',
                'name' => 'Parameter Diperbarui',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.master', ['section' => 'parameters']));

        $parameter->refresh();
        $this->assertSame('test_parameter_code_updated', $parameter->code);
        $this->assertSame('test_parameter_code_updated', $parameter->variable);
        $this->assertSame('test_parameter_code_updated', $parameter->formulaKey());
        $this->assertSame('Parameter Diperbarui', $parameter->name);
        $this->assertSame($initialSortOrder, $parameter->sort_order);

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master', ['section' => 'parameters', 'search' => 'test_parameter_code_updated']))
            ->assertOk()
            ->assertSee('{test_parameter_code_updated}');

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.master.parameters.destroy', $parameter))
            ->assertRedirect(route('calculations.master', ['section' => 'parameters']));

        $this->assertDatabaseMissing('unit_cost_parameters', ['id' => $parameter->id]);
    }

    public function test_parameter_can_be_added_from_period_and_returns_to_period_form(): void
    {
        $component = UnitCostComponent::create([
            'code' => 'TEST-PERIOD-COMP',
            'name' => 'Komponen Periode',
            'is_active' => true,
        ]);

        $period = UnitCostPeriod::create([
            'code' => 'UC-ADD-PARAM',
            'name' => 'Periode Tambah Parameter',
            'period_year' => 2026,
            'status' => 'draft',
            'created_by' => $this->unitCostUser->id,
        ]);

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.master.parameters.store'), [
                'period_id' => $period->id,
                'unit_id' => '0',
                'component_id' => $component->id,
                'code' => 'test_parameter_from_period',
                'name' => 'Parameter dari Periode',
                'value_type' => 'decimal',
                'unit' => 'rupiah',
                'scope' => 'unit_period',
                'is_required' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('calculations.parameters.edit', [
                'period' => $period,
                'component' => $component->id,
                'unit_id' => '0',
            ]));

        $this->assertDatabaseHas('unit_cost_parameters', [
            'code' => 'test_parameter_from_period',
            'component_id' => $component->id,
        ]);
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
            ->assertRedirect(route('calculations.master', ['section' => 'parameters']))
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
