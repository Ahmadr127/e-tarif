<?php

namespace Tests\Feature;

use App\Http\Services\Calculation\CalculationService;
use App\Models\CalculationFormula;
use App\Models\CalculationRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\Feature\Concerns\CalculationTestSchema;
use Tests\TestCase;

class CalculationPageTest extends TestCase
{
    use CalculationTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCalculation();
    }

    public function test_halaman_riwayat_dapat_diakses_admin_kalkulasi(): void
    {
        $this->actingAsCalculationAdmin();

        $this->get(route('calculations.index'))
            ->assertOk()
            ->assertSee('Kalkulasi Unit Cost')
            ->assertSee('Belum ada run kalkulasi.');
    }

    public function test_pengguna_tanpa_permission_ditolak(): void
    {
        $viewer = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
        $this->calculationUser->update(['role_id' => $viewer->id]);

        $this->actingAs($this->calculationUser->fresh('role'))
            ->get(route('calculations.index'))
            ->assertForbidden();
    }

    public function test_form_kalkulasi_menampilkan_variabel_aktif(): void
    {
        $this->activateOnly(['average_patients_per_day']);
        $this->actingAsCalculationAdmin();

        $this->get(route('calculations.create'))
            ->assertOk()
            ->assertSee('Jalankan Kalkulasi Unit Cost')
            ->assertSee('annual_patient_count')
            ->assertSee('Jumlah pasien selama satu tahun');
    }

    public function test_kalkulasi_dari_form_disimpan_dan_ditampilkan(): void
    {
        $this->activateOnly(['average_patients_per_day']);
        $this->actingAsCalculationAdmin();

        $response = $this->post(route('calculations.store'), [
            'unit_code' => 'KPR',
            'period_year' => 2026,
            'inputs' => [
                'annual_patient_count' => '730',
            ],
            'notes' => 'Uji dari halaman',
        ]);

        $run = CalculationRun::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('calculations.show', $run));
        $this->assertSame(CalculationRun::STATUS_CALCULATED, $run->status);

        $this->get(route('calculations.show', $run))
            ->assertOk()
            ->assertSee('average_patients_per_day')
            ->assertSee('Rata-rata pasien per hari')
            ->assertSee('Uji dari halaman');
    }

    public function test_kalkulasi_gagal_diarahkan_ke_detail_run_failed(): void
    {
        $this->activateOnly(['average_patients_per_day']);
        $this->actingAsCalculationAdmin();

        $response = $this->post(route('calculations.store'), [
            'unit_code' => 'KPR',
            'period_year' => 2026,
            'inputs' => [],
        ]);

        $run = CalculationRun::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('calculations.show', $run));
        $response->assertSessionHas('error');

        $this->get(route('calculations.show', $run))
            ->assertOk()
            ->assertSee('Kalkulasi gagal');
    }

    public function test_approve_mengubah_status_run(): void
    {
        $this->activateOnly(['average_patients_per_day']);
        $this->actingAsCalculationAdmin();

        $run = app(CalculationService::class)->calculateAndStore(
            'KPR',
            2026,
            ['annual_patient_count' => 730],
            $this->calculationUser->id
        );

        $this->post(route('calculations.approve', $run))
            ->assertRedirect(route('calculations.show', $run));

        $this->assertSame(CalculationRun::STATUS_APPROVED, $run->refresh()->status);
    }

    public function test_halaman_master_menampilkan_komponen_variabel_dan_formula(): void
    {
        $this->actingAsCalculationAdmin();

        $this->get(route('calculations.master'))
            ->assertOk()
            ->assertSee('Master Data Kalkulasi Unit Cost')
            ->assertSee('Tempat Tidur Pasien')
            ->assertSee('average_patients_per_day')
            ->assertSee('annual_patient_count / days_per_year');
    }

    private function actingAsCalculationAdmin(): User
    {
        $role = Role::create(['name' => 'admin-calc', 'display_name' => 'Admin Kalkulasi']);

        foreach ([
            'manage_calculations',
            'approve_calculations',
        ] as $name) {
            $permission = Permission::create([
                'name' => $name,
                'display_name' => $name,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $this->calculationUser->update(['role_id' => $role->id]);

        $user = $this->calculationUser->fresh('role');
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function activateOnly(array $codes): void
    {
        CalculationFormula::query()->update(['is_active' => false]);
        CalculationFormula::query()->whereIn('code', $codes)->update(['is_active' => true]);
    }
}
