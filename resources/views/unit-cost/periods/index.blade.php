@extends('layouts.app')

@section('title', 'Periode Unit Cost')

@section('content')
@php
    $statusLabels = [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'closed' => 'Ditutup',
    ];
    $statusClasses = [
        'draft' => 'bg-gray-100 text-gray-700',
        'active' => 'bg-blue-100 text-blue-800',
        'closed' => 'bg-slate-200 text-slate-700',
    ];
@endphp

<div class="w-full mx-auto space-y-4">
    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <span class="font-semibold text-gray-700">Master</span>
        <i class="bi bi-chevron-right text-xs"></i>
        <span>Periode Unit Cost</span>
    </div>

    <x-card padding="false">
        <x-slot name="title">Periode Kalkulasi Unit Cost</x-slot>
        <x-slot name="subtitle">Pilih periode, buka komponen, lalu isi nilai atau rumus parameter</x-slot>
        <x-slot name="actions">
            {{-- <a href="{{ route('calculations.units.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-navy border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-buildings"></i> Daftar Unit
            </a> --}}
            <a href="{{ route('calculations.periods.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-plus-lg"></i> Tambah Periode
            </a>
        </x-slot>

        <div class="px-4 py-3 border-b border-gray-100 bg-white">
            <form method="GET" class="flex flex-col lg:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label for="search" class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Cari kode atau nama periode..."
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-full bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                    </div>
                </div>
                <div class="lg:w-32">
                    <label for="period_year" class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
                    <input type="number" id="period_year" name="period_year" min="2000" max="2100"
                           value="{{ $filters['period_year'] ?? '' }}"
                           class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                </div>
                <div class="lg:w-44">
                    <x-searchable-dropdown
                        label-field="label"
                        name="status"
                        label="Status"
                        :options="collect($statusLabels)->map(fn ($label, $value) => (object) ['id' => $value, 'label' => $label])->values()"
                        :selected="($filters['status'] ?? '') !== '' ? $filters['status'] : null"
                        placeholder="Semua Status"
                        empty-option="Semua Status"
                    />
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

        <x-table :columns="['Kode', 'Periode', 'Tahun', 'Status', 'Komponen', 'Terkonfigurasi', 'Belum Lengkap', 'Aksi']"
                 :pagination="$periods" :showNumber="true" :perPage="(int) ($filters['per_page'] ?? 10)"
                 :perPageOptions="[10, 25, 50, 100]" empty="Belum ada periode unit cost."
                 class="border-0 rounded-none shadow-none">
            @foreach($periods as $period)
                @php $stat = $stats[$period->id] ?? []; @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-center text-gray-500">{{ ($periods->firstItem() ?? 1) + $loop->index }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('calculations.periods.show', $period) }}" class="font-mono text-xs font-semibold text-sp-primary hover:underline">
                            {{ $period->code }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900">{{ $period->name }}</div>
                        <div class="mt-0.5 text-xs text-gray-500">{{ $period->creator?->name ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $period->period_year }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusClasses[$period->status] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $statusLabels[$period->status] ?? $period->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ (int) ($stat['components'] ?? 0) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                        {{ (int) ($stat['configured'] ?? 0) }} / {{ (int) ($stat['total'] ?? 0) }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @php $incomplete = (int) ($stat['incomplete'] ?? 0); @endphp
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $incomplete > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                            {{ $incomplete }}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                            {{-- <a href="{{ route('calculations.periods.show', $period) }}" title="Buka periode"
                               class="inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors">
                                <i class="bi bi-folder2-open"></i>
                            </a> --}}
                            <a href="{{ route('calculations.periods.edit', $period) }}" title="Edit periode"
                               class="inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($period->status !== 'closed')
                                <form method="POST" action="{{ route('calculations.periods.activate', $period) }}">
                                    @csrf
                                    <button type="submit" title="Aktifkan periode"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-green-700 border border-green-200 bg-white hover:bg-green-50 transition-colors">
                                        <i class="bi bi-check2-circle"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('calculations.periods.close', $period) }}" onsubmit="return confirm('Tutup periode ini? Setelah ditutup, nilai dan rumus tidak dapat diubah.')">
                                    @csrf
                                    <button type="submit" title="Tutup periode"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-slate-700 border border-slate-200 bg-white hover:bg-slate-50 transition-colors">
                                        <i class="bi bi-lock"></i>
                                    </button>
                                </form>
                            @endif
                            @if($period->status === 'draft')
                                <form method="POST" action="{{ route('calculations.periods.destroy', $period) }}" onsubmit="return confirm('Hapus periode draf ini beserta seluruh konfigurasinya?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus periode"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-red-600 border border-red-200 bg-white hover:bg-red-50 transition-colors">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
