@extends('layouts.app')

@section('title', 'Buku Tarif - '.$unit->name)

@section('content')
@php
    $ownUnit = $ownUnit ?? false;
    // Export mengikuti filter aktif (search/status) di halaman ini.
    $exportBase = $ownUnit ? 'buku-tarif-saya.export' : 'tarif-units.unit.export';
    $exportParams = $ownUnit ? request()->query() : (['organization_unit' => $unit->id] + request()->query());
    $canEdit = auth()->user()->hasPermission($ownUnit ? 'edit_my_tarif_unit' : 'edit_tarif_units');
    $canDelete = auth()->user()->hasPermission($ownUnit ? 'delete_my_tarif_unit' : 'delete_tarif_units');
@endphp
<div class="w-full mx-auto">
    <x-card padding="false">
        <x-slot name="title">Buku Tarif: {{ $unit->name }}</x-slot>
        <x-slot name="subtitle">
            {{ $unit->code }}
            &bull; {{ $unit->type?->display_name ?: ($unit->type?->name ?? '-') }}
            @if($unit->parent) &bull; Induk: {{ $unit->parent->name }} @endif
        </x-slot>
        <x-slot name="actions">
            @if($unit->is_active)
                <a href="{{ $ownUnit ? route('buku-tarif-saya.create') : route('tarif-units.create', ['unit_id' => $unit->id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-plus-lg"></i> Input Buku Tarif
                </a>
            @endif
            <a href="{{ route($exportBase.'-xlsx', $exportParams) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
            </a>
            <a href="{{ route($exportBase.'-pdf', $exportParams) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
            @unless($ownUnit)
                <a href="{{ route('tarif-units.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    <i class="bi bi-arrow-left"></i> Daftar Unit
                </a>
            @endunless
        </x-slot>

        <div class="px-4 py-3 border-b border-gray-100 bg-white">
            <form method="GET" class="flex flex-col lg:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label for="search" class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input type="text" id="search" name="search" value="{{ request('search') }}"
                               placeholder="Cari kode/keterangan tarif atau catatan..."
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-full bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                    </div>
                </div>
                <div class="lg:w-40">
                    <label for="status" class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="">Semua</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-x-lg mr-1.5 text-xs"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-funnel mr-1.5 text-xs"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <x-table :columns="['Tarif', 'Provider', 'Kelas', 'Tarif (Rp)', 'Status', 'Catatan', 'Aksi']" :pagination="$mappings" :showNumber="true" :maxHeight="'70vh'" empty="Belum ada tarif di buku tarif unit ini." class="border-0 rounded-none shadow-none">
            @foreach($mappings as $mapping)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-center text-gray-500">{{ ($mappings->firstItem() ?? 1) + $loop->index }}</td>
                <td class="px-4 py-3">
                    <div class="text-sm font-medium text-gray-900 max-w-md">{{ $mapping->tarif?->display_label ?? '-' }}</div>
                    <div class="mt-0.5 text-xs text-gray-500">{{ $mapping->tarif?->jenisTarif?->name ?? '-' }}</div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-mono">{{ $mapping->tarif?->provider?->code ?? '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $mapping->tarif?->serviceClass?->name ?? '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900">Rp {{ number_format((float) ($mapping->tarif?->tariff ?? 0), 0, ',', '.') }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @if($mapping->status === 'active')
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">Non-Aktif</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">{{ $mapping->notes ? \Illuminate\Support\Str::limit($mapping->notes, 60) : '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <x-actions>
                        @unless($ownUnit)
                            <x-actions-item href="{{ route('tarif-units.show', $mapping) }}" icon="bi-eye" label="Detail" />
                        @endunless
                        @if($canEdit)
                            <x-actions-item href="{{ $ownUnit ? route('buku-tarif-saya.edit', $mapping) : route('tarif-units.edit', $mapping) }}" icon="bi-pencil" label="Edit" />
                        @endif
                        @if($canDelete)
                            <x-actions-form
                                action="{{ $ownUnit ? route('buku-tarif-saya.destroy', $mapping) : route('tarif-units.destroy', $mapping) }}"
                                method="DELETE"
                                icon="bi-trash"
                                label="Hapus"
                                confirm="Yakin ingin menghapus tarif ini dari buku tarif unit?"
                            />
                        @endif
                    </x-actions>
                </td>
            </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
