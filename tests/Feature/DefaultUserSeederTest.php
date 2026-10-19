<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OrganizationTypeSeeder;
use Database\Seeders\OrganizationUnitSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Seeder user default: hanya "admin" dan "user", dan "user" berada di
 * Departemen IT supaya fitur Buku Tarif Unit Saya bisa langsung dipakai.
 *
 * Catatan: skema dibuat manual (tanpa RefreshDatabase) mengikuti test fitur
 * lain di repo ini.
 */
class DefaultUserSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
        $this->createTables();
        $this->truncateTables();

        Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        Role::create(['name' => 'user', 'display_name' => 'Pengguna']);
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
        foreach ([
            'users', 'organization_units', 'organization_types', 'roles', 'permissions', 'role_permission',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function seedDefaults(): void
    {
        $this->seed(OrganizationTypeSeeder::class);
        $this->seed(OrganizationUnitSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_only_admin_and_user_are_created(): void
    {
        $this->seedDefaults();

        $this->assertSame(2, User::count());
        $this->assertSame(['admin', 'user'], User::orderBy('username')->pluck('username')->all());
    }

    public function test_user_default_berada_di_departemen_it(): void
    {
        $this->seedDefaults();

        $admin = User::where('username', 'admin')->firstOrFail();
        $user = User::where('username', 'user')->firstOrFail();

        $this->assertSame('admin', $admin->role->name);
        $this->assertNull($admin->organization_unit_id);

        $this->assertSame('user', $user->role->name);
        $this->assertSame('IT', $user->organizationUnit->code);
        $this->assertSame('Departemen IT', $user->organizationUnit->name);
    }

    public function test_password_default_bisa_dipakai_login(): void
    {
        $this->seedDefaults();

        $this->assertTrue(Hash::check('rsazra', User::where('username', 'admin')->value('password')));
        $this->assertTrue(Hash::check('rsazra', User::where('username', 'user')->value('password')));
    }

    public function test_seeder_aman_dijalankan_berulang(): void
    {
        $this->seedDefaults();
        $this->seed(OrganizationUnitSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertSame(1, OrganizationUnit::where('code', 'IT')->count());
        $this->assertSame(1, OrganizationUnit::where('code', 'DIRUT')->count());
        $this->assertSame('IT', User::where('username', 'user')->firstOrFail()->organizationUnit->code);
    }
}
