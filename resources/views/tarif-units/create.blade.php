@extends('layouts.app')

@section('title', 'Input Buku Tarif')

@section('content')
@php $ownUnit = $ownUnit ?? false; @endphp
<div class="w-full mx-auto">
    <x-card padding="false">
        <x-slot name="title">Input Buku Tarif</x-slot>
        <x-slot name="subtitle">Tambahkan satu atau beberapa tarif ke buku tarif unit {{ $unit->name }}</x-slot>
        <x-slot name="actions">
            <a href="{{ $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
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
                :search-url="$ownUnit ? route('buku-tarif-saya.search-tarifs') : route('tarif-units.search-tarifs')" />

            <div class="flex justify-end gap-2 px-4 py-3 border-t border-gray-100 bg-white">
                <a href="{{ $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit) }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-check-lg"></i> Simpan ke Buku Tarif
                </button>
            </div>
        </form>
    </x-card>

    {{-- @if($existing->total() > 0)
    <div class="mt-4">
        <x-card padding="false">
            <x-slot name="title">Sudah ada di buku tarif unit ini</x-slot>
            <x-slot name="subtitle">{{ $existing->total() }} tarif terdaftar — tarif yang sama tidak bisa diinput dua kali</x-slot>
            <x-slot name="actions">
                <a href="{{ $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    <i class="bi bi-list-ul"></i> Lihat Semua
                </a>
            </x-slot>

            <ul class="divide-y divide-gray-100">
                @foreach($existing as $row)
                    <li class="px-4 py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900 truncate">{{ $row->tarif?->display_label ?? '-' }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $row->tarif?->provider?->code ?? '-' }}
                                &bull; Rp {{ number_format((float) ($row->tarif?->tariff ?? 0), 0, ',', '.') }}
                            </p>
                        </div>
                        <a href="{{ $ownUnit ? route('buku-tarif-saya.edit', $row) : route('tarif-units.edit', $row) }}" class="text-xs font-semibold text-sp-primary hover:text-sp-primary-dark whitespace-nowrap">Edit</a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
    @endif --}}
</div>
@endsection
