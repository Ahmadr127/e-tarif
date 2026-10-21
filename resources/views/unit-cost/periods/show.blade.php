@extends('layouts.app')

@section('title', 'Komponen Periode '.$period->name)

@section('content')
@php
    $periodStatusLabels = [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'closed' => 'Ditutup',
    ];
    $periodStatusClasses = [
        'draft' => 'bg-gray-100 text-gray-700',
        'active' => 'bg-blue-100 text-blue-800',
        'closed' => 'bg-slate-200 text-slate-700',
    ];
    $summaryStatus = [
        'valid' => ['label' => 'Lengkap', 'class' => 'bg-green-100 text-green-800'],
        'incomplete' => ['label' => 'Belum lengkap', 'class' => 'bg-amber-100 text-amber-800'],
        'invalid' => ['label' => 'Ada error', 'class' => 'bg-red-100 text-red-800'],
    ];
@endphp

<div class="w-full mx-auto space-y-4">
    @include('unit-cost._tabs')

    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('calculations.index') }}" class="font-semibold text-sp-primary hover:underline">Periode</a>
        <i class="bi bi-chevron-right text-xs"></i>
        <span class="font-semibold text-gray-700">{{ $period->name }}</span>
        <i class="bi bi-chevron-right text-xs"></i>
        <span>Komponen</span>
    </div>

    <x-card padding="false">
        <x-slot name="title">{{ $period->name }}</x-slot>
        <x-slot name="subtitle">{{ $period->code }} · Tahun {{ $period->period_year }}</x-slot>
        <x-slot name="actions">
            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $periodStatusClasses[$period->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $periodStatusLabels[$period->status] ?? $period->status }}
            </span>
            @if($period->status !== 'closed')
                <a href="{{ route('calculations.periods.edit', $period) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
            @if($canApprove && $period->status !== 'closed')
                <form method="POST" action="{{ route('calculations.periods.approve', $period) }}" onsubmit="return confirm('Setujui hasil unit cost untuk konteks unit ini? Snapshot resmi tidak dapat diubah.')">
                    @csrf
                    <input type="hidden" name="unit_id" value="{{ $unit?->id ?? '0' }}">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-check2-circle"></i> Setujui Hasil
                    </button>
                </form>
            @endif
        </x-slot>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 px-4 pt-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Komponen</div>
                <div class="mt-1 text-2xl font-bold text-gray-800">{{ (int) ($periodStats['components'] ?? 0) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-blue-50 px-4 py-3">
                <div class="text-xs font-semibold text-blue-600">Parameter Terkonfigurasi</div>
                <div class="mt-1 text-2xl font-bold text-blue-800">{{ (int) ($periodStats['configured'] ?? 0) }} / {{ (int) ($periodStats['total'] ?? 0) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-amber-50 px-4 py-3">
                <div class="text-xs font-semibold text-amber-700">Belum Lengkap</div>
                <div class="mt-1 text-2xl font-bold text-amber-800">{{ (int) ($periodStats['incomplete'] ?? 0) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-red-50 px-4 py-3">
                <div class="text-xs font-semibold text-red-700">Error</div>
                <div class="mt-1 text-2xl font-bold text-red-800">{{ (int) ($periodStats['invalid'] ?? 0) }}</div>
            </div>
        </div>

        <div class="px-4 py-3 border-b border-gray-100 bg-white">
            <form method="GET" action="{{ route('calculations.periods.show', $period) }}" class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="sm:w-80">
                    <label for="unit_id" class="block text-xs font-semibold text-gray-600 mb-1">Konteks Unit</label>
                    <select id="unit_id" name="unit_id" onchange="this.form.submit()"
                            class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="0" {{ $unit === null ? 'selected' : '' }}>Tanpa unit (global)</option>
                        @foreach($units as $unitOption)
                            <option value="{{ $unitOption->id }}" {{ $unit?->id === $unitOption->id ? 'selected' : '' }}>
                                {{ $unitOption->name }} ({{ $unitOption->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="text-xs text-gray-500 pb-2">
                    Parameter berscope unit disimpan terpisah untuk setiap unit.
                </div>
            </form>
        </div>

        <x-table :columns="['No', 'Komponen', 'Parameter', 'Terkonfigurasi', 'Belum Lengkap', 'Error', 'Status', 'Terakhir Diubah', 'Aksi']"
                 empty="Belum ada komponen aktif." class="border-0 rounded-none shadow-none">
            @php $rowNumber = 1; @endphp
            @if($globalCount > 0)
                @php
                    $global = $summaries['global'] ?? ['total' => 0, 'configured' => 0, 'incomplete' => 0, 'invalid' => 0, 'status' => 'incomplete', 'last_updated' => null];
                    $globalBadge = $summaryStatus[$global['status']] ?? $summaryStatus['incomplete'];
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-center text-gray-500">{{ $rowNumber++ }}</td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900">Parameter Global</div>
                        <div class="text-xs text-gray-500">Tidak terikat komponen</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ (int) $global['total'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ (int) $global['configured'] }}</td>
                    <td class="px-4 py-3 text-sm text-amber-700">{{ (int) $global['incomplete'] }}</td>
                    <td class="px-4 py-3 text-sm text-red-700">{{ (int) $global['invalid'] }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $globalBadge['class'] }}">{{ $globalBadge['label'] }}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                        {{ $global['last_updated']?->format('d/m/Y H:i') ?? '-' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('calculations.parameters.edit', ['period' => $period, 'component' => 'global', 'unit_id' => $unit?->id ?? '0']) }}"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                            <i class="bi bi-pencil-square"></i> Buka
                        </a>
                    </td>
                </tr>
            @endif

            @foreach($components as $component)
                @php
                    $summary = $summaries[(string) $component->id] ?? ['total' => 0, 'configured' => 0, 'incomplete' => 0, 'invalid' => 0, 'status' => 'incomplete', 'last_updated' => null];
                    $badge = $summaryStatus[$summary['status']] ?? $summaryStatus['incomplete'];
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-center text-gray-500">{{ $rowNumber++ }}</td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900">{{ $component->name }}</div>
                        <div class="text-xs font-mono text-gray-500">{{ $component->code }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ (int) $summary['total'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ (int) $summary['configured'] }}</td>
                    <td class="px-4 py-3 text-sm text-amber-700">{{ (int) $summary['incomplete'] }}</td>
                    <td class="px-4 py-3 text-sm text-red-700">{{ (int) $summary['invalid'] }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                        {{ $summary['last_updated']?->format('d/m/Y H:i') ?? '-' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($component->parameters_count > 0)
                            <a href="{{ route('calculations.parameters.edit', ['period' => $period, 'component' => $component->id, 'unit_id' => $unit?->id ?? '0']) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                                <i class="bi bi-pencil-square"></i> Buka
                            </a>
                        @else
                            <span class="text-xs text-gray-400">Belum ada parameter</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
