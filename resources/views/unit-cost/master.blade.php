@extends('layouts.app')

@section('title', 'Master Unit Cost')

@section('content')
@php
    $scopeLabels = [
        'global' => 'Global',
        'unit' => 'Unit',
        'period' => 'Periode',
        'unit_period' => 'Unit + Periode',
    ];
    $sourceLabels = [
        'manual' => 'Input Manual',
        'integration' => 'Integrasi',
        'derived' => 'Turunan / Formula',
        'global' => 'Global',
    ];
    $valueTypeLabels = [
        'decimal' => 'Desimal',
        'integer' => 'Bilangan Bulat',
        'boolean' => 'Boolean',
        'string' => 'Teks',
    ];
@endphp

<div class="w-full mx-auto space-y-4">
    @include('unit-cost._tabs')

    <x-card padding="false">
        <x-slot name="title">Master Komponen dan Parameter</x-slot>
        <x-slot name="subtitle">Definisi master dipakai ulang oleh seluruh periode. Nilai dan rumus tidak disimpan di sini.</x-slot>
        <x-slot name="actions">
            <a href="{{ route('calculations.master.components.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-plus-lg"></i> Tambah Komponen
            </a>
            <a href="{{ route('calculations.master.parameters.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md bg-white hover:bg-sp-primary/5 transition-colors">
                <i class="bi bi-plus-lg"></i> Tambah Parameter
            </a>
        </x-slot>

        <div class="p-4 space-y-5">
            @if($globalVariables->isNotEmpty())
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-blue-800">Variabel Global</div>
                            <div class="text-xs text-blue-700">Semua variable parameter aktif dapat dipakai lintas komponen pada formula.</div>
                        </div>
                        <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-blue-800 border border-blue-200">
                            {{ $globalVariables->count() }} variabel
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($globalVariables as $parameter)
                            <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-xs font-mono font-semibold text-blue-800 border border-blue-200"
                                  title="{{ $parameter->name }}">
                                {{ '{'.$parameter->formulaKey().'}' }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-800">Komponen</h3>
                    <span class="text-xs text-gray-500">{{ $components->count() }} komponen</span>
                </div>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-sm min-w-[48rem]">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 w-10">No.</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Kode</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-56">Nama Komponen</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-32">Kategori</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Parameter</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Status</th>
                                <th class="px-3 py-2.5 text-right font-semibold text-gray-700 min-w-40">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($components as $component)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2.5 text-center text-gray-500">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2.5 font-mono text-xs font-semibold text-sp-navy break-all">{{ $component->code }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-semibold text-gray-800">{{ $component->name }}</div>
                                        @if($component->description)
                                            <div class="text-xs text-gray-500">{{ $component->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $component->category ?: '-' }}</td>
                                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $component->parameters_count }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $component->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $component->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('calculations.master.components.edit', $component) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <form method="POST" action="{{ route('calculations.master.components.destroy', $component) }}"
                                                  onsubmit="return confirm('Hapus komponen {{ $component->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        @disabled($component->parameters_count > 0)
                                                        title="{{ $component->parameters_count > 0 ? 'Komponen masih memiliki parameter.' : 'Hapus komponen' }}"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada komponen master.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Parameter</h3>
                        <p class="text-xs text-gray-500">Variable adalah nama global yang direferensikan di formula, misalnya {{ '{biaya_sdm_total}' }}.</p>
                    </div>
                    <span class="text-xs text-gray-500">{{ $parameters->count() }} parameter</span>
                </div>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-sm min-w-[78rem]">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 w-10">No.</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-40">Komponen</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-44">Variable Global</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-44">Parameter</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Tipe</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Scope</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-32">Sumber</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Satuan</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-20">Wajib</th>
                                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Status</th>
                                <th class="px-3 py-2.5 text-right font-semibold text-gray-700 min-w-40">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($parameters as $parameter)
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-3 py-2.5 text-center text-gray-500">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="text-sm font-semibold text-gray-800">{{ $parameter->component?->name ?? 'Parameter Global' }}</div>
                                        <div class="font-mono text-[11px] text-gray-500">{{ $parameter->component?->code ?? 'GLOBAL' }}</div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-mono text-xs font-semibold text-sp-navy break-all">{{ '{'.$parameter->formulaKey().'}' }}</div>
                                        <div class="font-mono text-[10px] text-gray-400 break-all">kode: {{ $parameter->code }}</div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-semibold text-gray-800">{{ $parameter->name }}</div>
                                        @if($parameter->description)
                                            <div class="text-xs text-gray-500">{{ $parameter->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $valueTypeLabels[$parameter->value_type] ?? $parameter->value_type }}</td>
                                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $scopeLabels[$parameter->scope] ?? $parameter->scope }}</td>
                                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $sourceLabels[$parameter->source_type] ?? $parameter->source_type }}</td>
                                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $parameter->unit ?: '-' }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $parameter->is_required ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $parameter->is_required ? 'Wajib' : 'Opsional' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $parameter->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $parameter->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('calculations.master.parameters.edit', $parameter) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <form method="POST" action="{{ route('calculations.master.parameters.destroy', $parameter) }}"
                                                  onsubmit="return confirm('Hapus parameter {{ $parameter->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        @disabled($parameter->entries_count > 0 || $parameter->formula_versions_count > 0)
                                                        title="{{ ($parameter->entries_count > 0 || $parameter->formula_versions_count > 0) ? 'Parameter sudah memiliki nilai atau riwayat formula.' : 'Hapus parameter' }}"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada parameter master.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-card>

    <x-card padding="false">
        <x-slot name="title">Riwayat Versi Formula</x-slot>
        <x-slot name="subtitle">Setiap perubahan teks rumus pada periode membuat versi baru. Versi lama tidak diubah.</x-slot>

        <x-table :columns="['No', 'Periode', 'Parameter', 'Variable', 'Versi', 'Ekspresi', 'Dibuat']"
                 :pagination="$formulaVersions" empty="Belum ada versi formula."
                 class="border-0 rounded-none shadow-none">
            @foreach($formulaVersions as $version)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-center text-gray-500">{{ ($formulaVersions->firstItem() ?? 1) + $loop->index }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $version->period?->name ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-800">{{ $version->parameter?->name ?? '-' }}</div>
                        <div class="font-mono text-[11px] text-gray-500">{{ $version->parameter?->code }}</div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-sp-navy break-all">
                        {{ $version->parameter ? '{'.$version->parameter->formulaKey().'}' : '-' }}
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-700">v{{ $version->version }}</td>
                    <td class="px-4 py-3">
                        <code class="text-xs text-gray-700 break-all">{{ $version->expression }}</code>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                        {{ $version->created_at?->format('d/m/Y H:i') ?? '-' }}
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
