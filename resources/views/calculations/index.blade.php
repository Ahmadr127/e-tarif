@extends('layouts.app')

@section('title', 'Kalkulasi Unit Cost')

@section('content')
@php
    $statusLabels = [
        'draft' => 'Draf',
        'calculated' => 'Sudah Dihitung',
        'approved' => 'Disetujui',
        'failed' => 'Gagal',
    ];
    $statusClasses = [
        'draft' => 'bg-gray-100 text-gray-700',
        'calculated' => 'bg-blue-100 text-blue-800',
        'approved' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
    ];
    $summaryCards = [
        ['key' => 'calculated', 'label' => 'Sudah Dihitung', 'class' => 'text-blue-700', 'bg' => 'bg-blue-50'],
        ['key' => 'approved', 'label' => 'Disetujui', 'class' => 'text-green-700', 'bg' => 'bg-green-50'],
        ['key' => 'failed', 'label' => 'Gagal', 'class' => 'text-red-700', 'bg' => 'bg-red-50'],
        ['key' => 'draft', 'label' => 'Draf', 'class' => 'text-gray-700', 'bg' => 'bg-gray-100'],
    ];
@endphp

<div class="w-full mx-auto space-y-4">
    <x-card padding="false">
        <x-slot name="title">Kalkulasi Unit Cost</x-slot>
        <x-slot name="subtitle">Riwayat run kalkulasi, snapshot input, dan status persetujuan</x-slot>
        <x-slot name="actions">
            <a href="{{ route('calculations.master') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-database"></i> Master Data
            </a>
            <a href="{{ route('calculations.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-play-fill"></i> Jalankan Kalkulasi
            </a>
        </x-slot>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 px-4 pt-4">
            @foreach($summaryCards as $card)
                <div class="rounded-lg border border-gray-200 {{ $card['bg'] }} px-4 py-3">
                    <div class="text-xs font-semibold text-gray-500">{{ $card['label'] }}</div>
                    <div class="mt-1 text-2xl font-bold {{ $card['class'] }}">{{ (int) ($summary[$card['key']] ?? 0) }}</div>
                </div>
            @endforeach
        </div>

        <div class="px-4 py-3 border-b border-gray-100 bg-white">
            <form method="GET" class="flex flex-col lg:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label for="search" class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Cari kode run atau kode unit..."
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-full bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                    </div>
                </div>
                <div class="lg:w-56">
                    <label for="unit_code" class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
                    <select id="unit_code" name="unit_code" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="">Semua Unit</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->code }}" {{ ($filters['unit_code'] ?? '') === $unit->code ? 'selected' : '' }}>
                                {{ $unit->name }} ({{ $unit->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:w-32">
                    <label for="period_year" class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
                    <input type="number" id="period_year" name="period_year" min="2000" max="2100"
                           value="{{ $filters['period_year'] ?? '' }}"
                           class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                </div>
                <div class="lg:w-44">
                    <label for="status" class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="">Semua Status</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('calculations.index') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-x-lg mr-1.5 text-xs"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-funnel mr-1.5 text-xs"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <x-table :columns="['Kode Run', 'Unit', 'Tahun', 'Status', 'Versi Formula', 'Total Unit Cost', 'Dibuat', 'Aksi']"
                 :pagination="$runs" :showNumber="true" :perPage="(int) ($filters['per_page'] ?? 10)"
                 :perPageOptions="[10, 25, 50, 100]" empty="Belum ada run kalkulasi."
                 class="border-0 rounded-none shadow-none">
            @foreach($runs as $run)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-center text-gray-500">{{ ($runs->firstItem() ?? 1) + $loop->index }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('calculations.show', $run) }}" class="font-mono text-xs font-semibold text-sp-primary hover:underline">
                            {{ $run->run_code }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900">{{ $run->organizationUnit?->name ?? $run->unit_code }}</div>
                        <div class="mt-0.5 text-xs font-mono text-gray-500">{{ $run->unit_code }}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $run->period_year }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusClasses[$run->status] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $statusLabels[$run->status] ?? $run->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">v{{ $run->formula_version }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900">
                        @if($run->total_unit_cost !== null)
                            Rp {{ number_format((float) $run->total_unit_cost, 2, ',', '.') }}
                        @else
                            <span class="text-gray-400">Belum ditetapkan</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                        <div>{{ $run->calculated_at?->format('d/m/Y H:i') ?? $run->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                        <div class="text-gray-400">{{ $run->creator?->name ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('calculations.show', $run) }}" title="Lihat detail"
                           class="inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
