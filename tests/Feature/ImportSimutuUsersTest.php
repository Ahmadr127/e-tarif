<?php

namespace Tests\Feature;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Command users:import-simutu — impor data login user dari JSON SIMUTU.
 *
 * Catatan: skema dibuat manual (tanpa RefreshDatabase) mengikuti test fitur
 * lain di repo ini.
 */
class ImportSimutuUsersTest extends TestCase
{
    protected string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->createTables();
        $this->truncateTables();

        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::create(['name' => 'user', 'display_name' => 'Pengguna']);

        OrganizationType::create(['name' => 'department', 'display_name' => 'Departemen', 'level' => 4]);
        OrganizationType::create(['name' => 'unit', 'display_name' => 'Unit/Seksi', 'level' => 5]);

        // Unit yang sudah ada di e-tarif; di JSON cuma "IT" jadi harus
        // dicocokkan ke unit ini, bukan dibuat baru.
        OrganizationUnit::create([
            'name' => 'Departemen IT',
            'code' => 'IT',
            'type_id' => OrganizationType::where('name', 'department')->value('id'),
            'is_active' => true,
        ]);

        $this->fixturePath = $this->writeFixture();
    }

    protected function tearDown(): void
    {
        if (isset($this->fixturePath) && is_file($this->fixturePath)) {
            @unlink($this->fixturePath);
        }

        parent::tearDown();
    }

    protected function createTables(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function ($table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->onDelete('cascade');
                $table->foreignId('permission_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['role_id', 'permission_id']);
            });
        }
        if (! Schema::hasTable('organization_types')) {
            Schema::create('organization_types', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->integer('level')->default(1);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('organization_units')) {
            Schema::create('organization_units', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->foreignId('type_id')->constrained('organization_types');
                $table->foreignId('parent_id')->nullable()->constrained('organization_units');
                $table->foreignId('head_id')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('nik')->nullable();
                $table->string('username')->unique();
                $table->string('email')->unique();
                $table->string('password');
                $table->foreignId('role_id')->nullable();
                $table->unsignedBigInteger('organization_unit_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    protected function truncateTables(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach (['users', 'organization_units', 'organization_types', 'roles', 'permissions', 'role_permission'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function writeFixture(): string
    {
        $hash = Hash::make('rahasia');

        $payload = [
            'roles' => [
                ['id' => '1', 'nama_role' => 'Administrator'],
                ['id' => '4', 'nama_role' => 'Staff'],
            ],
            'units' => [
                ['id' => '24', 'kode_unit' => 'UNIT024', 'nama_unit' => 'IT', 'status_unit' => 'aktif'],
                ['id' => '12', 'kode_unit' => 'UNIT012', 'nama_unit' => 'NS 3', 'status_unit' => 'aktif'],
            ],
            'users' => [
                [
                    'id' => '1',
                    'nama_lengkap' => 'Administrator SIMUTU',
                    'nip' => '1234567890',
                    'username' => 'admin.simutu',
                    'email' => 'administrator@rsazra.co.id',
                    'password' => $hash,
                    'role_id' => '1',
                    'unit_id' => '24',
                ],
                [
                    'id' => '5',
                    'nama_lengkap' => 'Budi Perawat',
                    'nip' => '0987654321',
                    'username' => 'budi.ns3',
                    'email' => 'budi@rsazra.co.id',
                    'password' => $hash,
                    'role_id' => '4',
                    'unit_id' => '12',
                ],
                [
                    'id' => '6',
                    'nama_lengkap' => 'Tanpa Unit',
                    'nip' => '111222333',
                    'username' => 'tanpa.unit',
                    'email' => 'tanpa@rsazra.co.id',
                    'password' => $hash,
                    'role_id' => '4',
                    'unit_id' => '99',
                ],
            ],
        ];

        $path = storage_path('framework/testing/simutu-users-'.uniqid().'.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($payload));

        return $path;
    }

    public function test_import_membuat_user_dan_menyambungkan_unit(): void
    {
        $this->artisan('users:import-simutu', ['file' => $this->fixturePath])
            ->expectsOutputToContain('User dibuat')
            ->assertExitCode(0);

        $this->assertSame(3, User::count());

        // Role "Administrator" di JSON -> role admin e-tarif.
        $admin = User::where('username', 'admin.simutu')->firstOrFail();
        $this->assertSame('Administrator SIMUTU', $admin->name);
        $this->assertSame('1234567890', $admin->nik);
        $this->assertSame('admin', $admin->role->name);
        $this->assertSame('IT', $admin->organizationUnit->code);
        $this->assertSame(1, OrganizationUnit::where('name', 'Departemen IT')->count());
        $this->assertTrue(Hash::check('rahasia', $admin->password));

        // Sisanya role user, unit baru dibuat dari JSON.
        $budi = User::where('username', 'budi.ns3')->firstOrFail();
        $this->assertSame('user', $budi->role->name);
        $this->assertSame('NS 3', $budi->organizationUnit->name);
        $this->assertTrue(Hash::check('rahasia', $budi->password));

        // unit_id yang tidak ada di daftar units -> biarkan kosong.
        $this->assertNull(User::where('username', 'tanpa.unit')->firstOrFail()->organization_unit_id);
    }

    public function test_import_tidak_menimpa_user_yang_sudah_ada(): void
    {
        User::create([
            'name' => 'Sudah Ada',
            'username' => 'budi.ns3',
            'email' => 'budi@rsazra.co.id',
            'password' => 'lama',
            'role_id' => Role::where('name', 'user')->value('id'),
        ]);

        $this->artisan('users:import-simutu', ['file' => $this->fixturePath])
            ->expectsOutputToContain('User dilewati')
            ->assertExitCode(0);

        $this->assertSame(3, User::count());
        $this->assertSame('Sudah Ada', User::where('username', 'budi.ns3')->value('name'));
    }

    public function test_flag_update_existing_menimpa_data_user(): void
    {
        User::create([
            'name' => 'Sudah Ada',
            'username' => 'budi.ns3',
            'email' => 'budi@rsazra.co.id',
            'password' => 'lama',
            'role_id' => Role::where('name', 'user')->value('id'),
        ]);

        $this->artisan('users:import-simutu', ['file' => $this->fixturePath, '--update-existing' => true])
            ->assertExitCode(0);

        $budi = User::where('username', 'budi.ns3')->firstOrFail();
        $this->assertSame('Budi Perawat', $budi->name);
        $this->assertSame('budi@rsazra.co.id', $budi->email);
        $this->assertTrue(Hash::check('rahasia', $budi->password));
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $this->artisan('users:import-simutu', ['file' => $this->fixturePath, '--dry-run' => true])
            ->expectsOutputToContain('User dibuat')
            ->assertExitCode(0);

        $this->assertSame(0, User::count());
        $this->assertSame(1, OrganizationUnit::count());
    }

    public function test_flag_no_units_tidak_membuat_unit_baru(): void
    {
        $this->artisan('users:import-simutu', ['file' => $this->fixturePath, '--no-units' => true])
            ->assertExitCode(0);

        $this->assertSame(1, OrganizationUnit::count());

        // "IT" sudah ada -> tetap tersambung.
        $this->assertSame('IT', User::where('username', 'admin.simutu')->firstOrFail()->organizationUnit->code);

        // "NS 3" tidak dibuat -> user tanpa unit.
        $this->assertNull(User::where('username', 'budi.ns3')->firstOrFail()->organization_unit_id);
    }

    public function test_file_json_tidak_ada_mengembalikan_gagal(): void
    {
        $this->artisan('users:import-simutu', ['file' => 'tidak/ada.json'])
            ->expectsOutputToContain('tidak ditemukan')
            ->assertExitCode(1);
    }
}
