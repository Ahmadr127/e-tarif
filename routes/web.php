<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelperController;
use App\Http\Controllers\JenisTarifController;
use App\Http\Controllers\OrganizationTypeController;
use App\Http\Controllers\OrganizationUnitController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceClassController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\TarifImportController;
use App\Http\Controllers\TarifUnitBookController;
use App\Http\Controllers\TarifUnitServicePrefixController;
use App\Http\Controllers\UnitCostMasterController;
use App\Http\Controllers\UnitCostParameterController;
use App\Http\Controllers\UnitCostPeriodController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will be
| assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/login');
});

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Profile routes
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'index'])->name('profile.index');

    // User Management routes
    Route::middleware('permission:manage_users')->group(function () {
        Route::resource('users', UserController::class);
    });

    // Audit Log routes
    Route::middleware('permission:view_activity_logs')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::get('activity-logs/subject/{type}/{id}', [ActivityLogController::class, 'forSubject'])->name('activity-logs.subject');
    });

    // Role Management routes
    Route::middleware('permission:manage_roles')->group(function () {
        Route::put('roles/permissions', [RoleController::class, 'syncPermissions'])
            ->name('roles.permissions.sync');
        Route::resource('roles', RoleController::class);
    });

    // Permission Management routes
    Route::middleware('permission:manage_permissions')->group(function () {
        Route::resource('permissions', PermissionController::class);
    });

    // Organization Type Management routes
    Route::middleware('permission:manage_organization_types')->group(function () {
        Route::resource('organization-types', OrganizationTypeController::class);
    });

    // Organization Unit Management routes
    Route::middleware('permission:manage_organization_units')->group(function () {
        Route::resource('organization-units', OrganizationUnitController::class);

        // Member management routes
        Route::post('organization-units/{organization_unit}/members', [OrganizationUnitController::class, 'addMember'])
            ->name('organization-units.add-member');
        Route::delete('organization-units/{organization_unit}/members/{user}', [OrganizationUnitController::class, 'removeMember'])
            ->name('organization-units.remove-member');
        Route::patch('organization-units/{organization_unit}/head', [OrganizationUnitController::class, 'updateHead'])
            ->name('organization-units.update-head');
    });

    // Tarif Master Data routes (Jenis Tarif, Provider, Service, Kelas)
    Route::middleware('permission:manage_tarif_masters')->group(function () {
        Route::resource('jenis-tarifs', JenisTarifController::class);
        Route::resource('providers', ProviderController::class);
        Route::resource('services', ServiceController::class);
        Route::resource('classes', ServiceClassController::class)->parameters(['classes' => 'serviceClass']);
    });

    // Helper Master Data routes (aturan persentase billing bedah)
    Route::middleware('permission:manage_helpers')->group(function () {
        Route::resource('helpers', HelperController::class);
    });

    // Unit Cost: Periode -> Komponen -> Parameter.
    Route::middleware('permission:manage_unit_cost_periods|manage_calculations')->group(function () {
        Route::get('calculations', [UnitCostPeriodController::class, 'index'])
            ->name('calculations.index');
        Route::get('calculations/periods/create', [UnitCostPeriodController::class, 'create'])
            ->name('calculations.periods.create');
        Route::post('calculations/periods', [UnitCostPeriodController::class, 'store'])
            ->name('calculations.periods.store');
        Route::get('calculations/periods/{period}/edit', [UnitCostPeriodController::class, 'edit'])
            ->whereNumber('period')
            ->name('calculations.periods.edit');
        Route::put('calculations/periods/{period}', [UnitCostPeriodController::class, 'update'])
            ->whereNumber('period')
            ->name('calculations.periods.update');
        Route::delete('calculations/periods/{period}', [UnitCostPeriodController::class, 'destroy'])
            ->whereNumber('period')
            ->name('calculations.periods.destroy');
        Route::post('calculations/periods/{period}/activate', [UnitCostPeriodController::class, 'activate'])
            ->whereNumber('period')
            ->name('calculations.periods.activate');
        Route::post('calculations/periods/{period}/close', [UnitCostPeriodController::class, 'close'])
            ->whereNumber('period')
            ->name('calculations.periods.close');
        Route::get('calculations/periods/{period}', [UnitCostPeriodController::class, 'show'])
            ->whereNumber('period')
            ->name('calculations.periods.show');
    });

    Route::middleware('permission:view_unit_cost_results|edit_unit_cost_parameters|manage_calculations')->group(function () {
        Route::get('calculations/periods/{period}/components/{component}', [UnitCostParameterController::class, 'edit'])
            ->whereNumber('period')
            ->where('component', 'global|[0-9]+')
            ->name('calculations.parameters.edit');
    });

    Route::middleware('permission:edit_unit_cost_parameters|manage_calculations')->group(function () {
        Route::put('calculations/periods/{period}/components/{component}', [UnitCostParameterController::class, 'update'])
            ->whereNumber('period')
            ->where('component', 'global|[0-9]+')
            ->name('calculations.parameters.update');
        Route::post('calculations/parameters/preview', [UnitCostParameterController::class, 'preview'])
            ->name('calculations.parameters.preview');
    });

    Route::middleware('permission:manage_unit_cost_components|manage_unit_cost_parameters|view_unit_cost_formula_history|manage_unit_cost_masters|manage_calculations')->group(function () {
        Route::get('calculations/master', [UnitCostMasterController::class, 'index'])
            ->name('calculations.master');
    });

    Route::middleware('permission:manage_unit_cost_components|manage_unit_cost_masters|manage_calculations')->group(function () {
        Route::get('calculations/master/components/create', [UnitCostMasterController::class, 'createComponent'])
            ->name('calculations.master.components.create');
        Route::post('calculations/master/components', [UnitCostMasterController::class, 'storeComponent'])
            ->name('calculations.master.components.store');
        Route::get('calculations/master/components/{component}/edit', [UnitCostMasterController::class, 'editComponent'])
            ->whereNumber('component')
            ->name('calculations.master.components.edit');
        Route::put('calculations/master/components/{component}', [UnitCostMasterController::class, 'updateComponent'])
            ->whereNumber('component')
            ->name('calculations.master.components.update');
        Route::delete('calculations/master/components/{component}', [UnitCostMasterController::class, 'destroyComponent'])
            ->whereNumber('component')
            ->name('calculations.master.components.destroy');
    });

    Route::middleware('permission:manage_unit_cost_parameters|manage_unit_cost_masters|manage_calculations')->group(function () {
        Route::get('calculations/master/parameters/create', [UnitCostMasterController::class, 'createParameter'])
            ->name('calculations.master.parameters.create');
        Route::post('calculations/master/parameters', [UnitCostMasterController::class, 'storeParameter'])
            ->name('calculations.master.parameters.store');
        Route::get('calculations/master/parameters/{parameter}/edit', [UnitCostMasterController::class, 'editParameter'])
            ->whereNumber('parameter')
            ->name('calculations.master.parameters.edit');
        Route::put('calculations/master/parameters/{parameter}', [UnitCostMasterController::class, 'updateParameter'])
            ->whereNumber('parameter')
            ->name('calculations.master.parameters.update');
        Route::delete('calculations/master/parameters/{parameter}', [UnitCostMasterController::class, 'destroyParameter'])
            ->whereNumber('parameter')
            ->name('calculations.master.parameters.destroy');
    });

    Route::middleware('permission:view_unit_cost_results|manage_calculations')->group(function () {
        Route::get('calculations/history', [UnitCostPeriodController::class, 'history'])
            ->name('calculations.history.index');
        Route::get('calculations/history/{snapshot}', [UnitCostPeriodController::class, 'historyShow'])
            ->whereNumber('snapshot')
            ->name('calculations.history.show');
    });

    Route::middleware('permission:approve_unit_cost_results|approve_calculations')->group(function () {
        Route::post('calculations/periods/{period}/approve', [UnitCostPeriodController::class, 'approve'])
            ->whereNumber('period')
            ->name('calculations.periods.approve');
    });

    // Buku Tarif unit sendiri (role operator unit): unit diambil dari profil
    // pengguna, jadi namanya dibedakan agar tidak bentrok dengan pola menu admin.
    Route::middleware('permission:manage_my_tarif_unit')->group(function () {
        Route::get('buku-tarif-saya', [TarifUnitBookController::class, 'myUnit'])
            ->name('buku-tarif-saya.index');
        Route::get('buku-tarif-saya/export-xlsx', [TarifUnitBookController::class, 'exportMyUnitXlsx'])
            ->name('buku-tarif-saya.export-xlsx');
        Route::get('buku-tarif-saya/export-pdf', [TarifUnitBookController::class, 'exportMyUnitPdf'])
            ->name('buku-tarif-saya.export-pdf');
        Route::get('buku-tarif-saya/tarif/{tarif}', [TarifUnitBookController::class, 'myShow'])
            ->name('buku-tarif-saya.tarif');

        // Konfigurasi prefix: lihat dengan izin kelola, ubah/hapus dengan
        // izin terpisah (kontrak permission lama dipertahankan).
        Route::get('buku-tarif-saya/prefixes', [TarifUnitServicePrefixController::class, 'myIndex'])
            ->name('buku-tarif-saya.prefixes.index');
        Route::get('buku-tarif-saya/prefixes/create', [TarifUnitServicePrefixController::class, 'myCreate'])
            ->middleware('permission:edit_my_tarif_unit')
            ->name('buku-tarif-saya.prefixes.create');
        Route::post('buku-tarif-saya/prefixes', [TarifUnitServicePrefixController::class, 'myStore'])
            ->middleware('permission:edit_my_tarif_unit')
            ->name('buku-tarif-saya.prefixes.store');
        Route::get('buku-tarif-saya/prefixes/{prefix}/edit', [TarifUnitServicePrefixController::class, 'myEdit'])
            ->middleware('permission:edit_my_tarif_unit')
            ->name('buku-tarif-saya.prefixes.edit');
        Route::put('buku-tarif-saya/prefixes/{prefix}', [TarifUnitServicePrefixController::class, 'myUpdate'])
            ->middleware('permission:edit_my_tarif_unit')
            ->name('buku-tarif-saya.prefixes.update');
        Route::delete('buku-tarif-saya/prefixes/{prefix}', [TarifUnitServicePrefixController::class, 'myDestroy'])
            ->middleware('permission:delete_my_tarif_unit')
            ->name('buku-tarif-saya.prefixes.destroy');
    });

    // Buku Tarif admin: daftar unit + tarif dinamis dari prefix service.
    Route::middleware('permission:manage_tarif_units')->group(function () {
        Route::get('tarif-units', [TarifUnitBookController::class, 'index'])
            ->name('tarif-units.index');
        Route::get('tarif-units/unit/{organization_unit}', [TarifUnitBookController::class, 'unit'])
            ->name('tarif-units.unit');
        Route::get('tarif-units/unit/{organization_unit}/export-xlsx', [TarifUnitBookController::class, 'exportUnitXlsx'])
            ->name('tarif-units.unit.export-xlsx');
        Route::get('tarif-units/unit/{organization_unit}/export-pdf', [TarifUnitBookController::class, 'exportUnitPdf'])
            ->name('tarif-units.unit.export-pdf');
        Route::get('tarif-units/unit/{organization_unit}/tarif/{tarif}', [TarifUnitBookController::class, 'show'])
            ->name('tarif-units.unit.tarif');

        // Konfigurasi prefix per unit.
        Route::get('tarif-units/unit/{organization_unit}/prefixes', [TarifUnitServicePrefixController::class, 'index'])
            ->name('tarif-units.prefixes.index');
        Route::get('tarif-units/unit/{organization_unit}/prefixes/create', [TarifUnitServicePrefixController::class, 'create'])
            ->middleware('permission:edit_tarif_units')
            ->name('tarif-units.prefixes.create');
        Route::post('tarif-units/unit/{organization_unit}/prefixes', [TarifUnitServicePrefixController::class, 'store'])
            ->middleware('permission:edit_tarif_units')
            ->name('tarif-units.prefixes.store');
        Route::get('tarif-units/unit/{organization_unit}/prefixes/{prefix}/edit', [TarifUnitServicePrefixController::class, 'edit'])
            ->middleware('permission:edit_tarif_units')
            ->name('tarif-units.prefixes.edit');
        Route::put('tarif-units/unit/{organization_unit}/prefixes/{prefix}', [TarifUnitServicePrefixController::class, 'update'])
            ->middleware('permission:edit_tarif_units')
            ->name('tarif-units.prefixes.update');
        Route::delete('tarif-units/unit/{organization_unit}/prefixes/{prefix}', [TarifUnitServicePrefixController::class, 'destroy'])
            ->middleware('permission:delete_tarif_units')
            ->name('tarif-units.prefixes.destroy');
    });

    // Tarif Management routes
    Route::middleware('permission:manage_tarifs')->group(function () {
        Route::get('tarifs/import', [TarifImportController::class, 'index'])->name('tarif-import.index');
        Route::post('tarifs/import/scan', [TarifImportController::class, 'scan'])->name('tarif-import.scan');
        Route::post('tarifs/import/commit', [TarifImportController::class, 'commit'])->name('tarif-import.commit');
        Route::get('tarifs/import/batches', [TarifImportController::class, 'batches'])->name('tarif-import.batches');
        Route::get('tarifs/import/batches/status', [TarifImportController::class, 'batchStatus'])->name('tarif-import.batches.status');
        Route::get('tarifs/import/batches/{batch}/preview', [TarifImportController::class, 'preview'])->name('tarif-import.batches.preview')->whereNumber('batch');
        Route::get('tarifs/import/batches/{batch}', [TarifImportController::class, 'show'])->name('tarif-import.batches.show')->whereNumber('batch');
        Route::post('tarifs/import/batches/{batch}/retry-scan', [TarifImportController::class, 'retryScan'])->name('tarif-import.batches.retry-scan')->whereNumber('batch');
        Route::post('tarifs/import/batches/{batch}/retry', [TarifImportController::class, 'retryBatch'])->name('tarif-import.batches.retry')->whereNumber('batch');
        Route::post('tarifs/import/batches/{batch}/kill', [TarifImportController::class, 'killBatch'])->name('tarif-import.batches.kill')->whereNumber('batch');
        Route::delete('tarifs/import/batches/{batch}', [TarifImportController::class, 'destroyBatch'])->name('tarif-import.batches.destroy')->whereNumber('batch');
        Route::get('tarifs/import/template', [TarifImportController::class, 'template'])->name('tarif-import.template');
        Route::get('tarifs/export-xlsx', [TarifImportController::class, 'exportXlsx'])->name('tarif-import.export-xlsx');
        Route::get('tarifs/export', [TarifController::class, 'export'])->name('tarifs.export');
        // Edit/update dan hapus dipisah per permission.
        Route::resource('tarifs', TarifController::class)
            ->middlewareFor(['edit', 'update'], 'permission:edit_tarifs')
            ->middlewareFor('destroy', 'permission:delete_tarifs');
    });

});
