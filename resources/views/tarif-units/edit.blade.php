@extends('layouts.app')

@section('title', 'Edit Buku Tarif')

@section('content')
@php $ownUnit = $ownUnit ?? false; @endphp
<div class="max-w-3xl mx-auto">
    <x-card>
        <x-slot name="title">Edit Buku Tarif</x-slot>
        <x-slot name="subtitle">{{ $unit->name }} &bull; {{ $mapping->tarif?->display_label ?? '-' }}</x-slot>
        <x-slot name="actions">
            @unless($ownUnit)
                <a href="{{ route('tarif-units.show', $mapping) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    <i class="bi bi-eye"></i> Detail
                </a>
            @endunless
            <a href="{{ $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </x-slot>

        {{-- Unit dikunci: hanya tarif/status/catatan yang bisa diubah --}}
        <div class="mb-4 border border-gray-200 rounded-md p-3 bg-gray-50">
            <p class="text-xs font-semibold text-gray-500">Unit Organisasi</p>
            <p class="text-sm font-medium text-gray-900">{{ $unit->name }} <span class="font-mono text-xs text-gray-500">({{ $unit->code }})</span></p>
            <p class="mt-0.5 text-xs text-gray-500">
                {{ $unit->type?->display_name ?: ($unit->type?->name ?? '-') }}
                @if($unit->parent) &bull; Induk: {{ $unit->parent->name }} @endif
            </p>
        </div>

        <form action="{{ $ownUnit ? route('buku-tarif-saya.update', $mapping) : route('tarif-units.update', $mapping) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="organization_unit_id" value="{{ $unit->id }}">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <x-tarif-picker name="tarif_id" label="Pilih Tarif" :selected="$selectedTarif"
                        :search-url="$ownUnit ? route('buku-tarif-saya.search-tarifs') : route('tarif-units.search-tarifs')" />
                </div>

                <div>
                    <label for="status" class="block text-sm font-semibold text-sp-navy mb-1">Status <span class="text-red-500">*</span></label>
                    <select id="status" name="status" required class="w-full text-sm px-3 py-2 border rounded-md outline-none transition-colors bg-white border-gray-300 focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                        <option value="active" {{ old('status', $mapping->status) === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ old('status', $mapping->status) === 'inactive' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-semibold text-sp-navy mb-1">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Keterangan tambahan (opsional)"
                              class="w-full text-sm px-3 py-2 border rounded-md outline-none transition-colors bg-white placeholder-gray-400 border-gray-300 focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">{{ old('notes', $mapping->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <a href="{{ $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit) }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-check-lg"></i> Perbarui
                </button>
            </div>
        </form>
    </x-card>
</div>
@endsection
