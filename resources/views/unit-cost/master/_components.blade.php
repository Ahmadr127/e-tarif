<div class="mb-2 flex items-center justify-between gap-2">
    <div>
        <h3 class="text-sm font-semibold text-gray-800">Daftar Komponen</h3>
        <p class="text-xs text-gray-500">Komponen mengelompokkan parameter unit cost. Komponen aktif tampil pada halaman periode.</p>
    </div>
    <span class="text-xs text-gray-500">{{ $components->count() }} komponen</span>
</div>

<div class="overflow-x-auto border border-gray-200 rounded-lg">
    <table class="w-full text-sm min-w-[48rem]">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 w-10">No.</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Kode</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-56">Nama Komponen</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-32">Kategori</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Parameter</th>
                <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Status</th>
                <th class="px-3 py-2.5 text-right font-semibold text-gray-700 min-w-40">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($components as $component)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2.5 text-center text-gray-500">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2.5 font-mono text-xs font-semibold text-sp-navy break-all">{{ $component->code }}</td>
                    <td class="px-3 py-2.5">
                        <div class="font-semibold text-gray-800">{{ $component->name }}</div>
                        @if($component->description)
                            <div class="text-xs text-gray-500">{{ $component->description }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $component->category ?: '-' }}</td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">{{ $component->parameters_count }}</td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $component->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $component->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <a href="{{ route('calculations.master.components.edit', $component) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('calculations.master.components.destroy', $component) }}"
                                  onsubmit="return confirm('Hapus komponen {{ $component->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        @disabled($component->parameters_count > 0)
                                        title="{{ $component->parameters_count > 0 ? 'Komponen masih memiliki parameter.' : 'Hapus komponen' }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-red-600 border border-red-200 rounded hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <i class="bi bi-trash"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada komponen master.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
