<?php

namespace Tests\Feature;

use App\Models\UnitCostComponent;
use App\Models\UnitCostPeriod;
use Tests\Feature\Concerns\UnitCostTestSchema;
use Tests\TestCase;

class UnitCostPeriodTest extends TestCase
{
    use UnitCostTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUnitCost();
    }

    public function test_period_index_is_accessible_and_has_no_calculate_button(): void
    {
        $response = $this->actingAs($this->unitCostUser)->get(route('calculations.index'));

        $response->assertOk();
        $response->assertSee('Tambah Periode');
        $response->assertDontSee('Hitung dan Simpan');
        $response->assertDontSee('Jalankan');
    }

    public function test_user_can_create_period_and_duplicates_are_rejected(): void
    {
        $payload = [
            'code' => 'UC-2026',
            'name' => 'Tahun Anggaran 2026',
            'period_year' => 2026,
            'status' => 'draft',
        ];

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.periods.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('unit_cost_periods', [
            'code' => 'UC-2026',
            'period_year' => 2026,
        ]);

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.periods.store'), $payload)
            ->assertSessionHasErrors('code');

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.periods.store'), [
                'code' => 'UC-2026-B',
                'name' => 'Tahun Anggaran 2026',
                'period_year' => 2026,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_period_show_lists_components_and_parameter_form(): void
    {
        $period = $this->createPeriod();
        $component = UnitCostComponent::query()->where('code', 'GAJI')->firstOrFail();

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.periods.show', ['period' => $period, 'unit_id' => $this->unitCostUnit->id]))
            ->assertOk()
            ->assertSee('Gaji Pegawai')
            ->assertSee('Keperawatan')
            ->assertDontSee('Hitung dan Simpan');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.parameters.edit', [
                'period' => $period,
                'component' => $component->id,
                'unit_id' => $this->unitCostUnit->id,
            ]))
            ->assertOk()
            ->assertSee('Nama Parameter')
            ->assertSee('Kode Parameter')
            ->assertDontSee('Sumber Data')
            ->assertSee('Metode Input')
            ->assertSee('Input Nilai')
            ->assertSee('Input Rumus')
            ->assertSee('Simpan')
            ->assertDontSee('Hitung dan Simpan');
    }

    public function test_period_can_be_activated_and_closed_and_closed_period_is_read_only(): void
    {
        $period = $this->createPeriod();

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.periods.activate', $period))
            ->assertRedirect();

        $this->assertSame(UnitCostPeriod::STATUS_ACTIVE, $period->fresh()->status);

        $this->actingAs($this->unitCostUser)
            ->post(route('calculations.periods.close', $period))
            ->assertRedirect();

        $this->assertSame(UnitCostPeriod::STATUS_CLOSED, $period->fresh()->status);

        $component = UnitCostComponent::query()->where('code', 'GAJI')->firstOrFail();
        $parameter = $component->parameters()->firstOrFail();

        $this->actingAs($this->unitCostUser)
            ->put(route('calculations.parameters.update', [
                'period' => $period,
                'component' => $component->id,
            ]), [
                'unit_id' => $this->unitCostUnit->id,
                'entries' => [
                    $parameter->id => [
                        'input_type' => 'value',
                        'input_value' => 100,
                    ],
                ],
            ])
            ->assertSessionHasErrors('period');
    }

    public function test_period_permission_is_required(): void
    {
        $viewer = $this->makeUnitCostUser(['view_unit_cost_results'], 'viewer@example.com');

        $this->actingAs($viewer)
            ->get(route('calculations.index'))
            ->assertForbidden();

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.index'))
            ->assertOk();
    }

    public function test_only_draft_period_can_be_deleted(): void
    {
        $draft = $this->createPeriod('UC-DRAFT', 'Draf', 'draft');
        $active = $this->createPeriod('UC-ACTIVE', 'Aktif', 'active');

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.periods.destroy', $active))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('unit_cost_periods', ['id' => $active->id]);

        $this->actingAs($this->unitCostUser)
            ->delete(route('calculations.periods.destroy', $draft))
            ->assertRedirect(route('calculations.index'));

        $this->assertDatabaseMissing('unit_cost_periods', ['id' => $draft->id]);
    }

    public function test_master_and_history_pages_are_accessible(): void
    {
        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.master', ['section' => 'parameters']))
            ->assertOk()
            ->assertSee('Master Parameter')
            ->assertSee('luas_bangunan_keseluruhan');

        $this->actingAs($this->unitCostUser)
            ->get(route('calculations.history.index'))
            ->assertOk()
            ->assertSee('Riwayat Hasil');
    }

    public function test_calculate_routes_are_not_registered(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('calculations.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('calculations.create'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('calculations.approve'));
    }

    private function createPeriod(string $code = 'UC-2026', string $name = 'Tahun Anggaran 2026', string $status = 'draft'): UnitCostPeriod
    {
        return UnitCostPeriod::create([
            'code' => $code,
            'name' => $name,
            'period_year' => 2026,
            'status' => $status,
            'created_by' => $this->unitCostUser->id,
        ]);
    }
}
