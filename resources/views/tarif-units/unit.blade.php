@extends('layouts.app')

@section('title', 'Buku Tarif - '.$unit->name)

@section('content')
@php
    $ownUnit = $ownUnit ?? false;
    $exportBase = $ownUnit ? 'buku-tarif-saya.export' : 'tarif-units.unit.export';
    $exportParams = $ownUnit ? request()->query() : (['organization_unit' => $unit->id] + request()->query());
    $prefixUrl = $ownUnit ? route('buku-tarif-saya.prefixes.index') : route('tarif-units.prefixes.index', $unit);
    $backUrl = $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.index');
    $canManagePrefix = auth()->user()->hasPermission($ownUnit ? 'edit_my_tarif_unit' : 'edit_tarif_units');
    $inactivePrefixCount = $prefixes->where('is_active', false)->count();
    $statusLabel = fn (string $status) => match ($status) {
        'ACTIVE' => 'Aktif',
        'UPCOMING' => 'Akan Berlaku',
        'EXPIRED' => 'Kedaluwarsa',
        default => $status,
    };
@endphp
<div class="w-full mx-auto">
    <x-card padding="false">
        <x-slot name="title">Buku Tarif: {{ $unit->name }}</x-slot>
        <x-slot name="subtitle">
            {{ $unit->code }}
            &bull; {{ $unit->type?->display_name ?: ($unit->type?->name ?? '-') }}
            @if($unit->parent) &bull; Induk: {{ $unit->parent->name }} @endif
            &bull; Prefix aktif:
            @if(!empty($activePrefixes))
                <span class="font-mono font-semibold text-gray-700">{{ implode(', ', $activePrefixes) }}</span>
                @if($inactivePrefixCount > 0)
                    <span class="text-gray-400">(+{{ $inactivePrefixCount }} nonaktif)</span>
                @endif
            @else
                <span class="font-medium text-amber-700">belum ada</span>
                @if($canManagePrefix)
                    <a href="{{ $prefixUrl }}" class="font-semibold text-sp-primary hover:underline">Tambah prefix</a>
                @endif
            @endif
        </x-slot>
        <x-slot name="actions">
            @if($canManagePrefix)
                <a href="{{ $prefixUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-signpost-split"></i> Kelola Prefix
                </a>
            @endif
            <a href="{{ route($exportBase.'-xlsx', $exportParams) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
            </a>
            <a href="{{ route($exportBase.'-pdf', $exportParams) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
            @unless($ownUnit)
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    <i class="bi bi-arrow-left"></i> Daftar Unit
                </a>
            @endunless
        </x-slot>

        <div class="px-4 py-3 border-b border-gray-100 bg-white">
            <form method="GET" class="flex flex-col lg:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label for="search" class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input type="text" id="search" name="search" value="{{ request('search') }}"
                               placeholder="Cari kode/keterangan service, provider, atau kelas..."
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-full bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                    </div>
                </div>
                <div class="lg:w-48">
                    <label for="status" class="block text-xs font-semibold text-gray-600 mb-1">Status Tarif</label>
                    <select id="status" name="status" class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                        <option value="">Semua</option>
                        <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                        <option value="UPCOMING" {{ request('status') === 'UPCOMING' ? 'selected' : '' }}>Akan Berlaku</option>
                        <option value="EXPIRED" {{ request('status') === 'EXPIRED' ? 'selected' : '' }}>Kedaluwarsa</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $ownUnit ? route('buku-tarif-saya.index') : route('tarif-units.unit', $unit) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-x-lg mr-1.5 text-xs"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-funnel mr-1.5 text-xs"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <x-table :columns="['Kode Service', 'Keterangan', 'Jenis', 'Provider', 'Kelas', 'Tarif (Rp)', 'Periode Berlaku', 'Status', 'Aksi']" :pagination="$tarifs" :showNumber="true" :perPage="(int) request('per_page', 25)" :perPageOptions="[25, 50, 100]" :maxHeight="'70vh'" empty="Tidak ada tarif yang cocok dengan prefix aktif unit ini." class="border-0 rounded-none shadow-none">
            @foreach($tarifs as $tarif)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-center text-gray-500">{{ ($tarifs->firstItem() ?? 1) + $loop->index }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-mono font-medium text-gray-900">{{ $tarif->service?->code ?? '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 max-w-md">{{ $tarif->service?->description ?: ($tarif->service?->name ?? '-') }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $tarif->jenisTarif?->name ?? '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 font-mono">{{ $tarif->provider?->code ?? '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $tarif->serviceClass?->name ?? '-' }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900">Rp {{ number_format((float) $tarif->tariff, 0, ',', '.') }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                    {{ $tarif->valid_date_from?->format('d/m/Y') ?? '-' }}
                    <span class="text-gray-400">&rarr;</span>
                    {{ $tarif->end_date_to?->format('d/m/Y') ?? '-' }}
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ \App\Models\Tarif::badgeClass($tarif->status) }}">
                        {{ $statusLabel($tarif->status) }}
                    </span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <a href="{{ $ownUnit ? route('buku-tarif-saya.tarif', $tarif) : route('tarif-units.unit.tarif', [$unit, $tarif]) }}"
                       class="inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors"
                       title="Detail tarif">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
