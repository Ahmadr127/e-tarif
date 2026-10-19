<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\Store;
use App\Http\Requests\Role\Update;
use App\Http\Services\RoleService;
use App\Models\Permission;
use App\Models\Role;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roleService) {}

    public function index(\Illuminate\Http\Request $request)
    {
        $roles = $this->roleService->getRoles($request->only(['search', 'date_from', 'date_to', 'per_page']));
        $permissions = Permission::orderBy('name')->get();
        $stats = $this->roleService->getStats();

        // Matriks hak akses: kolom = role (semua role, bukan hanya halaman aktif),
        // baris = permission dikelompokkan per awalan (manage/view/edit/delete).
        $matrixRoles = Role::with('permissions')->orderBy('name')->get();
        $permissionGroups = $permissions->groupBy(
            fn (Permission $permission) => str_contains($permission->name, '_')
                ? str($permission->name)->before('_')->toString()
                : 'lainnya'
        );

        return view('roles.index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'matrixRoles' => $matrixRoles,
            'permissionGroups' => $permissionGroups,
            'totalRoles' => $stats['total'],
            'activeRoles' => $stats['active'],
            'inactiveRoles' => $stats['inactive'],
        ]);
    }

    /**
     * Simpan matriks permission (checkbox per role) sekaligus.
     */
    public function syncPermissions(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['integer', 'exists:permissions,id'],
        ]);

        $this->roleService->syncRolePermissions($data['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Hak akses role berhasil diperbarui.');
    }

    public function create()
    {
        $permissions = Permission::all();
        return view('roles.create', compact('permissions'));
    }

    public function store(Store $request)
    {
        $this->roleService->createRole($request->validated());
        return redirect()->route('roles.index')->with('success', 'Role berhasil dibuat!');
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all();
        return view('roles.edit', compact('role', 'permissions'));
    }

    public function update(Update $request, Role $role)
    {
        $this->roleService->updateRole($role, $request->validated());
        return redirect()->route('roles.index')->with('success', 'Role berhasil diperbarui!');
    }

    public function destroy(Role $role)
    {
        $result = $this->roleService->deleteRole($role);
        if ($result === false) {
            return redirect()->route('roles.index')->with('error', 'Role tidak dapat dihapus karena masih digunakan oleh pengguna!');
        }

        return redirect()->route('roles.index')->with('success', 'Role berhasil dihapus!');
    }
}
