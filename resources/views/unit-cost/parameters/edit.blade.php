@extends('layouts.app')

@section('title', 'Parameter '.($componentModel?->name ?? 'Global'))

@section('content')
@php
    $statusMeta = [
        'valid' => ['label' => 'Valid', 'class' => 'bg-green-100 text-green-800'],
        'incomplete' => ['label' => 'Belum lengkap', 'class' => 'bg-amber-100 text-amber-800'],
        'invalid' => ['label' => 'Error', 'class' => 'bg-red-100 text-red-800'],
    ];
    $componentName = $componentModel?->name ?? 'Parameter Global';
    $componentSlug = $componentModel?->id ?? 'global';
    $locked = $period->isClosed();
@endphp

<div class="w-full mx-auto space-y-4">
    @include('unit-cost._tabs')

    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('calculations.index') }}" class="font-semibold text-sp-primary hover:underline">Periode</a>
        <i class="bi bi-chevron-right text-xs"></i>
        <a href="{{ route('calculations.periods.show', ['period' => $period, 'unit_id' => $unit?->id ?? '0']) }}" class="font-semibold text-sp-primary hover:underline">{{ $period->name }}</a>
        <i class="bi bi-chevron-right text-xs"></i>
        <span class="font-semibold text-gray-700">{{ $componentName }}</span>
        <i class="bi bi-chevron-right text-xs"></i>
        <span>Parameter</span>
    </div>

    <div class="space-y-4">
        <div>
            <x-card padding="false">
                <x-slot name="title">{{ $componentName }}</x-slot>
                <x-slot name="subtitle">
                    {{ $period->name }} · {{ $period->code }} · Tahun {{ $period->period_year }}
                    @if($unit) · Unit {{ $unit->name }} ({{ $unit->code }}) @else · Tanpa unit (global) @endif
                </x-slot>
                <x-slot name="actions">
                    <x-variable-help-modal :parameters="$allParameters" />

                    @if(auth()->user()?->hasPermission('manage_unit_cost_masters') || auth()->user()?->hasPermission('manage_calculations'))
                        <a href="{{ route('calculations.master.parameters.create', [
                                'component_id' => $componentModel?->id,
                                'period_id' => $period->id,
                                'unit_id' => $unit?->id ?? '0',
                            ]) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md bg-white hover:bg-sp-primary/5 transition-colors"
                           title="Tambah parameter master">
                            <i class="bi bi-plus-lg"></i>
                            <span class="hidden sm:inline">Tambah Parameter</span>
                        </a>
                    @endif

                    <span id="preview-indicator" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                        <i class="bi bi-circle-fill text-[6px]"></i> Preview otomatis
                    </span>
                    <a href="{{ route('calculations.periods.show', ['period' => $period, 'unit_id' => $unit?->id ?? '0']) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </x-slot>

                @if($locked)
                    <div class="m-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        Periode ini sudah ditutup. Nilai dan rumus tidak dapat diubah.
                    </div>
                @endif

                @if($errors->any())
                    <div class="m-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <div class="font-semibold mb-1">Parameter belum dapat disimpan:</div>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

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
                                                <select name="entries[{{ $parameterId }}][input_type]"
                                                        class="method-select w-full text-xs px-2 py-1.5 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary"
                                                        data-previous="{{ $row['input_type'] }}">
                                                    <option value="value" {{ $inputType === 'value' ? 'selected' : '' }}>Input Nilai</option>
                                                    <option value="formula" {{ $inputType === 'formula' ? 'selected' : '' }}>Input Rumus</option>
                                                </select>
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
            </x-card>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('parameter-form');
    const indicator = document.getElementById('preview-indicator');
    const previewUrl = @json(route('calculations.parameters.preview'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const periodId = @json($period->id);
    const unitId = @json($unit?->id);
    const component = @json((string) $componentSlug);
    const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 6 });
    let previewTimer = null;

    function rows() {
        return Array.from(document.querySelectorAll('.parameter-row'));
    }

    function payload() {
        const entries = {};

        rows().forEach(function (row) {
            const id = row.dataset.parameterId;
            const method = row.querySelector('.method-select')?.value
                || row.querySelector('input[name="entries[' + id + '][input_type]"]')?.value
                || 'value';
            const valueInput = row.querySelector('.value-input');
            const formulaInput = row.querySelector('.formula-input');

            entries[id] = {
                input_type: method,
                input_value: method === 'value' ? (valueInput?.value || null) : null,
                formula_text: method === 'formula' ? (formulaInput?.value || '') : null,
            };
        });

        return {
            period_id: periodId,
            unit_id: unitId,
            component: component,
            entries: entries,
        };
    }

    function setIndicator(text, classes) {
        if (!indicator) return;
        indicator.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ' + classes;
        indicator.innerHTML = '<i class="bi bi-circle-fill text-[6px]"></i> ' + text;
    }

    function statusMeta(status) {
        if (status === 'valid') return { label: 'Valid', classes: 'bg-green-100 text-green-800' };
        if (status === 'invalid') return { label: 'Error', classes: 'bg-red-100 text-red-800' };
        return { label: 'Belum lengkap', classes: 'bg-amber-100 text-amber-800' };
    }

    function applyPreview(data) {
        rows().forEach(function (row) {
            const id = row.dataset.parameterId;
            const result = data.rows?.[id];
            if (!result) return;

            const resultNode = row.querySelector('.result-value');
            if (resultNode) {
                resultNode.innerHTML = result.result_value === null || result.result_value === undefined
                    ? '<span class="text-gray-400">-</span>'
                    : numberFormat.format(result.result_value);
            }

            const badge = row.querySelector('.status-badge');
            if (badge) {
                const meta = statusMeta(result.status);
                badge.className = 'status-badge inline-flex px-2 py-0.5 text-xs font-semibold rounded-full ' + meta.classes;
                badge.textContent = meta.label;
            }

            const errorNode = row.querySelector('.row-error');
            if (errorNode) {
                errorNode.textContent = result.error || '';
                errorNode.classList.toggle('hidden', !result.error);
            }
        });

        if (data.valid) {
            setIndicator('Preview valid', 'bg-green-100 text-green-800');
        } else {
            setIndicator('Preview perlu dilengkapi', 'bg-amber-100 text-amber-800');
        }
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        setIndicator('Menghitung preview...', 'bg-blue-100 text-blue-800');
        previewTimer = setTimeout(async function () {
            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload()),
                });
                const data = await response.json().catch(function () { return {}; });

                if (!response.ok) {
                    setIndicator('Preview gagal', 'bg-red-100 text-red-800');
                    return;
                }

                applyPreview(data);
            } catch (error) {
                setIndicator('Preview gagal', 'bg-red-100 text-red-800');
            }
        }, 450);
    }

    function toggleRow(row, method) {
        const valueField = row.querySelector('.value-field');
        const formulaField = row.querySelector('.formula-field');
        if (!valueField || !formulaField) return;

        valueField.classList.toggle('hidden', method !== 'value');
        formulaField.classList.toggle('hidden', method !== 'formula');
    }

    rows().forEach(function (row) {
        const methodSelect = row.querySelector('.method-select');
        const valueInput = row.querySelector('.value-input');
        const formulaInput = row.querySelector('.formula-input');

        methodSelect?.addEventListener('change', function () {
            const previous = methodSelect.dataset.previous || 'value';
            const next = methodSelect.value;

            if (previous !== next) {
                const hasOldValue = previous === 'value' && valueInput?.value.trim() !== '';
                const hasOldFormula = previous === 'formula' && formulaInput?.value.trim() !== '';

                if ((hasOldValue || hasOldFormula)
                    && !confirm('Mengubah metode input akan mengosongkan ' + (previous === 'value' ? 'nilai' : 'rumus') + ' lama. Lanjutkan?')) {
                    methodSelect.value = previous;
                    return;
                }

                if (previous === 'value' && valueInput) valueInput.value = '';
                if (previous === 'formula' && formulaInput) formulaInput.value = '';
                methodSelect.dataset.previous = next;
            }

            toggleRow(row, next);
            schedulePreview();
        });

        valueInput?.addEventListener('input', schedulePreview);
        formulaInput?.addEventListener('input', schedulePreview);
        formulaInput?.addEventListener('keydown', function (event) {
            if (event.key === 'Tab') {
                event.preventDefault();
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.slice(0, start) + '    ' + this.value.slice(end);
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
    });

    document.querySelectorAll('.copy-variable').forEach(function (button) {
        button.addEventListener('click', async function () {
            const text = button.dataset.copyVariable;

            try {
                await navigator.clipboard.writeText(text);
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Tersalin';
                setTimeout(function () { button.innerHTML = original; }, 1200);
            } catch (error) {
                window.prompt('Salin referensi variabel:', text);
            }
        });
    });

    document.getElementById('variable-search')?.addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('.variable-item').forEach(function (item) {
            item.classList.toggle('hidden', term !== '' && !item.dataset.search.includes(term));
        });
    });

    form?.addEventListener('submit', function () {
        const button = document.getElementById('save-parameters');
        if (button) {
            button.disabled = true;
            button.classList.add('opacity-60');
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
        }
    });
});
</script>
@endpush
