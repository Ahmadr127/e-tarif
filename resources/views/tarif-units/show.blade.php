@extends('layouts.app')

@section('title', 'Detail Tarif - '.$unit->name)

@section('content')
@php
    $ownUnit = $ownUnit ?? false;
    $backUrl = $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit);
    $statusLabel = match ($tarif->status) {
        'ACTIVE' => 'Aktif',
        'UPCOMING' => 'Akan Berlaku',
        'EXPIRED' => 'Kedaluwarsa',
        default => $tarif->status,
    };
@endphp
<div class="max-w-3xl mx-auto">
    <x-card>
        <x-slot name="title">Detail Tarif</x-slot>
        <x-slot name="subtitle">{{ $tarif->display_label }}</x-slot>
        <x-slot name="actions">
            <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali ke Buku Tarif
            </a>
        </x-slot>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="border border-gray-200 rounded-md p-3 md:col-span-2">
                <dt class="font-semibold text-gray-500">Service</dt>
                <dd class="mt-1 font-medium text-gray-900">
                    <span class="font-mono">{{ $tarif->service?->code ?? '-' }}</span>
                    &mdash; {{ $tarif->service?->description ?: ($tarif->service?->name ?? '-') }}
                </dd>
                <dd class="mt-0.5 text-xs text-gray-500">
                    Provider {{ $tarif->provider?->code ?? '-' }} &bull; Jenis {{ $tarif->jenisTarif?->name ?? '-' }}
                </dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Tarif (Rp)</dt>
                <dd class="mt-1 font-mono font-semibold text-gray-900">Rp {{ number_format((float) $tarif->tariff, 0, ',', '.') }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Kelas</dt>
                <dd class="mt-1 text-gray-900">{{ $tarif->serviceClass?->name ?? '-' }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Surgery Type</dt>
                <dd class="mt-1 text-gray-900">{{ $tarif->surgery_type ?: 'Tanpa Bedah' }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Helper</dt>
                <dd class="mt-1 text-gray-900">{{ $tarif->helper ?: '-' }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Periode Berlaku</dt>
                <dd class="mt-1 text-gray-900">
                    {{ $tarif->valid_date_from?->format('d/m/Y') ?? '-' }}
                    <span class="text-gray-400">&rarr;</span>
                    {{ $tarif->end_date_to?->format('d/m/Y') ?? '-' }}
                </dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3">
                <dt class="font-semibold text-gray-500">Status</dt>
                <dd class="mt-1">
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\Tarif::badgeClass($tarif->status) }}">
                        {{ $statusLabel }}
                    </span>
                </dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3 md:col-span-2">
                <dt class="font-semibold text-gray-500">Unit Organisasi</dt>
                <dd class="mt-1 font-medium text-gray-900">{{ $unit->name }}</dd>
                <dd class="mt-0.5 text-xs text-gray-500 font-mono">{{ $unit->code }}</dd>
            </div>

            <div class="border border-gray-200 rounded-md p-3 md:col-span-2">
                <dt class="font-semibold text-gray-500">Prefix yang Cocok</dt>
                <dd class="mt-1 flex flex-wrap gap-1.5">
                    @forelse($matchedPrefixes as $prefix)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-mono font-semibold rounded-full bg-blue-100 text-blue-800">
                            <i class="bi bi-signpost-2"></i>{{ $prefix }}
                        </span>
                    @empty
                        <span class="text-gray-500">-</span>
                    @endforelse
                </dd>
                <dd class="mt-1 text-xs text-gray-500">Tarif ini masuk cakupan unit karena kode service diawali salah satu prefix aktif di atas.</dd>
            </div>
        </dl>
    </x-card>
</div>
@endsection
