<x-table :columns="['No', 'Komponen', 'Terakhir Diubah', 'Aksi']"
         empty="Belum ada komponen yang di-assign ke unit Anda. Hubungi admin Unit Cost."
         class="border-0 rounded-none shadow-none">
    @php $rowNumber = 1; @endphp

    @foreach($components as $component)
        @php
            $summary = $summaries[(string) $component->id] ?? ['last_updated' => null];
        @endphp
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-center text-gray-500">{{ $rowNumber++ }}</td>
            <td class="px-4 py-3">
                <div class="text-sm font-semibold text-gray-900">{{ $component->name }}</div>
                <div class="text-xs font-mono text-gray-500">{{ $component->code }}</div>
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
