@extends('layouts.app')

@section('title', 'Manajemen Role')

@section('content')
@php
    $roleDataMap = collect($roles->items())->mapWithKeys(fn($role) => [
        $role->id => [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'description' => $role->description,
            'is_active' => (bool) $role->is_active,
        ],
    ])->all();

    $groupLabels = [
        'view' => 'Lihat',
        'manage' => 'Kelola',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'lainnya' => 'Lainnya',
    ];
    $groupOrder = array_keys($groupLabels);
    $permissionGroups = $permissionGroups
        ->sortBy(fn($items, $key) => array_search($key, $groupOrder, true) === false ? 99 : array_search($key, $groupOrder, true));
@endphp
<div class="space-y-5" x-data="roleModal({{ Js::from($roleDataMap) }})">

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-stats label="Total Role" :value="$totalRoles" icon="bi-shield-lock-fill" color="bg-blue-500" />
        <x-stats label="Role Aktif" :value="$activeRoles" icon="bi-shield-check" color="bg-sp-primary" />
        <x-stats label="Role Tidak Aktif" :value="$inactiveRoles" icon="bi-shield-x" color="bg-red-500" />
    </div>

    {{-- ===================== DATA ROLE ===================== --}}
    {{-- <x-card padding="false">
        <x-slot name="title">Data Role</x-slot>
        <x-slot name="subtitle">Daftar role dalam sistem</x-slot>
        <x-slot name="actions">
            <form method="GET" class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari role..."
                    class="pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-full bg-gray-50 focus:bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary outline-none transition-colors w-44">
            </form>
            <button type="button" @click="openCreate()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-plus-lg"></i> Tambah Role
            </button>
        </x-slot>
    </x-card> --}}

    {{-- ===================== MATRIKS PERMISSION ===================== --}}
    <x-card padding="false">
        <x-slot name="title">Kelola Permission</x-slot>
        <x-slot name="subtitle">Centang hak akses tiap role, lalu simpan sekali untuk semua role</x-slot>
         <button type="button" @click="openCreate()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-plus-lg"></i> Tambah Role
            </button>
        <form action="{{ route('roles.permissions.sync') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="overflow-auto max-h-[70vh]">
                <table class="w-full min-w-max text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="sticky left-0 top-0 z-30 bg-gray-100 px-4 py-2.5 text-left font-semibold text-gray-700 min-w-[16rem]">
                                Permission
                            </th>
                            @foreach($matrixRoles as $role)
                                <th class="sticky top-0 z-20 bg-gray-100 px-3 py-2.5 text-center font-semibold text-gray-700 whitespace-nowrap">
                                    <label class="inline-flex flex-col items-center gap-1 cursor-pointer" title="Centang / kosongkan semua untuk {{ $role->display_name }}">
                                        <span>{{ $role->display_name }}</span>
                                        <span class="text-[10px] font-normal text-gray-500 font-mono">{{ $role->name }}</span>
                                        <input type="checkbox"
                                               data-role-toggle="{{ $role->id }}"
                                               class="rounded border-gray-300 text-sp-primary focus:ring-sp-primary">
                                    </label>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @forelse($permissionGroups as $group => $items)
                            <tr class="bg-gray-50">
                                <td class="sticky left-0 z-10 bg-gray-50 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-500">
                                    {{ $groupLabels[$group] ?? ucfirst($group) }}
                                </td>
                                <td colspan="{{ $matrixRoles->count() }}" class="bg-gray-50"></td>
                            </tr>
                            @foreach($items as $permission)
                                <tr class="border-t border-gray-100 hover:bg-gray-50/70 transition-colors">
                                    <td class="sticky left-0 z-10 bg-white px-4 py-2">
                                        <div class="text-sm text-gray-900">{{ $permission->display_name }}</div>
                                        <div class="text-[10px] font-mono text-gray-400">{{ $permission->name }}</div>
                                    </td>
                                    @foreach($matrixRoles as $role)
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox"
                                                   name="permissions[{{ $role->id }}][]"
                                                   value="{{ $permission->id }}"
                                                   data-role="{{ $role->id }}"
                                                   @checked($role->permissions->contains('id', $permission->id))
                                                   class="rounded border-gray-300 text-sp-primary focus:ring-sp-primary">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="{{ $matrixRoles->count() + 1 }}" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada permission.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    <i class="bi bi-info-circle mr-1"></i>
                    Perubahan berlaku untuk seluruh role sekaligus. Role tanpa centang berarti tidak punya akses.
                </p>
                <button type="submit"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-floppy"></i> Simpan Hak Akses
                </button>
            </div>
        </form>
    </x-card>

    {{-- ===================== MODAL TAMBAH / EDIT ROLE ===================== --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close()"></div>

        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-sp-primary text-white rounded-t-lg">
                <h5 class="font-bold">
                    <i class="bi mr-2" :class="isEdit ? 'bi-pencil-square' : 'bi-plus-circle'"></i>
                    <span x-text="isEdit ? 'Edit Role' : 'Tambah Role'"></span>
                </h5>
                <button type="button" @click="close()" class="text-white/80 hover:text-white transition-colors"><i class="bi bi-x-lg"></i></button>
            </div>

            <form :action="action" method="POST" class="p-4">
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                @csrf

                <x-input x-model="name" id="role-name" name="name" label="Nama Role" placeholder="Contoh: admin" :required="true" />
                <x-input x-model="displayName" id="role-display-name" name="display_name" label="Display Name" placeholder="Contoh: Administrator" :required="true" />

                <div class="mb-3">
                    <label for="role-description" class="block text-sm font-semibold text-sp-navy mb-1">Deskripsi</label>
                    <textarea name="description" id="role-description" rows="3" x-model="description" placeholder="Deskripsi role"
                        class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors"></textarea>
                </div>

                <div class="mb-3">
                    <label for="role-is-active" class="block text-sm font-semibold text-sp-navy mb-1">Status</label>
                    <select name="is_active" id="role-is-active" x-model="isActive"
                        class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="1">Aktif</option>
                        <option value="0">Tidak Aktif</option>
                    </select>
                </div>

                <p class="mb-3 text-xs text-gray-500">
                    <i class="bi bi-info-circle mr-1"></i>
                    Hak akses role diatur di tabel <strong>Kelola Permission</strong> di bawah.
                </p>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="close()"
                        class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-save"></i> <span x-text="isEdit ? 'Simpan Perubahan' : 'Simpan Role'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('roleModal', (initialRoles = {}) => ({
        roles: initialRoles,
        open: false,
        isEdit: false,
        action: '',
        name: '',
        displayName: '',
        description: '',
        isActive: '1',

        openCreate() {
            this.isEdit = false;
            this.action = '{{ route('roles.store') }}';
            this.name = '';
            this.displayName = '';
            this.description = '';
            this.isActive = '1';
            this.open = true;
        },

        openEdit(id) {
            const role = this.roles[id];
            if (!role) return;

            this.isEdit = true;
            this.action = '{{ url('roles') }}/' + role.id;
            this.name = role.name;
            this.displayName = role.display_name;
            this.description = role.description || '';
            this.isActive = role.is_active ? '1' : '0';
            this.open = true;
        },

        close() {
            this.open = false;
        }
    }));
});

// Checkbox di header kolom: centang/kosongkan semua permission role tersebut.
document.addEventListener('change', (e) => {
    const toggle = e.target.closest('[data-role-toggle]');
    if (!toggle) return;

    const roleId = toggle.dataset.roleToggle;
    document.querySelectorAll('input[data-role="' + roleId + '"]').forEach((cb) => {
        cb.checked = toggle.checked;
    });
});
</script>
@endpush
