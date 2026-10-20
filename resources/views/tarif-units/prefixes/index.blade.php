@extends('layouts.app')

@section('title', 'Prefix Buku Tarif - '.$unit->name)

@section('content')
@php
    $ownUnit = $ownUnit ?? false;
    $routeBase = $ownUnit ? 'buku-tarif-saya.prefixes' : 'tarif-units.prefixes';
    $unitParams = $ownUnit ? [] : [$unit];
    $backUrl = $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit);
    $canEdit = auth()->user()->hasPermission($ownUnit ? 'edit_my_tarif_unit' : 'edit_tarif_units');
    $canDelete = auth()->user()->hasPermission($ownUnit ? 'delete_my_tarif_unit' : 'delete_tarif_units');
@endphp
<div class="w-full mx-auto">
    <x-card padding="false">
        <x-slot name="title">Prefix Buku Tarif: {{ $unit->name }}</x-slot>
        <x-slot name="subtitle">
            {{ $unit->code }}
            &bull; {{ $unit->type?->display_name ?: ($unit->type?->name ?? '-') }}
        </x-slot>
        <x-slot name="actions">
            @if($canEdit)
                <a href="{{ route($routeBase.'.create', $unitParams) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-plus-lg"></i> Tambah Prefix
                </a>
            @endif
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-journal-bookmark"></i> Buku Tarif
            </a>
        </x-slot>

        <div class="px-4 py-3 border-b border-gray-100 bg-blue-50/60 text-xs text-blue-900">
            <p class="font-semibold">Cara kerja prefix</p>
            <p class="mt-1">
                Tarif masuk buku tarif unit bila <span class="font-mono">services.code</span> diawali salah satu prefix
                <span class="font-semibold">aktif</span> di bawah. Contoh: prefix <span class="font-mono">JPU</span>
                mencakup <span class="font-mono">JPU01</span> dan <span class="font-mono">JPU10</span>, tetapi tidak
                mencakup <span class="font-mono">XJPU01</span>. Unit induk tidak mewariskan prefix ke unit anak.
            </p>
        </div>

        <x-table :columns="['Prefix', 'Status', 'Catatan', 'Dibuat', 'Aksi']" empty="Belum ada prefix. Buku tarif unit akan kosong sampai prefix ditambahkan." class="border-0 rounded-none shadow-none">
            @foreach($prefixes as $prefix)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-sm font-mono font-semibold rounded-md bg-gray-100 text-gray-900">
                        <i class="bi bi-signpost-2"></i>{{ $prefix->prefix }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @if($prefix->is_active)
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Non-Aktif</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-md">{{ $prefix->notes ?: '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">{{ $prefix->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <x-actions>
                        @if($canEdit)
                            <x-actions-item href="{{ route($routeBase.'.edit', $ownUnit ? [$prefix] : [$unit, $prefix]) }}" icon="bi-pencil" label="Edit" />
                        @endif
                        @if($canDelete)
                            <x-actions-form
                                action="{{ route($routeBase.'.destroy', $ownUnit ? [$prefix] : [$unit, $prefix]) }}"
                                method="DELETE"
                                icon="bi-trash"
                                label="Hapus"
                                confirm="Yakin ingin menghapus prefix ini? Tarif dengan kode tersebut tidak lagi tampil di buku tarif unit."
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
