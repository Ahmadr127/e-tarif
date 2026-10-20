@extends('layouts.app')

@section('title', 'Buku Tarif')

@section('content')
<div class="w-full mx-auto">
    <x-card padding="false">
        <x-slot name="title">Buku Tarif</x-slot>
        <x-slot name="subtitle">Pilih unit organisasi untuk melihat tarif yang tercakup oleh prefix kode service unit tersebut</x-slot>
        <x-slot name="actions">
            <a href="{{ route('tarifs.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-list-ul"></i> Daftar Tarif
            </a>
        </x-slot>

        <div class="px-4 py-3 border-b border-gray-100 bg-white">
            <form method="GET" class="flex flex-col lg:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label for="search" class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input type="text" id="search" name="search" value="{{ request('search') }}"
                               placeholder="Cari nama atau kode unit..."
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-full bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                    </div>
                </div>
                <div class="lg:w-56">
                    <label for="type_id" class="block text-xs font-semibold text-gray-600 mb-1">Tipe Unit</label>
                    <select id="type_id" name="type_id" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="">Semua Tipe</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" {{ (string) request('type_id') === (string) $type->id ? 'selected' : '' }}>{{ $type->display_name ?: $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:w-40">
                    <label for="status" class="block text-xs font-semibold text-gray-600 mb-1">Status Unit</label>
                    <select id="status" name="status" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="active" {{ request('status', 'active') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Non-Aktif</option>
                        <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Semua</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('tarif-units.index') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-x-lg mr-1.5 text-xs"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-funnel mr-1.5 text-xs"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <x-table :columns="['Unit Organisasi', 'Tipe Unit', 'Induk', 'Prefix Service', 'Status', 'Aksi']" :pagination="$units" :showNumber="true" empty="Tidak ada unit organisasi yang cocok." class="border-0 rounded-none shadow-none">
            @foreach($units as $unit)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-center text-gray-500">{{ ($units->firstItem() ?? 1) + $loop->index }}</td>
                <td class="px-4 py-3">
                    <div class="text-sm font-medium text-gray-900">{{ $unit->name }}</div>
                    <div class="mt-0.5 text-xs text-gray-500 font-mono">{{ $unit->code }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $unit->type?->display_name ?: ($unit->type?->name ?? '-') }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $unit->parent?->name ?? '—' }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <a href="{{ route('tarif-units.unit', $unit) }}" class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $unit->active_prefixes_count > 0 ? 'bg-blue-100 text-blue-800 hover:bg-blue-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} transition-colors">
                        {{ $unit->active_prefixes_count }} aktif / {{ $unit->service_prefixes_count }} prefix
                    </a>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @if($unit->is_active)
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">Non-Aktif</span>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        @if(auth()->user()->hasPermission('edit_tarif_units'))
                            <a href="{{ route('tarif-units.prefixes.index', $unit) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                                <i class="bi bi-signpost-split"></i> Kelola Prefix
                            </a>
                        @endif
                        <a href="{{ route('tarif-units.unit', $unit) }}" title="Lihat buku tarif unit ini"
                           class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary rounded-md transition-colors">
                            <i class="bi bi-journal-bookmark"></i> Buku Tarif
                        </a>
                    </div>
                </td>
            </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
