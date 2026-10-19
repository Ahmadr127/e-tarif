@extends('layouts.app')

@section('title', 'Input Buku Tarif')

@section('content')
@php
    $ownUnit = $ownUnit ?? false;
    $existing = $existing ?? null;
    $backUrl = $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit);
    $editRoute = $ownUnit ? 'buku-tarif-saya.edit' : 'tarif-units.edit';
    $destroyRoute = $ownUnit ? 'buku-tarif-saya.destroy' : 'tarif-units.destroy';
    $searchUrl = $ownUnit ? route('buku-tarif-saya.search-tarifs') : route('tarif-units.search-tarifs');
    $canEdit = auth()->user()->hasPermission($ownUnit ? 'edit_my_tarif_unit' : 'edit_tarif_units');
    $canDelete = auth()->user()->hasPermission($ownUnit ? 'delete_my_tarif_unit' : 'delete_tarif_units');
@endphp
<div class="w-full mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
        {{-- Kiri: form input buku tarif --}}
        <div class="lg:col-span-7 xl:col-span-8">
            <x-card padding="false">
                <x-slot name="title">Input Buku Tarif</x-slot>
                <x-slot name="subtitle">Tambahkan satu atau beberapa tarif ke buku tarif unit {{ $unit->name }}</x-slot>
                <x-slot name="actions">
                    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </x-slot>

                <form action="{{ $ownUnit ? route('buku-tarif-saya.store') : route('tarif-units.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="organization_unit_id" value="{{ $unit->id }}">

                    {{-- Unit dikunci sesuai unit yang diklik di daftar Buku Tarif --}}
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-gray-500">Unit Organisasi</p>
                                <p class="text-sm font-medium text-gray-900">{{ $unit->name }} <span class="font-mono text-xs text-gray-500">({{ $unit->code }})</span></p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    {{ $unit->type?->display_name ?: ($unit->type?->name ?? '-') }}
                                    @if($unit->parent) &bull; Induk: {{ $unit->parent->name }} @endif
                                </p>
                            </div>
                            @unless($ownUnit)
                                <a href="{{ route('tarif-units.index') }}" class="text-xs font-semibold text-sp-primary hover:text-sp-primary-dark whitespace-nowrap">Ganti unit</a>
                            @endunless
                        </div>
                    </div>

                    @error('items')
                        <div class="px-4 py-2 text-sm text-red-600 bg-red-50 border-b border-red-100">{{ $message }}</div>
                    @enderror

                    <x-tarif-input-table
                        :rows="old('items', [])"
                        :search-url="$searchUrl" />

                    <div class="flex justify-end gap-2 px-4 py-3 border-t border-gray-100 bg-white">
                        <a href="{{ $backUrl }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                            Batal
                        </a>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                            <i class="bi bi-check-lg"></i> Simpan ke Buku Tarif
                        </button>
                    </div>
                </form>
            </x-card>
        </div>

        {{-- Kanan: tarif yang sudah terdaftar di unit ini --}}
        <div class="lg:col-span-5 xl:col-span-4">
            <x-card padding="false">
                <x-slot name="title">Sudah ada di buku tarif unit ini</x-slot>
                <x-slot name="subtitle">
                    {{ $existing?->total() ?? 0 }} tarif terdaftar — tarif yang sama tidak bisa diinput dua kali
                </x-slot>

                @if($existing && $existing->count() > 0)
                    <ul class="divide-y divide-gray-100">
                        @foreach($existing as $row)
                            <li class="px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $row->tarif?->display_label ?? '-' }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ $row->tarif?->provider?->code ?? '-' }}
                                            &bull; {{ $row->tarif?->serviceClass?->name ?? '-' }}
                                        </p>
                                        <p class="mt-1 text-sm font-mono text-gray-900">
                                            Rp {{ number_format((float) ($row->tarif?->tariff ?? 0), 0, ',', '.') }}
                                        </p>
                                        @if($row->notes)
                                            <p class="mt-1 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($row->notes, 80) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                                        @if($row->status === 'active')
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">Non-Aktif</span>
                                        @endif
                                        <div class="flex items-center gap-2">
                                            @if($canEdit)
                                                <a href="{{ route($editRoute, $row) }}" class="text-xs font-semibold text-sp-primary hover:text-sp-primary-dark whitespace-nowrap">Edit</a>
                                            @endif
                                            @if($canDelete)
                                                <form action="{{ route($destroyRoute, $row) }}" method="POST" class="inline"
                                                      onsubmit="return confirm('Yakin ingin menghapus tarif ini dari buku tarif unit?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Hapus tarif"
                                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-500 border border-gray-300 bg-white hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-colors">
                                                        <i class="bi bi-trash text-xs"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @if($existing->total() > $existing->count())
                        <div class="px-4 py-3 border-t border-gray-100">
                            <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sp-primary hover:text-sp-primary-dark">
                                <i class="bi bi-list-ul"></i> Lihat semua {{ $existing->total() }} tarif
                            </a>
                        </div>
                    @endif
                @else
                    <div class="px-4 py-8 text-center">
                        <i class="bi bi-journal-x text-2xl text-gray-300"></i>
                        <p class="mt-2 text-sm text-gray-500">Belum ada tarif di buku tarif unit ini.</p>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
@endsection
