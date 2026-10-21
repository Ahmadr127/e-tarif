@extends('layouts.app')

@section('title', 'Master Data Kalkulasi Unit Cost')

@section('content')
@php
    $sourceLabels = [
        'global' => 'Global',
        'manual' => 'Manual',
        'integration' => 'Integrasi',
        'derived' => 'Turunan Formula',
    ];
    $formatDefault = function ($value): string {
        if ($value === null) {
            return '-';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    };
    $totalVariables = $components->sum(fn ($costComponent) => $costComponent->variables->count()) + $globalVariables->count();
    $totalFormulas = $components->sum(fn ($costComponent) => $costComponent->formulas->count());
@endphp

<div class="w-full mx-auto space-y-4">
    <x-card padding="false">
        <x-slot name="title">Master Data Kalkulasi Unit Cost</x-slot>
        <x-slot name="subtitle">Komponen biaya, variabel, dan formula yang dipakai engine</x-slot>
        <x-slot name="actions">
            <a href="{{ route('calculations.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Riwayat
            </a>
            <a href="{{ route('calculations.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-play-fill"></i> Jalankan Kalkulasi
            </a>
        </x-slot>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 px-4 py-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Komponen</div>
                <div class="mt-1 text-2xl font-bold text-sp-navy">{{ $components->count() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Variabel</div>
                <div class="mt-1 text-2xl font-bold text-sp-navy">{{ $totalVariables }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Formula</div>
                <div class="mt-1 text-2xl font-bold text-sp-navy">{{ $totalFormulas }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Formula Aktif</div>
                <div class="mt-1 text-2xl font-bold text-green-700">{{ $activeFormulaCount }}</div>
            </div>
        </div>

        <div class="px-4 pb-4">
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Halaman ini bersifat baca-saja. Master awal berasal dari seeder. Perubahan formula
                sebaiknya dibuat sebagai versi baru agar hasil run historis tetap dapat diaudit.
            </div>
        </div>
    </x-card>

    <x-card padding="false">
        <x-slot name="title">Parameter Global</x-slot>
        <x-slot name="subtitle">Variabel tanpa komponen biaya</x-slot>

        <x-table :columns="['Kode', 'Nama', 'Tipe', 'Scope', 'Sumber', 'Default', 'Wajib', 'Status']" class="border-0 rounded-none shadow-none" empty="Tidak ada parameter global.">
            @foreach($globalVariables as $variable)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $variable->code }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-800">{{ $variable->name }}</td>
                    <td class="px-4 py-2.5 text-xs text-gray-600">{{ $variable->value_type }}</td>
                    <td class="px-4 py-2.5 text-xs text-gray-600">{{ $variable->scope }}</td>
                    <td class="px-4 py-2.5 text-xs text-gray-600">{{ $sourceLabels[$variable->source_type] ?? $variable->source_type }}</td>
                    <td class="px-4 py-2.5 font-mono text-sm text-gray-800">{{ $formatDefault($variable->default_value) }}</td>
                    <td class="px-4 py-2.5 text-xs">{!! $variable->is_required ? '<span class="text-red-600 font-semibold">Ya</span>' : '<span class="text-gray-400">Tidak</span>' !!}</td>
                    <td class="px-4 py-2.5">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $variable->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' }}">
                            {{ $variable->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    @foreach($components as $costComponent)
        <details class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden" {{ $loop->first ? 'open' : '' }}>
            <summary class="cursor-pointer list-none flex items-center justify-between gap-3 px-4 py-3 bg-white hover:bg-gray-50 transition-colors">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold text-sp-navy">{{ $costComponent->name }}</span>
                        <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full bg-gray-100 text-gray-600">{{ $costComponent->category }}</span>
                        <span class="font-mono text-[11px] text-gray-400">{{ $costComponent->code }}</span>
                    </div>
                    @if($costComponent->description)
                        <p class="mt-0.5 text-xs text-gray-500">{{ $costComponent->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500 flex-shrink-0">
                    <span>{{ $costComponent->variables->count() }} variabel</span>
                    <span>&bull;</span>
                    <span>{{ $costComponent->formulas->count() }} formula</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
            </summary>

            <div class="border-t border-gray-100">
                <div class="px-4 py-2 bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-500">Variabel</div>
                <x-table :columns="['Kode', 'Nama', 'Tipe', 'Scope', 'Sumber', 'Default', 'Wajib', 'Status']" class="border-0 rounded-none shadow-none" empty="Tidak ada variabel.">
                    @foreach($costComponent->variables as $variable)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $variable->code }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-800">{{ $variable->name }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-600">{{ $variable->value_type }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-600">{{ $variable->scope }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-600">{{ $sourceLabels[$variable->source_type] ?? $variable->source_type }}</td>
                            <td class="px-4 py-2.5 font-mono text-sm text-gray-800">{{ $formatDefault($variable->default_value) }}</td>
                            <td class="px-4 py-2.5 text-xs">{!! $variable->is_required ? '<span class="text-red-600 font-semibold">Ya</span>' : '<span class="text-gray-400">Tidak</span>' !!}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $variable->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' }}">
                                    {{ $variable->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="px-4 py-2 bg-gray-50 text-xs font-bold uppercase tracking-wide text-gray-500 border-t border-gray-100">Formula</div>
                <x-table :columns="['Kode', 'Nama', 'Ekspresi', 'Hasil', 'Versi', 'Status']" class="border-0 rounded-none shadow-none" empty="Tidak ada formula.">
                    @foreach($costComponent->formulas as $formula)
                        <tr class="hover:bg-gray-50 transition-colors align-top">
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-700 whitespace-nowrap">{{ $formula->code }}</td>
                            <td class="px-4 py-2.5 text-sm text-gray-800 min-w-[180px]">{{ $formula->name }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-600 min-w-[240px]">{{ $formula->expression }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-600 whitespace-nowrap">{{ $formula->result_unit ?? '-' }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-600 whitespace-nowrap">v{{ $formula->version }}</td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $formula->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' }}">
                                    {{ $formula->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>
        </details>
    @endforeach
</div>
@endsection
