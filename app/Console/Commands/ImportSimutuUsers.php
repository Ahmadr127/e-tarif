<?php

namespace App\Console\Commands;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;

/**
 * Import data login user dari export JSON SIMUTU (roles/units/users).
 *
 * Contoh:
 *   php artisan users:import-simutu --dry-run
 *   php artisan users:import-simutu
 *   php artisan users:import-simutu --no-units
 *   php artisan users:import-simutu --update-existing
 *   php artisan users:import-simutu --update-existing --prune
 *
 * Catatan: password di file JSON sudah berupa hash bcrypt, jadi dipakai apa
 * adanya (user tetap login dengan password lamanya). Penulisan memakai query
 * builder supaya hash tidak di-hash ulang oleh cast "hashed" pada model User.
 *
 * Opsi --prune membuat tabel users mengikuti isi file JSON: user yang sudah
 * tidak ada di JSON (misalnya karena unitnya dihapus dari export) ikut
 * dihapus, kecuali username yang dilindungi lewat --keep (default: admin, user).
 *
 * Opsi --prune-units menghapus unit yang tidak ada di file JSON, tetapi hanya
 * unit yang benar-benar kosong: tanpa user, tanpa sub-unit, dan tanpa mapping
 * tarif. Unit yang masih terpakai dilewati dan dilaporkan alasannya.
 */
class ImportSimutuUsers extends Command
{
    protected $signature = 'users:import-simutu
                            {file=database/data/simutu_organisasi.json : File JSON (relatif ke root project atau absolut)}
                            {--no-units : Jangan buat unit baru, hanya pakai unit yang sudah ada (dicocokkan nama)}
                            {--update-existing : Timpa data user yang username-nya sudah ada}
                            {--prune : Hapus user yang tidak ada di file JSON (kecuali username yang dilindungi)}
                            {--keep=* : Username yang tidak dihapus saat --prune (boleh diulang; default: admin, user)}
                            {--prune-units : Hapus unit yang tidak ada di file JSON (hanya yang kosong: tanpa user/sub-unit/mapping tarif)}
                            {--keep-unit=* : Kode unit yang tidak dihapus saat --prune-units (boleh diulang)}
                            {--default-password= : Pakai password ini untuk semua user (mengabaikan hash di JSON)}
                            {--dry-run : Hanya tampilkan rencana, tidak menulis ke database}';

    protected $description = 'Import user (data login) dari export JSON SIMUTU ke tabel users e-tarif';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_file($path)) {
            $this->error("File JSON tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        try {
            $json = json_decode($this->readJson($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException|RuntimeException $e) {
            $this->error('File JSON tidak bisa dibaca: '.$e->getMessage());

            return self::FAILURE;
        }

        $userRows = $json['users'] ?? [];
        $unitRows = $json['units'] ?? [];
        $rolesById = collect($json['roles'] ?? [])->keyBy('id');

        if (empty($userRows)) {
            $this->error('Tidak ada data "users" di file JSON tersebut.');

            return self::FAILURE;
        }

        $adminRoleId = Role::where('name', 'admin')->value('id');
        $userRoleId = Role::where('name', 'user')->value('id');

        if (! $adminRoleId || ! $userRoleId) {
            $this->error('Role "admin"/"user" belum ada. Jalankan dulu: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $createUnits = ! $this->option('no-units');
        $updateExisting = (bool) $this->option('update-existing');
        $prune = (bool) $this->option('prune');
        $keepUsernames = $this->protectedUsernames();
        $pruneUnits = (bool) $this->option('prune-units');
        $keepUnitCodes = $this->protectedUnitCodes();
        $defaultPassword = trim((string) $this->option('default-password'));
        $defaultPassword = $defaultPassword !== '' ? $defaultPassword : null;
        $now = now();

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Import dari: '.$path);
        $this->newLine();

        $unitStats = $this->importUnits($unitRows, $createUnits, $dryRun);
        $userStats = $this->importUsers(
            $userRows,
            $unitStats['map'],
            $rolesById,
            $adminRoleId,
            $userRoleId,
            $updateExisting,
            $defaultPassword,
            $dryRun,
            $now
        );

        $pruneStats = $prune
            ? $this->pruneUsers($userRows, $keepUsernames, $dryRun)
            : ['deleted' => 0, 'protected' => 0, 'usernames' => []];

        // Dijalankan setelah user dipangkas supaya unit yang jadi kosong ikut terhapus.
        $matchedUnitIds = array_values(array_filter(array_map('intval', array_values($unitStats['map']))));
        $unitPruneStats = $pruneUnits
            ? $this->pruneUnits($unitRows, $keepUnitCodes, $matchedUnitIds, $dryRun)
            : ['deleted' => 0, 'skipped' => [], 'codes' => []];

        $summary = [
            ['User di file JSON', count($userRows)],
            ['User dibuat', $userStats['created']],
            ['User diperbarui', $userStats['updated']],
            ['User dilewati (username sudah ada)', $userStats['skipped']],
            ['User tanpa unit', $userStats['without_unit']],
            ['Unit dicocokkan dengan yang ada', $unitStats['matched']],
            ['Unit baru dibuat', $unitStats['created']],
        ];

        if ($prune) {
            $summary[] = ['User dihapus (tidak ada di JSON)', $pruneStats['deleted']];
            $summary[] = ['User dilindungi (--keep)', $pruneStats['protected']];
        }

        if ($pruneUnits) {
            $summary[] = ['Unit dihapus (tidak ada di JSON)', $unitPruneStats['deleted']];
            $summary[] = ['Unit dilewati (masih terpakai)', count($unitPruneStats['skipped'])];
        }

        $this->table(['Keterangan', 'Jumlah'], $summary);

        if ($prune && $pruneStats['usernames'] !== []) {
            $sample = array_slice($pruneStats['usernames'], 0, 15);
            $this->line(($dryRun ? 'Akan dihapus: ' : 'Dihapus: ').implode(', ', $sample)
                .(count($pruneStats['usernames']) > count($sample) ? ', ...' : ''));
            $this->newLine();
        }

        if ($pruneUnits && $unitPruneStats['codes'] !== []) {
            $sample = array_slice($unitPruneStats['codes'], 0, 15);
            $this->line(($dryRun ? 'Unit akan dihapus: ' : 'Unit dihapus: ').implode(', ', $sample)
                .(count($unitPruneStats['codes']) > count($sample) ? ', ...' : ''));
            $this->newLine();
        }

        if ($pruneUnits && $unitPruneStats['skipped'] !== []) {
            $this->line('Unit dilewati:');

            foreach ($unitPruneStats['skipped'] as $code => $reason) {
                $this->line('  - '.$code.': '.$reason);
            }

            $this->newLine();
        }

        if (! $prune) {
            $extraUsers = $this->prunableUserCount($userRows, $keepUsernames);

            if ($extraUsers > 0) {
                $this->warn($extraUsers.' user di database tidak ada di file JSON (pakai --prune untuk menghapus).');
            }
        }

        if (! $pruneUnits) {
            $extraUnits = $this->prunableUnitCount($matchedUnitIds, $keepUnitCodes);

            if ($extraUnits > 0) {
                $this->warn($extraUnits.' unit di database tidak ada di file JSON (pakai --prune-units untuk menghapus).');
            }
        }

        if ($userStats['skipped'] > 0 && ! $updateExisting) {
            $this->warn('User yang username-nya sudah ada dilewati (pakai --update-existing untuk menimpa).');
        }

        if ($unitStats['skipped'] > 0) {
            $this->warn($unitStats['skipped'].' user tidak punya unit di e-tarif (dijalankan dengan --no-units).');
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN: tidak ada data yang ditulis. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $unitRows
     * @return array{map: array<mixed, int>, matched: int, created: int, skipped: int}
     */
    protected function importUnits(array $unitRows, bool $createUnits, bool $dryRun): array
    {
        $unitTypeId = OrganizationType::where('name', 'unit')->value('id');

        // Pencocokan nama: "Unit Rawat Inap" / "Departemen IT" dianggap sama dengan
        // "Rawat inap" / "IT" supaya unit yang sudah ada tidak diduplikasi.
        $byName = OrganizationUnit::query()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [$this->normalizeName((string) $name) => $id])
            ->all();

        $map = [];
        $matched = 0;
        $created = 0;
        $skipped = 0;

        foreach ($unitRows as $unit) {
            $name = trim((string) ($unit['nama_unit'] ?? ''));

            if ($name === '') {
                continue;
            }

            $key = $this->normalizeName($name);

            if (isset($byName[$key])) {
                $map[$unit['id']] = $byName[$key];
                $matched++;

                continue;
            }

            if (! $createUnits) {
                $skipped++;

                continue;
            }

            $attributes = [
                'name' => $name,
                'type_id' => $unitTypeId,
                'parent_id' => null,
                'description' => 'Hasil import SIMUTU',
                'is_active' => ($unit['status_unit'] ?? 'aktif') === 'aktif',
            ];

            if ($dryRun) {
                // Placeholder supaya hitungan "user tanpa unit" di dry-run akurat.
                $map[$unit['id']] = 0;
                $byName[$key] = 0;
                $created++;

                continue;
            }

            $code = trim((string) ($unit['kode_unit'] ?? ''))
                ?: 'IMP'.str_pad((string) $unit['id'], 3, '0', STR_PAD_LEFT);

            $model = OrganizationUnit::firstOrCreate(['code' => $code], $attributes);

            $map[$unit['id']] = $model->id;
            $byName[$key] = $model->id;
            $created++;
        }

        return ['map' => $map, 'matched' => $matched, 'created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, array<string, mixed>>  $userRows
     * @param  array<mixed, int>  $unitMap
     * @return array{created: int, updated: int, skipped: int, without_unit: int}
     */
    protected function importUsers(
        array $userRows,
        array $unitMap,
        $rolesById,
        int $adminRoleId,
        int $userRoleId,
        bool $updateExisting,
        ?string $defaultPassword,
        bool $dryRun,
        \DateTimeInterface $now
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $withoutUnit = 0;
        $defaultHash = $defaultPassword !== null ? Hash::make($defaultPassword) : null;

        foreach ($userRows as $row) {
            $username = trim((string) ($row['username'] ?? ''));

            if ($username === '') {
                continue;
            }

            $roleName = $rolesById[$row['role_id']]['nama_role'] ?? null;
            $unitId = $unitMap[$row['unit_id']] ?? null;

            if ($unitId === null) {
                $withoutUnit++;
            }

            $existingId = User::where('username', $username)->value('id');

            $data = [
                'name' => trim((string) ($row['nama_lengkap'] ?? $username)) ?: $username,
                'nik' => $row['nip'] ?? null,
                'email' => $this->uniqueEmail(trim((string) ($row['email'] ?? '')), $username, $existingId),
                'password' => $defaultHash ?? $this->passwordFor($row['password'] ?? null),
                'role_id' => $roleName === 'Administrator' ? $adminRoleId : $userRoleId,
                'organization_unit_id' => $unitId,
                'updated_at' => $now,
            ];

            if ($existingId) {
                if (! $updateExisting) {
                    $skipped++;

                    continue;
                }

                $updated++;

                if ($dryRun) {
                    continue;
                }

                // Jangan rebut email milik user lain.
                if (User::where('email', $data['email'])->where('id', '!=', $existingId)->exists()) {
                    unset($data['email']);
                }

                DB::table('users')->where('id', $existingId)->update($data);

                continue;
            }

            $created++;

            if ($dryRun) {
                continue;
            }

            DB::table('users')->insert($data + ['username' => $username, 'created_at' => $now]);
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'without_unit' => $withoutUnit];
    }

    /**
     * Hapus user yang sudah tidak ada di file JSON supaya isi tabel users sama
     * dengan export SIMUTU yang sedang dipakai.
     *
     * @param  array<int, array<string, mixed>>  $userRows
     * @param  array<int, string>  $keepUsernames
     * @return array{deleted: int, protected: int, usernames: array<int, string>}
     */
    protected function pruneUsers(array $userRows, array $keepUsernames, bool $dryRun): array
    {
        $jsonUsernames = $this->jsonUsernames($userRows);

        if ($jsonUsernames === []) {
            return ['deleted' => 0, 'protected' => 0, 'usernames' => []];
        }

        $doomed = User::query()
            ->whereNotIn('username', $jsonUsernames)
            ->whereNotIn('username', $keepUsernames)
            ->orderBy('username')
            ->pluck('username')
            ->all();

        $protected = User::query()
            ->whereNotIn('username', $jsonUsernames)
            ->whereIn('username', $keepUsernames)
            ->count();

        if (! $dryRun && $doomed !== []) {
            // FK yang menunjuk users sudah nullable (head_id & import_batches.user_id
            // -> SET NULL), jadi aman dihapus tanpa menyisakan relasi yatim.
            DB::table('users')->whereIn('username', $doomed)->delete();
        }

        return ['deleted' => count($doomed), 'protected' => $protected, 'usernames' => $doomed];
    }

    /**
     * Jumlah user di database yang tidak ada di file JSON (tanpa menghapus).
     *
     * @param  array<int, array<string, mixed>>  $userRows
     * @param  array<int, string>  $keepUsernames
     */
    protected function prunableUserCount(array $userRows, array $keepUsernames): int
    {
        $jsonUsernames = $this->jsonUsernames($userRows);

        if ($jsonUsernames === []) {
            return 0;
        }

        return User::query()
            ->whereNotIn('username', $jsonUsernames)
            ->whereNotIn('username', $keepUsernames)
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $userRows
     * @return array<int, string>
     */
    protected function jsonUsernames(array $userRows): array
    {
        $usernames = [];

        foreach ($userRows as $row) {
            $username = trim((string) ($row['username'] ?? ''));

            if ($username !== '') {
                $usernames[$username] = true;
            }
        }

        return array_keys($usernames);
    }

    /**
     * Username yang tidak boleh ikut dihapus oleh --prune.
     *
     * @return array<int, string>
     */
    protected function protectedUsernames(): array
    {
        $keep = array_merge(['admin', 'user'], (array) $this->option('keep'));

        $keep = array_map(static function ($name): string {
            return is_string($name) ? trim($name) : '';
        }, $keep);

        return array_values(array_unique(array_filter($keep, static fn (string $name): bool => $name !== '')));
    }

    /**
     * Kode unit yang tidak boleh dihapus oleh --prune-units.
     *
     * @return array<int, string>
     */
    protected function protectedUnitCodes(): array
    {
        $keep = array_map(static function ($code): string {
            return is_string($code) ? trim($code) : '';
        }, (array) $this->option('keep-unit'));

        return array_values(array_unique(array_filter($keep, static fn (string $code): bool => $code !== '')));
    }

    /**
     * Hapus unit yang tidak ada di file JSON. Supaya tidak ada data yang ikut
     * terhapus diam-diam, hanya unit kosong yang dijalankan; unit yang masih
     * punya user, sub-unit, atau konfigurasi prefix buku tarif dilewati dan
     * dilaporkan.
     *
     * @param  array<int, array<string, mixed>>  $unitRows
     * @param  array<int, string>  $keepCodes
     * @param  array<int, int>  $matchedIds  id unit yang dipakai file JSON
     * @return array{deleted: int, skipped: array<string, string>, codes: array<int, string>}
     */
    protected function pruneUnits(array $unitRows, array $keepCodes, array $matchedIds, bool $dryRun): array
    {
        $jsonCodes = $this->jsonUnitCodes($unitRows);

        if ($jsonCodes === []) {
            return ['deleted' => 0, 'skipped' => [], 'codes' => []];
        }

        $candidates = OrganizationUnit::query()
            ->when($matchedIds !== [], fn ($query) => $query->whereNotIn('id', $matchedIds))
            ->when($keepCodes !== [], fn ($query) => $query->whereNotIn('code', $keepCodes))
            ->orderBy('code')
            ->get();

        $hasPrefixes = Schema::hasTable('tarif_unit_service_prefixes');

        $codes = [];
        $skipped = [];

        foreach ($candidates as $unit) {
            $users = DB::table('users')->where('organization_unit_id', $unit->id)->count();

            if ($users > 0) {
                $skipped[$unit->code] = $users.' user masih terhubung';

                continue;
            }

            $children = DB::table('organization_units')->where('parent_id', $unit->id)->count();

            if ($children > 0) {
                $skipped[$unit->code] = $children.' sub-unit masih menempel';

                continue;
            }

            if ($hasPrefixes) {
                $prefixes = DB::table('tarif_unit_service_prefixes')->where('organization_unit_id', $unit->id)->count();

                if ($prefixes > 0) {
                    $skipped[$unit->code] = $prefixes.' prefix buku tarif masih memakai unit ini';

                    continue;
                }
            }

            $codes[] = $unit->code;
        }

        if (! $dryRun && $codes !== []) {
            OrganizationUnit::query()->whereIn('code', $codes)->delete();
        }

        return ['deleted' => count($codes), 'skipped' => $skipped, 'codes' => $codes];
    }

    /**
     * Jumlah unit di database yang tidak ada di file JSON (tanpa menghapus).
     *
     * @param  array<int, int>  $matchedIds
     * @param  array<int, string>  $keepCodes
     */
    protected function prunableUnitCount(array $matchedIds, array $keepCodes): int
    {
        return OrganizationUnit::query()
            ->when($matchedIds !== [], fn ($query) => $query->whereNotIn('id', $matchedIds))
            ->when($keepCodes !== [], fn ($query) => $query->whereNotIn('code', $keepCodes))
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $unitRows
     * @return array<int, string>
     */
    protected function jsonUnitCodes(array $unitRows): array
    {
        $codes = [];

        foreach ($unitRows as $row) {
            $code = trim((string) ($row['kode_unit'] ?? ''));

            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * Password di JSON sudah hash bcrypt; kalau ternyata kosong/plain text,
     * dipakai default "rsazra" (di-hash lebih dulu).
     */
    protected function passwordFor(mixed $value): string
    {
        $value = (string) $value;

        if (Hash::isHashed($value)) {
            return $value;
        }

        return Hash::make($value !== '' ? $value : 'rsazra');
    }

    protected function uniqueEmail(string $email, string $username, ?int $ignoreUserId = null): string
    {
        if ($email === '') {
            $email = $username.'@rsazra.co.id';
        }

        $taken = User::where('email', $email)
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->exists();

        return $taken ? $username.'@rsazra.co.id' : $email;
    }

    /**
     * Samakan penulisan nama unit supaya "Unit Rawat Inap" == "Rawat inap".
     */
    protected function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/^(departemen|department|unit|instalasi|seksi|bagian)\s+/u', '', $name) ?? $name;

        return preg_replace('/[^a-z0-9]+/u', '', $name) ?? '';
    }

    protected function resolvePath(string $file): string
    {
        $isAbsolute = (bool) preg_match('#^([a-zA-Z]:[\\\\/]|/|\\\\\\\\)#', $file);

        return $isAbsolute ? $file : base_path($file);
    }

    protected function readJson(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Gagal membaca file: {$path}");
        }

        // Buang BOM kalau file dibuat oleh PowerShell/Excel.
        return preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
    }
}
