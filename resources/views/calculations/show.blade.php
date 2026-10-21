@extends('layouts.app')

@section('title', 'Detail Kalkulasi Unit Cost')

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
    $formatNumber = function ($value, int $decimals = 6): string {
        $formatted = number_format((float) $value, $decimals, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    };
    $snapshotInputs = $run->parameter_snapshot['inputs'] ?? [];
    $canApprove = auth()->user()->hasPermission('approve_calculations');
@endphp

<div class="w-full mx-auto space-y-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <x-card padding="false">
        <x-slot name="title">
            <span class="font-mono">{{ $run->run_code }}</span>
        </x-slot>
        <x-slot name="subtitle">
            {{ $run->organizationUnit?->name ?? $run->unit_code }}
            &bull; {{ $run->unit_code }}
            &bull; Periode {{ $run->period_year }}
        </x-slot>
        <x-slot name="actions">
            <a href="{{ route('calculations.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Riwayat
            </a>
            <a href="{{ route('calculations.create', ['unit_code' => $run->unit_code, 'period_year' => $run->period_year]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary rounded-md transition-colors">
                <i class="bi bi-arrow-repeat"></i> Jalankan Ulang
            </a>
            @if($run->status === 'calculated' && $canApprove)
                <form method="POST" action="{{ route('calculations.approve', $run) }}" onsubmit="return confirm('Setujui hasil kalkulasi ini?')">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-green-600 hover:bg-green-700 transition-colors">
                        <i class="bi bi-check2-circle"></i> Setujui
                    </button>
                </form>
            @endif
        </x-slot>

        <div class="px-4 py-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Status</div>
                <div class="mt-1">
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusClasses[$run->status] ?? 'bg-gray-100 text-gray-700' }}">
                        {{ $statusLabels[$run->status] ?? $run->status }}
                    </span>
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Versi Formula</div>
                <div class="mt-1 text-lg font-bold text-sp-navy">v{{ $run->formula_version }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Total Unit Cost</div>
                <div class="mt-1 text-lg font-bold text-sp-navy">
                    @if($run->total_unit_cost !== null)
                        Rp {{ number_format((float) $run->total_unit_cost, 2, ',', '.') }}
                    @else
                        <span class="text-sm font-medium text-gray-400">Belum ditetapkan</span>
                    @endif
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Dihitung</div>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ $run->calculated_at?->format('d/m/Y H:i') ?? '-' }}</div>
                <div class="text-xs text-gray-500">{{ $run->creator?->name ?? '-' }}</div>
            </div>
        </div>

        @if($run->notes)
            <div class="px-4 pb-4">
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                    <span class="font-semibold text-gray-500">Catatan:</span> {{ $run->notes }}
                </div>
            </div>
        @endif

        @if($run->status === 'failed')
            <div class="px-4 pb-4">
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div class="font-semibold mb-1">Kalkulasi gagal</div>
                    <p>Periksa input dan formula, lalu jalankan ulang. Detail error tersimpan pada catatan run ini.</p>
                </div>
            </div>
        @endif
    </x-card>

    @if($inputDetails->isNotEmpty())
        <x-card padding="false">
            <x-slot name="title">Snapshot Input</x-slot>
            <x-slot name="subtitle">Nilai variabel yang dipakai pada run ini, termasuk nilai default</x-slot>

            <x-table :columns="['Kode', 'Nama', 'Nilai', 'Satuan', 'Sumber']" class="border-0 rounded-none shadow-none" empty="Tidak ada input.">
                @foreach($inputDetails as $detail)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ $detail->code }}</td>
                        <td class="px-4 py-2.5 text-sm text-gray-800">{{ $detail->name }}</td>
                        <td class="px-4 py-2.5 font-mono text-sm font-semibold text-gray-900">{{ $formatNumber($detail->result_value) }}</td>
                        <td class="px-4 py-2.5 text-xs text-gray-500">{{ $detail->result_unit ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-xs text-gray-600">{{ $detail->source_name ?? '-' }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    @foreach($formulaGroups as $componentName => $details)
        <x-card padding="false">
            <x-slot name="title">Hasil Formula: {{ $componentName }}</x-slot>
            <x-slot name="subtitle">{{ $details->count() }} formula</x-slot>

            <x-table :columns="['Kode', 'Nama', 'Ekspresi', 'Snapshot Input', 'Hasil', 'Satuan', 'Versi']" class="border-0 rounded-none shadow-none" empty="Tidak ada formula.">
                @foreach($details as $detail)
                    <tr class="hover:bg-gray-50 transition-colors align-top">
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-700 whitespace-nowrap">{{ $detail->code }}</td>
                        <td class="px-4 py-2.5 text-sm text-gray-800 min-w-[180px]">{{ $detail->name }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-600 min-w-[240px]">{{ $detail->expression ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-xs text-gray-600 min-w-[240px]">
                            @if(!empty($detail->input_snapshot))
                                <div class="space-y-0.5">
                                    @foreach($detail->input_snapshot as $code => $value)
                                        <div><span class="font-mono text-gray-500">{{ $code }}</span> = <span class="font-mono">{{ $formatNumber($value) }}</span></div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-mono text-sm font-bold text-sp-navy whitespace-nowrap">{{ $formatNumber($detail->result_value) }}</td>
                        <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap">{{ $detail->result_unit ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-xs text-gray-600 whitespace-nowrap">v{{ $detail->formula_version ?? '-' }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endforeach

    @if(!empty($snapshotInputs))
        <x-card>
            <x-slot name="title">Parameter Mentah Run</x-slot>
            <x-slot name="subtitle">Input asli sebelum validasi dan penggunaan nilai default</x-slot>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-sm">
                @foreach($snapshotInputs as $code => $value)
                    <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
                        <div class="font-mono text-xs text-gray-500">{{ $code }}</div>
                        <div class="font-mono text-gray-900">{{ is_numeric($value) ? $formatNumber($value) : $value }}</div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    @if($run->details->isEmpty())
        <x-card>
            <div class="py-8 text-center text-sm text-gray-500">
                Belum ada detail hasil. Run ini mungkin gagal sebelum formula dievaluasi.
            </div>
        </x-card>
    @endif
</div>
@endsection
