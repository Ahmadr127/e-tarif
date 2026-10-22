<?php

namespace Tests\Feature;

use App\Models\UnitCostComponent;
use App\Models\UnitCostPeriod;
use Tests\Feature\Concerns\UnitCostTestSchema;
use Tests\TestCase;

class UnitCostUnitAssignmentTest extends TestCase
{
    use UnitCostTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUnitCost();
    }

    public function test_menu_has_separate_admin_and_user_unit_cost_items(): void
    {
        $menus = collect(config('menu'))
            ->flatMap(static fn (array $group): array => $group['menus']);

        $admin = $menus->firstWhere('label', 'Unit Cost');
        $user = $menus->firstWhere('label', 'Unit Cost Saya');

        $this->assertSame('manage_unit_cost', $admin['permission']);
        $this->assertSame('calculations.units.index', $admin['route']);

        $this->assertSame('input_unit_cost', $user['permission']);
        $this->assertSame('calculations.my-unit', $user['route']);
    }

    public function test_unit_cost_index_lists_active_units_and_assignment_action(): void
    {
        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.units.index'))
            ->assertOk()
            ->assertSee('Keperawatan')
            ->assertSee('Radiologi')
            ->assertSee('Assign Komponen');
    }

    public function test_unit_cost_index_uses_selected_period_for_open_links(): void
    {
        $selected = UnitCostPeriod::create([
            'code' => 'UC-2026',
            'name' => 'Tahun Anggaran 2026',
            'period_year' => 2026,
            'status' => UnitCostPeriod::STATUS_ACTIVE,
            'created_by' => $this->unitCostUser->id,
        ]);

        $other = UnitCostPeriod::create([
            'code' => 'UC-2025',
            'name' => 'Tahun Anggaran 2025',
            'period_year' => 2025,
            'status' => UnitCostPeriod::STATUS_DRAFT,
            'created_by' => $this->unitCostUser->id,
        ]);

        $response = $this->actingAs($this->unitCostUser)
            ->get(route('calculations.units.index', ['period_id' => $selected->id]));

        $response->assertOk();
        $response->assertSee('name="period_id"', false);
        $response->assertSee(
            route('calculations.periods.show', ['period' => $selected, 'unit_id' => $this->unitCostUnit->id]),
            false
        );
        $response->assertDontSee(
            route('calculations.periods.show', ['period' => $other, 'unit_id' => $this->unitCostUnit->id]),
            false
        );
    }

    public function test_user_can_assign_components_to_a_unit(): void
    {
        $gaji = UnitCostComponent::query()->where('code', 'GAJI')->firstOrFail();
        $listrik = UnitCostComponent::query()->where('code', 'LISTRIK')->firstOrFail();

        $this->actingAs($this->unitCostUser)
            ->put(route('calculations.units.components.update', $this->unitCostUnit), [
                'component_ids' => [$gaji->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('unit_cost_component_unit', [
            'organization_unit_id' => $this->unitCostUnit->id,
            'unit_cost_component_id' => $gaji->id,
        ]);

        $this->assertDatabaseMissing('unit_cost_component_unit', [
            'organization_unit_id' => $this->unitCostUnit->id,
            'unit_cost_component_id' => $listrik->id,
        ]);
    }

    public function test_period_show_only_lists_components_assigned_to_selected_unit(): void
    {
        $gaji = UnitCostComponent::query()->where('code', 'GAJI')->firstOrFail();

        $this->unitCostUnitB->unitCostComponents()->sync([$gaji->id]);

        $period = UnitCostPeriod::create([
            'code' => 'UC-ASSIGN',
            'name' => 'Tahun Anggaran 2026',
            'period_year' => 2026,
            'status' => UnitCostPeriod::STATUS_DRAFT,
            'created_by' => $this->unitCostUser->id,
        ]);

        $operator = $this->makeUnitCostUser(
            ['input_unit_cost'],
            'operator-unit-b@example.com',
            $this->unitCostUnitB,
        );

        $this->actingAs($operator)
            ->get(route('calculations.periods.show', [
                'period' => $period,
                'unit_id' => $this->unitCostUnitB->id,
            ]))
            ->assertOk()
            ->assertSee('Gaji Pegawai')
            ->assertDontSee('Bed Pasien');
    }

    public function test_admin_period_show_lists_all_active_components(): void
    {
        $gaji = UnitCostComponent::query()->where('code', 'GAJI')->firstOrFail();

        $this->unitCostUnitB->unitCostComponents()->sync([$gaji->id]);

        $period = UnitCostPeriod::create([
            'code' => 'UC-ADMIN-ALL',
            'name' => 'Tahun Anggaran 2026',
            'period_year' => 2026,
            'status' => UnitCostPeriod::STATUS_DRAFT,
            'created_by' => $this->unitCostUser->id,
        ]);

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.periods.show', [
                'period' => $period,
                'unit_id' => $this->unitCostUnitB->id,
            ]))
            ->assertOk()
            ->assertSee('Gaji Pegawai')
            ->assertSee('Bed Pasien');
    }

    public function test_scoped_user_cannot_open_another_unit_from_url(): void
    {
        $period = UnitCostPeriod::create([
            'code' => 'UC-SCOPE',
            'name' => 'Tahun Anggaran 2026',
            'period_year' => 2026,
            'status' => UnitCostPeriod::STATUS_DRAFT,
            'created_by' => $this->unitCostUser->id,
        ]);

        $operator = $this->makeUnitCostUser(
            ['input_unit_cost'],
            'operator-scope@example.com',
            $this->unitCostUnit,
        );

        $this->actingAs($operator)
            ->get(route('calculations.periods.show', [
                'period' => $period,
                'unit_id' => $this->unitCostUnitB->id,
            ]))
            ->assertForbidden();
    }

    public function test_my_unit_redirects_to_active_period_and_own_unit(): void
    {
        $active = UnitCostPeriod::create([
            'code' => 'UC-ACTIVE-SAYA',
            'name' => 'Tahun Anggaran 2026',
            'period_year' => 2026,
            'status' => UnitCostPeriod::STATUS_ACTIVE,
            'created_by' => $this->unitCostUser->id,
        ]);

        $operator = $this->makeUnitCostUser(
            ['input_unit_cost'],
            'operator-saya@example.com',
            $this->unitCostUnit,
        );

        $this->actingAs($operator)
            ->get(route('calculations.my-unit'))
            ->assertRedirect(route('calculations.periods.show', [
                'period' => $active,
                'unit_id' => $this->unitCostUnit->id,
            ]));
    }

    public function test_unit_assignment_requires_unit_cost_permission(): void
    {
        $viewer = $this->makeUnitCostUser(['view_unit_cost_results'], 'unit-viewer@example.com');

        $this->actingAs($viewer)
            ->get(route('calculations.units.index'))
            ->assertForbidden();
    }
}
