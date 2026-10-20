<?php

/**
 * Skrip pengukuran performa sementara (tidak dipakai aplikasi).
 * Membuat prefix percobaan di dalam transaksi lalu rollback, sehingga data
 * produksi tidak berubah.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Services\TarifUnitBookService;
use App\Models\OrganizationUnit;
use App\Models\Tarif;
use Illuminate\Support\Facades\DB;

$unit = OrganizationUnit::where('is_active', true)->where('code', 'IGD')->first()
    ?? OrganizationUnit::where('is_active', true)->whereNull('parent_id')->first()
    ?? OrganizationUnit::where('is_active', true)->first();

if (! $unit) {
    exit("Tidak ada unit aktif.\n");
}

$fmt = static fn (float $ms): string => number_format($ms, 1, ',', '.').' ms';

echo "Unit uji: {$unit->name} ({$unit->code})\n";
echo 'Total tarif: '.number_format(Tarif::count(), 0, ',', '.')."\n\n";

echo "Index pada tarifs:\n";
foreach (DB::select("select indexname, indexdef from pg_indexes where tablename = 'tarifs'") as $index) {
    echo '  '.$index->indexname.' :: '.$index->indexdef."\n";
}

DB::beginTransaction();

try {
    $now = now();
    foreach (['JPU', 'LAB'] as $prefix) {
        DB::table('tarif_unit_service_prefixes')->insert([
            'organization_unit_id' => $unit->id,
            'prefix' => $prefix,
            'is_active' => true,
            'notes' => 'pengukuran sementara',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $service = app(TarifUnitBookService::class);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    // Baseline: query halaman tarif tanpa filter prefix (perilaku lama).
    $queries = [];
    $t0 = microtime(true);
    $baseline = Tarif::with(['jenisTarif', 'provider', 'service', 'serviceClass'])
        ->orderByDesc('created_at')->orderByDesc('id')->limit(25)->get();
    $baselineMs = (microtime(true) - $t0) * 1000;
    $baselineQueries = count($queries);

    // Buku tarif: prefix + pagination.
    $queries = [];
    $t0 = microtime(true);
    $prefixes = $service->prefixes($unit);
    $active = $service->normalizePrefixes($prefixes->where('is_active', true)->pluck('prefix'));
    $tPrefix = (microtime(true) - $t0) * 1000;
    $prefixQueries = count($queries);

    $queries = [];
    $t0 = microtime(true);
    $page = $service->paginate($unit, ['per_page' => 25], $active);
    $pageMs = (microtime(true) - $t0) * 1000;
    $pageQueries = count($queries);

    echo "\nHASIL PENGUKURAN\n";
    echo "Baseline (25 tarif tanpa filter prefix): {$fmt($baselineMs)} / {$baselineQueries} query\n";
    echo "Prefix aktif unit: {$fmt($tPrefix)} / {$prefixQueries} query\n";
    echo "Halaman buku tarif (25 baris): {$fmt($pageMs)} / {$pageQueries} query\n";
    echo 'Total hasil filter prefix: '.number_format($page->total(), 0, ',', '.')." tarif\n";
    echo 'Baris halaman: '.count($page->items())."\n";
    echo 'Prefix aktif: '.implode(', ', $active)."\n";

    echo "\nSQL halaman buku tarif:\n";
    foreach ($queries as $sql) {
        echo '  - '.$sql."\n";
    }

    $dup = array_count_values($queries);
    $repeated = array_filter($dup, static fn (int $n): bool => $n > 1);
    echo 'Query berulang: '.(empty($repeated) ? 'tidak ada' : json_encode($repeated))."\n";

    echo "\nEXPLAIN ANALYZE (prefix JPU/LAB, limit 25):\n";
    $plan = DB::select(<<<'SQL'
        EXPLAIN (ANALYZE, BUFFERS)
        SELECT tarifs.*
        FROM tarifs
        WHERE tarifs.service_id IN (
            SELECT id FROM services
            WHERE UPPER(TRIM(code)) LIKE 'JPU%' ESCAPE '\'
               OR UPPER(TRIM(code)) LIKE 'LAB%' ESCAPE '\'
        )
        ORDER BY tarifs.created_at DESC, tarifs.id DESC
        LIMIT 25
    SQL);

    foreach ($plan as $row) {
        echo '  '.$row->{'QUERY PLAN'}."\n";
    }
} finally {
    DB::rollBack();
}

echo "\nTransaksi di-rollback; tidak ada data yang tersimpan.\n";
