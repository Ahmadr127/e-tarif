<div class="mb-2 flex items-center justify-between gap-2">
    <div>
        <h3 class="text-sm font-semibold text-gray-800">Daftar Parameter</h3>
        <p class="text-xs text-gray-500">Kode parameter menjadi variabel global yang direferensikan di formula, misalnya {{ '{persentase_luas_bangunan}' }}.</p>
    </div>
    <span class="text-xs text-gray-500">{{ $parameters->count() }} parameter</span>
</div>

<div class="overflow-x-auto border border-gray-200 rounded-lg">
    <table class="w-full text-sm min-w-[64rem]">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 w-10">No.</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-40">Komponen</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-44">Kode Parameter</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-44">Parameter</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Tipe</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Scope</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Satuan</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-20">Wajib</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Status</th>
                <th class="px-3 py-2.5 text-right font-semibold text-gray-700 min-w-40">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($parameters as $parameter)
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-3 py-2.5 text-center text-gray-500">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm font-semibold text-gray-800">{{ $parameter->component?->name ?? 'Parameter Global' }}</div>
                        <div class="font-mono text-[11px] text-gray-500">{{ $parameter->component?->code ?? 'GLOBAL' }}</div>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="font-mono text-xs font-semibold text-sp-navy break-all">{{ '{'.$parameter->formulaKey().'}' }}</div>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="font-semibold text-gray-800">{{ $parameter->name }}</div>
                    </td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $valueTypeLabels[$parameter->value_type] ?? $parameter->value_type }}</td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $scopeLabels[$parameter->scope] ?? $parameter->scope }}</td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $parameter->unit ?: '-' }}</td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $parameter->is_required ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $parameter->is_required ? 'Wajib' : 'Opsional' }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $parameter->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $parameter->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="{{ route('calculations.master.parameters.edit', $parameter) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('calculations.master.parameters.destroy', $parameter) }}"
                                  onsubmit="return confirm('Hapus parameter {{ $parameter->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        @disabled($parameter->entries_count > 0 || $parameter->formula_versions_count > 0)
                                        title="{{ ($parameter->entries_count > 0 || $parameter->formula_versions_count > 0) ? 'Parameter sudah memiliki nilai atau riwayat formula.' : 'Hapus parameter' }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada parameter master.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
