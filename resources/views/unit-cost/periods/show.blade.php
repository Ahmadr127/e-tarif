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
        {{-- Header ringkas: informasi periode, konteks unit, dan aksi dalam satu baris. --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-100 px-4 py-2">
            <div class="flex min-w-0 items-center gap-2">
                <span class="text-base font-bold text-sp-navy"
                      title="{{ $period->name }} ({{ $period->code }}) · Tahun {{ $period->period_year }}">
                    {{ $period->name }}
                </span>
                <span class="inline-flex px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $periodStatusClasses[$period->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $periodStatusLabels[$period->status] ?? $period->status }}
                </span>
            </div>

            <div class="flex min-w-0 items-center gap-1.5 text-xs text-gray-600">
                <span class="text-gray-300">|</span>
                <span class="text-gray-500">Unit:</span>
                @if($unit)
                    <span class="max-w-[16rem] truncate font-semibold text-gray-800"
                          title="{{ $unit->name }} ({{ $unit->code }})">
                        {{ $unit->name }} ({{ $unit->code }})
                    </span>
                @else
                    <span class="font-semibold text-gray-800">Tanpa unit (global)</span>
                @endif
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-1.5">
                <div class="flex items-center gap-1.5">
                    <label class="text-xs font-semibold text-gray-600">Tahun</label>
                    <div class="w-28">
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
                </div>

                @unless($isScopedUser)
                    <a href="{{ route('calculations.units.index') }}"
                       class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5"
                       title="Ganti unit">
                        <i class="bi bi-arrow-left-right"></i> Ganti Unit
                    </a>
                @endunless

                @if($isAdmin && $period->status !== 'closed')
                    <a href="{{ route('calculations.periods.edit', $period) }}"
                       class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-gray-600 border border-gray-300 rounded bg-white hover:bg-gray-50 transition-colors"
                       title="Edit periode">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                @endif

                <a href="{{ route('calculations.periods.official-report', ['period' => $period, 'unit_id' => $unit?->id ?? '0']) }}"
                   class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-gray-600 border border-gray-300 rounded bg-white hover:bg-gray-50 transition-colors"
                   title="Unduh surat resmi Unit Cost (PDF)">
                    <i class="bi bi-file-earmark-pdf"></i> Surat Resmi
                </a>

                @if($canApprove && $period->status !== 'closed')
                    <form method="POST" action="{{ route('calculations.periods.approve', $period) }}" onsubmit="return confirm('Setujui hasil unit cost untuk konteks unit ini? Snapshot resmi tidak dapat diubah.')">
                        @csrf
                        <input type="hidden" name="unit_id" value="{{ $unit?->id ?? '0' }}">
                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-white rounded bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                            <i class="bi bi-check2-circle"></i> Setujui
                        </button>
                    </form>
                @endif
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
