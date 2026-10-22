@extends('layouts.app')

@section('title', 'Komponen Periode '.$period->name)

@section('content')
@php
    $isAdmin = $isAdmin ?? false;
    $isScopedUser = $isScopedUser ?? false;
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
    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ $isScopedUser ? route('calculations.my-unit') : route('calculations.units.index') }}" class="font-semibold text-sp-primary hover:underline">
            {{ $isScopedUser ? 'Unit Cost Saya' : 'Unit Cost' }}
        </a>
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
            @if($isAdmin && $period->status !== 'closed')
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


        <div class="px-4 py-3 border-b border-gray-100 bg-white flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm">
                <span class="text-gray-500">Konteks unit:</span>
                @if($unit)
                    <span class="font-semibold text-gray-800">{{ $unit->name }} ({{ $unit->code }})</span>
                @else
                    <span class="font-semibold text-gray-800">Tanpa unit (global)</span>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs font-semibold text-gray-600">Tahun</label>
                <div class="w-40">
                    <x-searchable-dropdown
                        name="period_id"
                        label-field="label"
                        :options="$periods->map(fn ($periodOption) => (object) [
                            'id' => (string) $periodOption->id,
                            'label' => $periodOption->period_year
                                .(trim((string) $periodOption->name) === (string) $periodOption->period_year ? '' : ' · '.$periodOption->name),
                        ])"
                        :selected="(string) $period->id"
                        placeholder="Pilih Tahun"
                        onchange="window.location.href = '{{ route('calculations.periods.show', ['period' => '__PERIOD__']) }}'.replace('__PERIOD__', this.value) + '?unit_id={{ $unit?->id ?? 0 }}'"
                    />
                </div>
                @unless($isScopedUser)
                    <a href="{{ route('calculations.units.index') }}"
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                        <i class="bi bi-arrow-left-right"></i> Ganti Unit
                    </a>
                @endunless
            </div>
        </div>

        @if($isScopedUser)
            @include('unit-cost.periods._table_user')
        @else
            @include('unit-cost.periods._table_admin')
        @endif
    </x-card>
</div>
@endsection
