@extends('layouts.app')

@section('title', ($prefix ? 'Ubah' : 'Tambah').' Prefix - '.$unit->name)

@section('content')
@php
    $ownUnit = $ownUnit ?? false;
    $routeBase = $ownUnit ? 'buku-tarif-saya.prefixes' : 'tarif-units.prefixes';
    $unitParams = $ownUnit ? [] : [$unit];
    $formAction = $prefix
        ? route($routeBase.'.update', $ownUnit ? [$prefix] : [$unit, $prefix])
        : route($routeBase.'.store', $unitParams);
    $backUrl = $ownUnit ? route('buku-tarif-saya.prefixes.index') : route('tarif-units.prefixes.index', $unit);
@endphp
<div class="max-w-2xl mx-auto">
    <x-card>
        <x-slot name="title">{{ $prefix ? 'Ubah' : 'Tambah' }} Prefix</x-slot>
        <x-slot name="subtitle">Unit {{ $unit->name }} ({{ $unit->code }})</x-slot>
        <x-slot name="actions">
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </x-slot>

        <form method="POST" action="{{ $formAction }}" class="space-y-5">
            @csrf
            @if($prefix)
                @method('PUT')
            @endif

            <div>
                <label for="prefix" class="block text-sm font-semibold text-gray-700 mb-1">Prefix <span class="text-red-500">*</span></label>
                <input type="text" id="prefix" name="prefix"
                       value="{{ old('prefix', $prefix?->prefix) }}"
                       maxlength="{{ \App\Models\TarifUnitServicePrefix::MAX_LENGTH }}"
                       required autofocus
                       oninput="this.value = this.value.toUpperCase()"
                       placeholder="Contoh: JPU"
                       class="w-full px-3 py-2 text-sm font-mono uppercase border rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary @error('prefix') border-red-400 @else border-gray-300 @enderror">
                <p class="mt-1 text-xs text-gray-500">
                    Huruf, angka, titik, dan strip. Tanpa spasi atau wildcard (<span class="font-mono">%</span>, <span class="font-mono">_</span>).
                    Prefix otomatis diubah ke huruf besar dan harus unik per unit.
                </p>
                @error('prefix')
                    <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-semibold text-gray-700 mb-1">Catatan</label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000"
                          placeholder="Opsional, misalnya sumber atau penanggung jawab prefix"
                          class="w-full px-3 py-2 text-sm border rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary @error('notes') border-red-400 @else border-gray-300 @enderror">{{ old('notes', $prefix?->notes) }}</textarea>
                @error('notes')
                    <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-start gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       {{ old('is_active', $prefix?->is_active ?? true) ? 'checked' : '' }}
                       class="mt-0.5 h-4 w-4 rounded border-gray-300 text-sp-primary focus:ring-sp-primary/30">
                <div>
                    <label for="is_active" class="text-sm font-semibold text-gray-700">Prefix aktif</label>
                    <p class="text-xs text-gray-500">Hanya prefix aktif yang dipakai menghitung cakupan buku tarif.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                <a href="{{ $backUrl }}" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-save"></i> Simpan
                </button>
            </div>
        </form>
    </x-card>
</div>
@endsection
