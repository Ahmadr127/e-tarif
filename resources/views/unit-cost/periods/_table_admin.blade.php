@php
    $summaryStatus = $summaryStatus ?? [
        'valid' => ['label' => 'Lengkap', 'class' => 'bg-green-100 text-green-800'],
        'incomplete' => ['label' => 'Belum lengkap', 'class' => 'bg-amber-100 text-amber-800'],
        'invalid' => ['label' => 'Ada error', 'class' => 'bg-red-100 text-red-800'],
    ];
@endphp

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
