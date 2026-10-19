<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi: baris audit lama bisa menunjuk model yang sudah dihapus (mis.
 * App\Models\Room). Eager load morph-nya melempar "Class not found" sehingga
 * halaman /activity-logs error 500. Kini subject diakses lewat accessor aman
 * dan tidak lagi di-eager load.
 *
 * Catatan: tidak memakai RefreshDatabase karena salah satu migration
 * (index trigram/fulltext) khusus PostgreSQL — skema dibuat manual seperti
 * test fitur lain di repo ini.
 */
class ActivityLogPageTest extends TestCase
{
    protected User $admin;

    protected ActivityLog $legacyLog;

    protected ActivityLog $activeLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
        $this->createTables();
        $this->truncateTables();

        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $permission = Permission::create([
            'name' => 'view_activity_logs',
            'display_name' => 'Lihat Audit Log',
        ]);
        $role->permissions()->attach($permission->id);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role_id' => $role->id,
        ]);

        // Subject menunjuk model yang sudah tidak ada di aplikasi.
        $this->legacyLog = ActivityLog::create([
            'log_name' => 'room',
            'event' => 'deleted',
            'description' => 'Hapus room lama',
            'subject_type' => 'App\\Models\\Room',
            'subject_id' => 7,
            'causer_type' => User::class,
            'causer_id' => $this->admin->id,
        ]);

        // Subject yang masih bisa di-resolve.
        $this->activeLog = ActivityLog::create([
            'log_name' => 'user',
            'event' => 'updated',
            'description' => 'Update profil user',
            'subject_type' => User::class,
            'subject_id' => $this->admin->id,
            'causer_type' => User::class,
            'causer_id' => $this->admin->id,
        ]);
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
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->foreignId('role_id')->nullable();
                $table->unsignedBigInteger('organization_unit_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($table) {
                $table->id();
                $table->string('log_name')->nullable();
                $table->string('event');
                $table->string('description')->nullable();
                $table->nullableMorphs('subject');
                $table->nullableMorphs('causer');
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->uuid('batch_uuid')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function truncateTables(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach (['activity_logs', 'users', 'roles', 'permissions', 'role_permission'] as $table) {
            DB::table($table)->delete();
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function test_subject_label_is_safe_for_missing_and_existing_models(): void
    {
        $this->assertFalse($this->legacyLog->subjectIsResolvable());
        $this->assertSame('Room #7 (model tidak tersedia)', $this->legacyLog->subject_label);

        $this->assertTrue($this->activeLog->subjectIsResolvable());
        $this->assertSame('Admin', $this->activeLog->subject_label);
    }

    public function test_index_renders_when_subject_model_no_longer_exists(): void
    {
        $response = $this->actingAs($this->admin)->get(route('activity-logs.index'));

        $response->assertOk();
        $response->assertSee('Hapus room lama');
        $response->assertSee('Room #7');
        $response->assertSee('model tidak tersedia');
        // Subject yang masih ada tetap menampilkan namanya.
        $response->assertSee('Update profil user');
    }

    public function test_index_can_filter_by_legacy_log_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('activity-logs.index', ['log_name' => 'room']));

        $response->assertOk();
        $response->assertSee('Hapus room lama');
        $response->assertDontSee('Update profil user');
    }

    public function test_show_renders_when_subject_model_no_longer_exists(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('activity-logs.show', $this->legacyLog));

        $response->assertOk();
        $response->assertSee('Room #7');
        $response->assertSee('Hapus room lama');
    }
}
