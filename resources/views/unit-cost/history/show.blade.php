@extends('layouts.app')

@section('title', 'Detail Snapshot Unit Cost')

@section('content')
@php
    $parameters = $payload['parameters'] ?? [];
    $summary = $payload['summary'] ?? [];
    $statusMeta = [
        'valid' => ['label' => 'Valid', 'class' => 'bg-green-100 text-green-800'],
        'incomplete' => ['label' => 'Belum lengkap', 'class' => 'bg-amber-100 text-amber-800'],
        'invalid' => ['label' => 'Error', 'class' => 'bg-red-100 text-red-800'],
    ];
@endphp

<div class="w-full mx-auto space-y-4">
    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('calculations.history.index') }}" class="font-semibold text-sp-primary hover:underline">Riwayat Hasil</a>
        <i class="bi bi-chevron-right text-xs"></i>
        <span class="font-semibold text-gray-700">Snapshot #{{ $snapshot->id }}</span>
    </div>

    <x-card padding="false">
        <x-slot name="title">Snapshot Resmi</x-slot>
        <x-slot name="subtitle">
            {{ $snapshot->period?->name }} · {{ $snapshot->unit?->name ?? 'Global' }}
        </x-slot>
        <x-slot name="actions">
            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disetujui</span>
            <a href="{{ route('calculations.history.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </x-slot>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 px-4 pt-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Disetujui</div>
                <div class="mt-1 text-sm font-semibold text-gray-800">{{ $snapshot->approved_at?->format('d/m/Y H:i') ?? '-' }}</div>
                <div class="text-xs text-gray-500">{{ $snapshot->approver?->name ?? '-' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-blue-50 px-4 py-3">
                <div class="text-xs font-semibold text-blue-600">Parameter</div>
                <div class="mt-1 text-2xl font-bold text-blue-800">{{ count($parameters) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-green-50 px-4 py-3">
                <div class="text-xs font-semibold text-green-700">Valid</div>
                <div class="mt-1 text-2xl font-bold text-green-800">{{ (int) ($summary['valid'] ?? 0) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold text-gray-500">Dibuat</div>
                <div class="mt-1 text-sm font-semibold text-gray-800">{{ $snapshot->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
        </div>

        <div class="p-4">
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full text-sm min-w-[64rem]">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 w-10">No.</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-48">Parameter</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-40">Kode</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Metode</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-64">Nilai / Rumus</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-20">Versi</th>
                            <th class="px-3 py-2.5 text-right font-semibold text-gray-700 min-w-32">Hasil</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($parameters as $parameter)
                            @php $badge = $statusMeta[$parameter['status'] ?? 'incomplete'] ?? $statusMeta['incomplete']; @endphp
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-3 py-3 text-center text-gray-500">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-gray-900">{{ $parameter['name'] ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $parameter['unit'] ?? '-' }}</div>
                                </td>
                                <td class="px-3 py-3 font-mono text-xs font-semibold text-sp-navy break-all">{{ $parameter['code'] ?? '-' }}</td>
                                <td class="px-3 py-3 text-xs text-gray-600">
                                    {{ ($parameter['input_type'] ?? 'value') === 'formula' ? 'Input Rumus' : 'Input Nilai' }}
                                </td>
                                <td class="px-3 py-3">
                                    @if(($parameter['input_type'] ?? 'value') === 'formula')
                                        <code class="text-xs text-gray-700 break-all">{{ $parameter['formula_text'] ?? '-' }}</code>
                                    @else
                                        <span class="font-mono text-sm text-gray-800">
                                            {{ $parameter['input_value'] !== null ? number_format((float) $parameter['input_value'], 6, ',', '.') : '-' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-600">
                                    {{ ! empty($parameter['formula_version']) ? 'v'.$parameter['formula_version'] : '-' }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-sm font-semibold text-gray-800">
                                    {{ $parameter['result_value'] !== null ? number_format((float) $parameter['result_value'], 6, ',', '.') : '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Snapshot tidak memiliki parameter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-card>
</div>
@endsection
