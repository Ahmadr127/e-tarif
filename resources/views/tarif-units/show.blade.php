@extends('layouts.app')

@section('title', 'Detail Buku Tarif')

@section('content')
<div class="max-w-3xl mx-auto">
    <x-card>
        <x-slot name="title">Detail Buku Tarif</x-slot>
        <x-slot name="subtitle">{{ $mapping->tarif?->display_label ?? '-' }}</x-slot>
        <x-slot name="actions">
            <a href="{{ route('tarif-units.edit', $mapping) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <a href="{{ route('tarif-units.unit', $mapping->organization_unit_id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </x-slot>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="border border-gray-200 rounded-md p-3 md:col-span-2">
                <dt class="font-semibold text-gray-500">Tarif</dt>
                <dd class="mt-1 font-medium text-gray-900">{{ $mapping->tarif?->display_label ?? '-' }}</dd>
                <dd class="mt-0.5 text-xs text-gray-500">
                    Provider {{ $mapping->tarif?->provider?->code ?? '-' }} &bull; Jenis {{ $mapping->tarif?->jenisTarif?->name ?? '-' }}
                </dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Tarif (Rp)</dt>
                <dd class="mt-1 font-mono font-semibold text-gray-900">Rp {{ number_format((float) ($mapping->tarif?->tariff ?? 0), 0, ',', '.') }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Surgery Type</dt>
                <dd class="mt-1 text-gray-900">{{ $mapping->tarif?->surgery_type ?: 'Tanpa Bedah' }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Unit Organisasi</dt>
                <dd class="mt-1 font-medium text-gray-900">{{ $mapping->organizationUnit?->name ?? '-' }}</dd>
                <dd class="mt-0.5 text-xs text-gray-500 font-mono">{{ $mapping->organizationUnit?->code ?? '-' }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Tipe &amp; Induk Unit</dt>
                <dd class="mt-1 text-gray-900">{{ $mapping->organizationUnit?->type?->name ?? '-' }}</dd>
                <dd class="mt-0.5 text-xs text-gray-500">Induk: {{ $mapping->organizationUnit?->parent?->name ?? '—' }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Status</dt>
                <dd class="mt-1">
                    @if($mapping->status === 'active')
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">Non-Aktif</span>
                    @endif
                </dd>
            </div>

            @if($mapping->notes)
            <div class="border border-gray-200 rounded-md p-3 md:col-span-2">
                <dt class="font-semibold text-gray-500">Catatan</dt>
                <dd class="mt-1 text-gray-900">{{ $mapping->notes }}</dd>
            </div>
            @endif

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Dibuat</dt>
                <dd class="mt-1 text-gray-900">{{ $mapping->created_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Terakhir Diubah</dt>
                <dd class="mt-1 text-gray-900">{{ $mapping->updated_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
        </dl>
    </x-card>
</div>
@endsection
