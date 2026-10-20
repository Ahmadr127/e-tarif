<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;

$json = json_decode(file_get_contents(__DIR__.'/database/data/simutu_organisasi.json'), true);

$norm = function (string $name): string {
    $name = mb_strtolower(trim($name));
    $name = preg_replace('/^(departemen|department|unit|instalasi|seksi|bagian)\s+/u', '', $name) ?? $name;

    return preg_replace('/[^a-z0-9]+/u', '', $name) ?? '';
};

$byName = OrganizationUnit::query()->pluck('id', 'name')
    ->mapWithKeys(fn ($id, $name) => [$norm((string) $name) => $id])->all();

$matched = [];
foreach ($json['units'] as $u) {
    $key = $norm($u['nama_unit']);
    if (isset($byName[$key])) {
        $matched[(string) $u['kode_unit']] = $byName[$key];
    }
}
echo 'JSON units: '.count($json['units']).' | matched: '.count($matched)."\n";
$ids = array_values($matched);
echo 'matched ids: '.count($ids)."\n\n";

$cand = OrganizationUnit::query()->whereNotIn('id', $ids)->orderBy('code')->get(['id', 'code', 'name']);
echo 'kandidat (tidak cocok): '.$cand->count()."\n";
foreach ($cand as $u) {
    echo "  {$u->id} {$u->code} {$u->name}\n";
}

echo "\npasangan JSON -> DB:\n";
foreach ($json['units'] as $u) {
    $key = $norm($u['nama_unit']);
    echo '  '.$u['kode_unit'].' '.$u['nama_unit'].' => '.($matched[$u['kode_unit']] ?? 'BARU')."\n";
}
