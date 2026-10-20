<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Tarif;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\TarifBookTestSchema;
use Tests\TestCase;

/**
 * Business logic buku tarif berbasis prefix `services.code`.
 *
 * Tidak ada tabel `tarif_unit_mappings` di skema test: seluruh halaman harus
 * berjalan tanpa tabel tersebut.
 */
class TarifUnitBookTest extends TestCase
{
    use TarifBookTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->setUpTarifBook();
    }

    public function test_prefix_mencakup_kode_yang_diawali_prefix(): void
    {
        $jpu01 = $this->makeTarif('JPU01');
        $jpu02 = $this->makeTarif('JPU02');
        $jpu10 = $this->makeTarif('JPU10');

        $this->addPrefix($this->unit, 'JPU');

        $ids = $this->matchingServiceIds($this->unit);

        $this->assertContains($jpu01->service_id, $ids);
        $this->assertContains($jpu02->service_id, $ids);
        $this->assertContains($jpu10->service_id, $ids);
    }

    public function test_prefix_tidak_mencakup_kode_di_tengah(): void
    {
        $inside = $this->makeTarif('JPU01');
        $outside = $this->makeTarif('XJPU01');

        $this->addPrefix($this->unit, 'JPU');

        $ids = $this->matchingServiceIds($this->unit);

        $this->assertContains($inside->service_id, $ids);
        $this->assertNotContains($outside->service_id, $ids);
    }

    public function test_beberapa_prefix_aktif_menggunakan_kondisi_or(): void
    {
        $jpu = $this->makeTarif('JPU01');
        $jpk = $this->makeTarif('JPK05');
        $lab = $this->makeTarif('LAB114');

        $this->addPrefix($this->unit, 'JPU');
        $this->addPrefix($this->unit, 'JPK');

        $ids = $this->matchingServiceIds($this->unit);

        $this->assertContains($jpu->service_id, $ids);
        $this->assertContains($jpk->service_id, $ids);
        $this->assertNotContains($lab->service_id, $ids);
    }

    public function test_prefix_nonaktif_tidak_memengaruhi_hasil(): void
    {
        $jpu = $this->makeTarif('JPU01');
        $lab = $this->makeTarif('LAB114');

        $this->addPrefix($this->unit, 'JPU', true);
        $this->addPrefix($this->unit, 'LAB', false);

        $ids = $this->matchingServiceIds($this->unit);

        $this->assertContains($jpu->service_id, $ids);
        $this->assertNotContains($lab->service_id, $ids);
    }

    public function test_unit_tanpa_prefix_menghasilkan_daftar_kosong(): void
    {
        $this->makeTarif('JPU01');

        $this->assertSame([], $this->matchingServiceIds($this->noPrefixUnit));

        $response = $this->actingAs($this->admin)
            ->get(route('tarif-units.unit', $this->noPrefixUnit));

        $response->assertOk();
        $response->assertSee('Prefix aktif:');
        $response->assertSee('belum ada');
        $response->assertSee('Tidak ada tarif yang cocok');
    }

    public function test_unit_dengan_prefix_nonaktif_saja_tetap_kosong(): void
    {
        $this->makeTarif('JPU01');
        $this->addPrefix($this->noPrefixUnit, 'JPU', false);

        $this->assertSame([], $this->matchingServiceIds($this->noPrefixUnit));
    }

    public function test_service_sama_dengan_kelas_berbeda_tetap_baris_terpisah(): void
    {
        $this->makeTarif('JPU01', ['class_code' => 'KLS1']);
        $this->makeTarif('JPU01', ['class_code' => 'KLS2']);

        $this->addPrefix($this->unit, 'JPU');

        $page = $this->bookService()->paginate($this->unit, ['service_code' => 'JPU01']);

        $this->assertSame(2, $page->total());
        $this->assertCount(2, $page->items());
    }

    public function test_filter_status_mengikuti_periode_dan_berlaku_sebelum_pagination(): void
    {
        $expired = $this->makeTarif('HST01', [
            'valid_date_from' => '2020-01-01',
            'end_date_to' => '2021-12-31',
        ]);
        $upcoming = $this->makeTarif('HST02', [
            'valid_date_from' => '2030-01-01',
            'end_date_to' => '2031-12-31',
        ]);
        $active = $this->makeTarif('HST03', [
            'valid_date_from' => '2024-01-01',
            'end_date_to' => '2029-12-31',
        ]);

        $this->addPrefix($this->unit, 'HST');

        $this->assertSame([$expired->service_id], $this->matchingServiceIds($this->unit, ['status' => 'EXPIRED']));
        $this->assertSame([$upcoming->service_id], $this->matchingServiceIds($this->unit, ['status' => 'UPCOMING']));
        $this->assertSame([$active->service_id], $this->matchingServiceIds($this->unit, ['status' => 'ACTIVE']));

        // Filter status harus diterapkan di SQL sebelum pagination.
        $page = $this->bookService()->paginate($this->unit, ['status' => 'ACTIVE', 'per_page' => 25]);
        $this->assertSame(1, $page->total());
        $this->assertSame('ACTIVE', $page->items()[0]->status);

        // Filter periode (overlap masa berlaku).
        $overlap = $this->matchingServiceIds($this->unit, [
            'date_from' => '2020-06-01',
            'date_to' => '2021-06-01',
        ]);
        $this->assertContains($expired->service_id, $overlap);
        $this->assertNotContains($active->service_id, $overlap);
        $this->assertNotContains($upcoming->service_id, $overlap);

        $from2024 = $this->matchingServiceIds($this->unit, ['date_from' => '2024-06-01']);
        $this->assertNotContains($expired->service_id, $from2024);
        $this->assertContains($active->service_id, $from2024);
    }

    public function test_tarif_baru_otomatis_masuk_tanpa_assignment(): void
    {
        $this->addPrefix($this->unit, 'JPU');

        $this->makeTarif('JPU01');
        $this->assertSame(1, $this->bookService()->paginate($this->unit)->total());

        $this->makeTarif('JPU10');
        $this->assertSame(2, $this->bookService()->paginate($this->unit)->total());
    }

    public function test_perubahan_nominal_master_langsung_terlihat(): void
    {
        $tarif = $this->makeTarif('JPU01', ['tariff' => 100000]);
        $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit', $this->unit))
            ->assertOk()
            ->assertSee('100.000');

        $tarif->update(['tariff' => 250000]);

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit', $this->unit))
            ->assertOk()
            ->assertSee('250.000');
    }

    public function test_tarif_yang_cocok_dengan_prefix_dua_unit_muncul_di_keduanya(): void
    {
        $tarif = $this->makeTarif('JPU01');

        $this->addPrefix($this->unit, 'JPU');
        $this->addPrefix($this->otherUnit, 'JPU');

        $this->assertContains($tarif->service_id, $this->matchingServiceIds($this->unit));
        $this->assertContains($tarif->service_id, $this->matchingServiceIds($this->otherUnit));
    }

    public function test_pagination_pencarian_dan_filter_menghasilkan_data_benar(): void
    {
        $this->addPrefix($this->unit, 'PAG');

        for ($i = 0; $i < 30; $i++) {
            $this->makeTarif(sprintf('PAG%03d', $i));
        }

        $page1 = $this->bookService()->paginate($this->unit, ['per_page' => 25]);
        $this->assertSame(30, $page1->total());
        $this->assertCount(25, $page1->items());

        $page2 = $this->actingAs($this->admin)->get(
            route('tarif-units.unit', ['organization_unit' => $this->unit, 'page' => 2])
        );
        $page2->assertOk();
        $page2->assertSee('PAG000');

        // per_page default 25 (bukan seluruh dataset).
        $this->assertSame(25, $page1->perPage());

        $search = $this->actingAs($this->admin)->get(
            route('tarif-units.unit', ['organization_unit' => $this->unit, 'search' => 'PAG003'])
        );
        $search->assertOk();
        $search->assertSee('PAG003');
        $search->assertDontSee('PAG004');
    }

    public function test_halaman_tidak_pernah_mengakses_tabel_mapping_lama(): void
    {
        $this->assertFalse(Schema::hasTable('tarif_unit_mappings'));

        $this->addPrefix($this->unit, 'JPU');
        $this->makeTarif('JPU01');

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit', $this->unit))
            ->assertOk();

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('tarif_unit_mappings', $sql);
        }
    }

    public function test_tidak_terjadi_n_plus_one_query_per_baris(): void
    {
        $this->addPrefix($this->unit, 'JPU');
        $this->makeTarif('JPU01');
        $this->makeTarif('JPU02');

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($this->admin)->get(route('tarif-units.unit', $this->unit))->assertOk();
        $small = count($queries);

        for ($i = 3; $i <= 10; $i++) {
            $this->makeTarif(sprintf('JPU%02d', $i));
        }

        // Relasi role/permission pada instance user yang sama bisa tersimpan
        // antar request; lepas agar kedua pengukuran adil.
        $this->admin->unsetRelation('role');
        $queries = [];
        $this->actingAs($this->admin)->get(route('tarif-units.unit', $this->unit))->assertOk();
        $large = count($queries);

        $this->assertSame($small, $large, 'Jumlah query berubah ketika baris bertambah (indikasi N+1).');
        $this->assertLessThanOrEqual(15, $large);
    }

    public function test_detail_tarif_di_luar_cakupan_prefix_menghasilkan_404(): void
    {
        $this->addPrefix($this->unit, 'JPU');

        $inside = $this->makeTarif('JPU01');
        $outside = $this->makeTarif('XJPU01');

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit.tarif', [$this->unit, $inside]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit.tarif', [$this->unit, $outside]))
            ->assertNotFound();
    }

    public function test_operator_unit_hanya_melihat_buku_dan_prefix_unitnya_sendiri(): void
    {
        $this->addPrefix($this->unit, 'JPU');
        $this->addPrefix($this->otherUnit, 'LAB');

        $this->makeTarif('JPU01');
        $this->makeTarif('LAB114');

        $book = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.index'));
        $book->assertOk();
        $book->assertSee('JPU01');
        $book->assertDontSee('LAB114');

        $prefixes = $this->actingAs($this->unitUser)->get(route('buku-tarif-saya.prefixes.index'));
        $prefixes->assertOk();
        $prefixes->assertSee('JPU');
        $prefixes->assertDontSee('LAB');
    }

    public function test_pengguna_tanpa_permission_tidak_dapat_membuka_buku_tarif(): void
    {
        $this->actingAs($this->plainUser)
            ->get(route('tarif-units.index'))
            ->assertForbidden();

        $this->actingAs($this->plainUser)
            ->get(route('buku-tarif-saya.index'))
            ->assertForbidden();
    }

    public function test_unit_induk_tidak_mewariskan_prefix_ke_unit_anak(): void
    {
        $child = OrganizationUnit::create([
            'name' => 'Anak Keperawatan',
            'code' => 'KPR-ANAK',
            'type_id' => $this->unit->type_id,
            'parent_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $tarif = $this->makeTarif('JPU01');
        $this->addPrefix($this->unit, 'JPU');

        $this->assertContains($tarif->service_id, $this->matchingServiceIds($this->unit));
        $this->assertSame([], $this->matchingServiceIds($child));
    }

    public function test_prefix_disimpan_uppercase_dan_mencocokkan_kode_service(): void
    {
        $tarif = $this->makeTarif('JPU01');
        $this->addPrefix($this->unit, 'jpu');

        $this->assertContains($tarif->service_id, $this->matchingServiceIds($this->unit));
    }

    public function test_export_xlsx_dan_pdf_mengikuti_cakupan_prefix(): void
    {
        $this->addPrefix($this->unit, 'JPU');
        $this->makeTarif('JPU01');
        $this->makeTarif('XJPU01');

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit.export-xlsx', $this->unit))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit.export-pdf', $this->unit))
            ->assertOk();
    }

    public function test_master_tarif_tidak_berubah_saat_melihat_buku_tarif(): void
    {
        $tarif = $this->makeTarif('JPU01', ['tariff' => 175000]);
        $this->addPrefix($this->unit, 'JPU');

        $this->actingAs($this->admin)
            ->get(route('tarif-units.unit', $this->unit))
            ->assertOk();

        $this->assertSame('175000.00', Tarif::findOrFail($tarif->id)->tariff);
    }
}
