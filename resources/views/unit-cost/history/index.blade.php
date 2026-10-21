@extends('layouts.app')

@section('title', 'Riwayat Hasil Unit Cost')

@section('content')
<div class="w-full mx-auto space-y-4">
    @include('unit-cost._tabs')

    <x-card padding="false">
        <x-slot name="title">Riwayat Hasil Disetujui</x-slot>
        <x-slot name="subtitle">Snapshot resmi menyimpan nilai, rumus, versi formula, dan hasil pada saat persetujuan.</x-slot>

        <x-table :columns="['No', 'Periode', 'Unit', 'Disetujui', 'Oleh', 'Status', 'Ringkasan', 'Aksi']"
                 :pagination="$snapshots" empty="Belum ada hasil yang disetujui."
                 class="border-0 rounded-none shadow-none">
            @foreach($snapshots as $snapshot)
                @php $payload = $snapshot->payload ?? []; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-center text-gray-500">{{ ($snapshots->firstItem() ?? 1) + $loop->index }}</td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900">{{ $snapshot->period?->name ?? '-' }}</div>
                        <div class="text-xs text-gray-500">{{ $snapshot->period?->code }} · {{ $snapshot->period?->period_year }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        {{ $snapshot->unit?->name ?? 'Global' }}
                        @if($snapshot->unit)
                            <div class="text-xs text-gray-500">{{ $snapshot->unit->code }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                        {{ $snapshot->approved_at?->format('d/m/Y H:i') ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $snapshot->approver?->name ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                            Disetujui
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600">
                        @php $summary = $payload['summary'] ?? []; @endphp
                        {{ (int) ($summary['valid'] ?? 0) }} valid ·
                        {{ (int) ($summary['incomplete'] ?? 0) }} belum lengkap ·
                        {{ (int) ($summary['invalid'] ?? 0) }} error
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('calculations.history.show', $snapshot) }}"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                            <i class="bi bi-eye"></i> Lihat
                        </a>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
@endsection
