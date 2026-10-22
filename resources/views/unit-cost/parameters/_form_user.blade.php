@php
    $hasEditable = collect($rows)->contains(
        static fn (array $row): bool => $row['input_type'] === 'value'
            && $row['parameter']->requiresUnit()
    );

    $formulaRows = collect($rows)
        ->filter(static fn (array $row): bool => $row['input_type'] === 'formula')
        ->map(static function (array $row) use ($latestVersions): array {
            $parameter = $row['parameter'];

            return [
                'name' => $parameter->name,
                'code' => $parameter->formulaKey(),
                'formula' => (string) ($row['formula_text'] ?: $latestVersions->get($parameter->id)?->expression),
                'version' => $row['formula_version'] ?? null,
            ];
        })
        ->values();
@endphp

<form id="parameter-form" method="POST"
      action="{{ route('calculations.parameters.update', ['period' => $period, 'component' => $componentSlug]) }}"
      class="p-4 space-y-4">
    @csrf
    @method('PUT')
    <input type="hidden" name="unit_id" value="{{ $unit?->id ?? '0' }}">

    @if($rows === [])
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
            Belum ada parameter aktif pada komponen ini.
        </div>
    @else
        @if($formulaRows->isNotEmpty())
            <div class="flex items-center justify-end">
                <button type="button"
                        id="open-formula-preview"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded-md bg-white hover:bg-sp-primary/5 transition-colors"
                        aria-haspopup="dialog"
                        aria-controls="formula-preview-modal">
                    <i class="bi bi-eye"></i> Lihat Rumus ({{ $formulaRows->count() }})
                </button>
            </div>
        @endif

        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full text-sm min-w-[56rem]">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 w-10">No.</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-48">Nama Parameter</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-36">Kode Parameter</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Satuan</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-32">Metode Input</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-64">Nilai / Rumus</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-gray-700 min-w-32">Hasil</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        @php
                            $parameter = $row['parameter'];
                            $parameterId = $parameter->id;
                            $isValue = $row['input_type'] === 'value';
                            $isEditable = $isValue && $parameter->requiresUnit();
                            $inputValue = old("entries.{$parameterId}.input_value", $row['input_value']);
                            $status = $row['status'];
                            $statusBadge = $statusMeta[$status] ?? $statusMeta['incomplete'];
                        @endphp
                        @if(! $isValue)
                            {{-- Baris rumus: hanya nama parameter dan hasil; kolom lain sengaja kosong. --}}
                            <tr class="align-top parameter-row-readonly"
                                data-parameter-id="{{ $parameterId }}">
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-gray-900">{{ $parameter->name }}</div>
                                    <p class="row-error mt-1 text-xs text-red-600 {{ $row['error'] ? '' : 'hidden' }}">{{ $row['error'] }}</p>
                                </td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3 text-right">
                                    <div class="result-value font-mono text-sm font-semibold text-gray-800">
                                        @if($row['result_value'] !== null)
                                            {{ number_format((float) $row['result_value'], 6, ',', '.') }}
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3"></td>
                            </tr>
                        @else
                            <tr class="align-top {{ $isEditable ? 'parameter-row' : 'parameter-row-readonly' }}"
                                data-parameter-id="{{ $parameterId }}">
                                <td class="px-3 py-3 text-center text-gray-500">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-gray-900">{{ $parameter->name }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="font-mono text-xs font-semibold text-sp-navy break-all">{{ '{'.$parameter->formulaKey().'}' }}</span>
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-600">{{ $parameter->unit ?: '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Input Nilai
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    @if($isEditable)
                                        <input type="hidden" name="entries[{{ $parameterId }}][input_type]" value="value">
                                        <input type="number" step="any"
                                               name="entries[{{ $parameterId }}][input_value]"
                                               value="{{ $inputValue }}"
                                               placeholder="Masukkan nilai{{ $parameter->unit ? ' ('.$parameter->unit.')' : '' }}"
                                               @if($locked) disabled @endif
                                               class="value-input w-full text-sm px-2.5 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                                    @else
                                        <div class="rounded-md border border-gray-200 bg-gray-50 px-2.5 py-2 font-mono text-sm text-gray-700">
                                            {{ $inputValue !== null && $inputValue !== '' ? number_format((float) $inputValue, 6, ',', '.') : '-' }}
                                        </div>
                                        <div class="mt-1 text-[10px] text-gray-500">Nilai global dikelola admin.</div>
                                    @endif
                                    <p class="row-error mt-1 text-xs text-red-600 {{ $row['error'] ? '' : 'hidden' }}">{{ $row['error'] }}</p>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div class="result-value font-mono text-sm font-semibold text-gray-800">
                                        @if($row['result_value'] !== null)
                                            {{ number_format((float) $row['result_value'], 6, ',', '.') }}
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </div>
                                    @if($row['formula_version'])
                                        <div class="text-[10px] text-gray-400">v{{ $row['formula_version'] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="status-badge inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
            <div class="text-xs text-gray-500">
                Anda hanya dapat mengisi nilai. Metode input dan rumus dikelola admin.
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('calculations.periods.show', ['period' => $period, 'unit_id' => $unit?->id ?? '0']) }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    Batal
                </a>
                @unless($locked)
                    @if($hasEditable)
                        <button type="submit" id="save-parameters"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    @else
                        <span class="text-xs text-gray-500">Tidak ada parameter nilai yang dapat diisi.</span>
                    @endif
                @endunless
            </div>
        </div>
    @endif
</form>

@if($formulaRows->isNotEmpty())
    <div id="formula-preview-modal"
         class="fixed inset-0 z-[100] hidden"
         role="dialog"
         aria-modal="true"
         aria-labelledby="formula-preview-title"
         aria-hidden="true">
        <div class="absolute inset-0 bg-gray-900/50" data-formula-close></div>

        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-3">
                    <div class="min-w-0">
                        <h3 id="formula-preview-title" class="text-sm font-bold text-gray-900">Preview Rumus</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $formulaRows->count() }} parameter dihitung otomatis oleh sistem.
                        </p>
                    </div>
                    <button type="button"
                            data-formula-close
                            class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            aria-label="Tutup preview rumus">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="min-h-0 flex-1 divide-y divide-gray-100 overflow-y-auto">
                    @foreach($formulaRows as $formulaRow)
                        <div class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900">{{ $formulaRow['name'] }}</div>
                                    <div class="mt-0.5 font-mono text-[10px] text-gray-500">{{ '{'.$formulaRow['code'].'}' }}</div>
                                </div>
                                @if($formulaRow['version'])
                                    <span class="flex-shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">
                                        v{{ $formulaRow['version'] }}
                                    </span>
                                @endif
                            </div>

                            @if($formulaRow['formula'] !== '')
                                <pre class="mt-2 overflow-x-auto rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs leading-5 text-gray-800 whitespace-pre-wrap break-all">{{ $formulaRow['formula'] }}</pre>
                            @else
                                <p class="mt-2 rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                    Belum ada rumus untuk parameter ini.
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-gray-100 px-4 py-2 text-[10px] text-gray-500">
                    Rumus dikelola admin. Baris rumus pada tabel hanya menampilkan nama dan hasil.
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('formula-preview-modal');
        const openButton = document.getElementById('open-formula-preview');
        if (!modal || !openButton) return;

        let lastFocused = null;

        function open() {
            lastFocused = openButton;
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            modal.querySelector('button[data-formula-close]')?.focus();
        }

        function close() {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
            lastFocused?.focus();
        }

        openButton.addEventListener('click', open);

        modal.querySelectorAll('[data-formula-close]').forEach(function (button) {
            button.addEventListener('click', close);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                close();
            }
        });
    });
    </script>
@endif
