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
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="w-full text-sm min-w-[64rem]">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 w-10">No.</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-48">Nama Parameter</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-40">Kode Parameter</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-24">Satuan</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-32">Metode Input</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-72">Nilai / Rumus</th>
                        <th class="px-3 py-2.5 text-right font-semibold text-gray-700 min-w-32">Hasil</th>
                        <th class="px-3 py-2.5 text-left font-semibold text-gray-700 min-w-28">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        @php
                            $parameter = $row['parameter'];
                            $entry = $row['entry'];
                            $parameterId = $parameter->id;
                            $inputType = old("entries.{$parameterId}.input_type", $row['input_type']);
                            $inputValue = old("entries.{$parameterId}.input_value", $row['input_value']);
                            $suggestedFormula = $latestVersions->get($parameterId)?->expression;
                            $formulaText = old("entries.{$parameterId}.formula_text", $row['formula_text'] ?: $suggestedFormula);
                            $status = $row['status'];
                            $statusBadge = $statusMeta[$status] ?? $statusMeta['incomplete'];
                        @endphp
                        <tr class="align-top parameter-row"
                            data-parameter-id="{{ $parameterId }}"
                            data-initial-type="{{ $row['input_type'] }}">
                            <td class="px-3 py-3 text-center text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-gray-900">{{ $parameter->name }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <span class="font-mono text-xs font-semibold text-sp-navy break-all">{{ '{'.$parameter->formulaKey().'}' }}</span>
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-600">{{ $parameter->unit ?: '-' }}</td>
                            <td class="px-3 py-3">
                                <x-searchable-dropdown
                                    label-field="label"
                                    name="entries[{{ $parameterId }}][input_type]"
                                    :options="collect([
                                        (object) ['id' => 'value', 'label' => 'Input Nilai'],
                                        (object) ['id' => 'formula', 'label' => 'Input Rumus'],
                                    ])"
                                    :selected="$inputType"
                                    placeholder="Input Nilai"
                                    class="method-select"
                                    data-previous="{{ $row['input_type'] }}"
                                />
                            </td>
                            <td class="px-3 py-3">
                                <div class="value-field {{ $inputType === 'formula' ? 'hidden' : '' }}">
                                    <input type="number" step="any"
                                           name="entries[{{ $parameterId }}][input_value]"
                                           value="{{ $inputValue }}"
                                           placeholder="Masukkan nilai{{ $parameter->unit ? ' ('.$parameter->unit.')' : '' }}"
                                           class="value-input w-full text-sm px-2.5 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                                </div>
                                <div class="formula-field {{ $inputType === 'formula' ? '' : 'hidden' }}">
                                    <textarea name="entries[{{ $parameterId }}][formula_text]" rows="3" spellcheck="false"
                                              placeholder="contoh: {luas_bangunan_tindakan} / {luas_bangunan_keseluruhan}"
                                              class="formula-input w-full text-xs px-2.5 py-2 border border-gray-300 rounded-md bg-white font-mono leading-5 focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">{{ $formulaText }}</textarea>
                                    <div class="mt-1 text-[10px] text-gray-500">
                                        Gunakan referensi {kode} parameter. Operator + - * /, kurung, dan fungsi abs, ceil, floor, sqrt, round, min, max.
                                    </div>
                                </div>
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
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
            <div class="text-xs text-gray-500">
                Preview dihitung otomatis oleh backend. Menyimpan tidak membuat tombol hitung terpisah.
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('calculations.periods.show', ['period' => $period, 'unit_id' => $unit?->id ?? '0']) }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    Batal
                </a>
                @unless($locked)
                    <button type="submit" id="save-parameters"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                @endunless
            </div>
        </div>
    @endif
</form>
