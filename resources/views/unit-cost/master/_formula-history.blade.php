<x-table :columns="['No', 'Periode', 'Parameter', 'Variable Saat Ini', 'Versi', 'Ekspresi', 'Dibuat']"
         :pagination="$formulaVersions" empty="Belum ada versi formula."
         class="border-0 rounded-none shadow-none">
    @foreach($formulaVersions as $version)
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 text-center text-gray-500">{{ ($formulaVersions->firstItem() ?? 1) + $loop->index }}</td>
            <td class="px-4 py-3 text-sm text-gray-700">{{ $version->period?->name ?? '-' }}</td>
            <td class="px-4 py-3">
                <div class="text-sm font-semibold text-gray-800">{{ $version->parameter?->name ?? '-' }}</div>
                <div class="font-mono text-[11px] text-gray-500">{{ $version->parameter?->code }}</div>
            </td>
            <td class="px-4 py-3 font-mono text-xs text-sp-navy break-all">
                {{ $version->parameter ? '{'.$version->parameter->formulaKey().'}' : '-' }}
            </td>
            <td class="px-4 py-3 text-sm font-semibold text-gray-700">v{{ $version->version }}</td>
            <td class="px-4 py-3">
                <code class="text-xs text-gray-700 break-all">{{ $version->expression }}</code>
            </td>
            <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                {{ $version->created_at?->format('d/m/Y H:i') ?? '-' }}
            </td>
        </tr>
    @endforeach
</x-table>
