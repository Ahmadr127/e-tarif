<?php

namespace App\Console\Commands;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
 *
 * Catatan: password di file JSON sudah berupa hash bcrypt, jadi dipakai apa
 * adanya (user tetap login dengan password lamanya). Penulisan memakai query
 * builder supaya hash tidak di-hash ulang oleh cast "hashed" pada model User.
 */
class ImportSimutuUsers extends Command
{
    protected $signature = 'users:import-simutu
                            {file=database/data/simutu_organisasi.json : File JSON (relatif ke root project atau absolut)}
                            {--no-units : Jangan buat unit baru, hanya pakai unit yang sudah ada (dicocokkan nama)}
                            {--update-existing : Timpa data user yang username-nya sudah ada}
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
            $dryRun,
            $now
        );

        $this->table(['Keterangan', 'Jumlah'], [
            ['User di file JSON', count($userRows)],
            ['User dibuat', $userStats['created']],
            ['User diperbarui', $userStats['updated']],
            ['User dilewati (username sudah ada)', $userStats['skipped']],
            ['User tanpa unit', $userStats['without_unit']],
            ['Unit dicocokkan dengan yang ada', $unitStats['matched']],
            ['Unit baru dibuat', $unitStats['created']],
        ]);

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
        bool $dryRun,
        \DateTimeInterface $now
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $withoutUnit = 0;

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
                'password' => $this->passwordFor($row['password'] ?? null),
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
     * Password di JSON sudah hash bcrypt; kalau ternyata plain text, di-hash dulu.
     */
    protected function passwordFor(mixed $value): string
    {
        $value = (string) $value;

        if (Hash::isHashed($value)) {
            return $value;
        }

        return Hash::make($value !== '' ? $value : Str::random(16));
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
