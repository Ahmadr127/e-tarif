@extends('layouts.app')

@section('title', 'Parameter '.($componentModel?->name ?? 'Global'))

@section('content')
@php
    $isAdmin = $isAdmin ?? false;
    $isScopedUser = $isScopedUser ?? false;
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
    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        @if($isScopedUser)
            <a href="{{ route('calculations.my-unit') }}" class="font-semibold text-sp-primary hover:underline">Unit Cost Saya</a>
        @else
            <a href="{{ route('calculations.index') }}" class="font-semibold text-sp-primary hover:underline">Periode</a>
        @endif
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
                    @unless($isScopedUser)
                        <x-variable-help-modal :parameters="$allParameters" />
                    @endunless

                    @if(auth()->user()?->hasPermission('manage_unit_cost') || auth()->user()?->hasPermission('manage_unit_cost_parameters') || auth()->user()?->hasPermission('manage_unit_cost_masters') || auth()->user()?->hasPermission('manage_calculations'))
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
                        @if($isScopedUser)
                            Periode ini sudah ditutup. Nilai tidak dapat diubah.
                        @else
                            Periode ini sudah ditutup. Nilai dan rumus tidak dapat diubah.
                        @endif
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

                @if($isScopedUser)
                    @include('unit-cost.parameters._form_user')
                @else
                    @include('unit-cost.parameters._form_admin')
                @endif
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

    function allRows() {
        return Array.from(document.querySelectorAll('.parameter-row, .parameter-row-readonly'));
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
        allRows().forEach(function (row) {
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
