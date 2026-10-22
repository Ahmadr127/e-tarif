@extends('layouts.app')

@section('title', $parameter->exists ? 'Edit Parameter Unit Cost' : 'Tambah Parameter Unit Cost')

@section('content')
@php
    $isEdit = $parameter->exists;
    $action = $isEdit
        ? route('calculations.master.parameters.update', $parameter)
        : route('calculations.master.parameters.store');

    $valueTypes = [
        'decimal' => 'Desimal',
        'integer' => 'Bilangan Bulat',
        'boolean' => 'Boolean',
        'string' => 'Teks',
    ];
    $scopes = [
        'global' => 'Global',
        'unit' => 'Unit',
        'period' => 'Periode',
        'unit_period' => 'Unit + Periode',
    ];

    $returnPeriodId = $returnPeriodId ?? null;
    $returnUnitId = $returnUnitId ?? null;
    $cancelUrl = $returnPeriodId
        ? route('calculations.parameters.edit', [
            'period' => $returnPeriodId,
            'component' => $parameter->component_id ?? 'global',
            'unit_id' => $returnUnitId ?? '0',
        ])
        : route('calculations.master', ['section' => 'parameters']);

    $codePreview = old('code', $parameter->code) ?: 'kode_parameter';
@endphp

<div class="w-full max-w-4xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('calculations.master', ['section' => 'parameters']) }}" class="font-semibold text-sp-primary hover:underline">Master</a>
        <i class="bi bi-chevron-right text-xs"></i>
        <span class="font-semibold text-gray-700">{{ $isEdit ? 'Edit Parameter' : 'Tambah Parameter' }}</span>
    </div>

    <x-card padding="false">
        <x-slot name="title">{{ $isEdit ? 'Edit Parameter' : 'Tambah Parameter' }}</x-slot>
        <x-slot name="subtitle">Kode parameter menjadi variabel global yang dapat dipakai pada formula semua komponen.</x-slot>
        <x-slot name="actions">
            <a href="{{ $cancelUrl }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </x-slot>

        <form method="POST" action="{{ $action }}" class="p-4 space-y-4">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            @if($returnPeriodId)
                <input type="hidden" name="period_id" value="{{ $returnPeriodId }}">
                <input type="hidden" name="unit_id" value="{{ $returnUnitId ?? '0' }}">
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div class="font-semibold mb-1">Parameter belum dapat disimpan:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <x-searchable-dropdown
                        label-field="label"
                        name="component_id"
                        label="Komponen"
                        :options="$components->map(fn ($componentOption) => (object) [
                            'id' => (string) $componentOption->id,
                            'label' => $componentOption->name.' ('.$componentOption->code.')'.($componentOption->is_active ? '' : ' - nonaktif'),
                        ])->values()"
                        :selected="old('component_id', $parameter->component_id)"
                        placeholder="Global (tanpa komponen)"
                        empty-option="Global (tanpa komponen)"
                    />
                </div>

                <div class="md:col-span-2">
                    <label for="code" class="block text-sm font-semibold text-sp-navy mb-1">Kode Parameter <span class="text-red-500">*</span></label>
                    <input type="text" id="code" name="code" required maxlength="120"
                           value="{{ old('code', $parameter->code) }}"
                           placeholder="contoh: biaya_sdm_total"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white font-mono focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    <p class="mt-1 text-xs text-gray-500">
                        Kode ini juga menjadi variabel global. Formula memakai referensi
                        <code class="font-mono font-semibold text-sp-navy" id="code-preview">{{ '{'.$codePreview.'}' }}</code>.
                        Kode harus unik dan sebaiknya tidak diubah setelah dipakai.
                    </p>
                    @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-semibold text-sp-navy mb-1">Nama Parameter <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="255"
                           value="{{ old('name', $parameter->name) }}"
                           placeholder="contoh: Total Biaya SDM"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <x-searchable-dropdown
                        label-field="label"
                        name="value_type"
                        label="Tipe Nilai"
                        :options="collect($valueTypes)->map(fn ($text, $value) => (object) ['id' => $value, 'label' => $text])->values()"
                        :selected="old('value_type', $parameter->value_type)"
                        placeholder="Pilih Tipe Nilai..."
                        :required="true"
                    />
                </div>

                <div>
                    <label for="unit" class="block text-sm font-semibold text-sp-navy mb-1">Satuan</label>
                    <input type="text" id="unit" name="unit" maxlength="50"
                           value="{{ old('unit', $parameter->unit) }}"
                           placeholder="contoh: rupiah, pasien, m2, rasio"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    @error('unit')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <x-searchable-dropdown
                        label-field="label"
                        name="scope"
                        label="Scope"
                        :options="collect($scopes)->map(fn ($text, $value) => (object) ['id' => $value, 'label' => $text])->values()"
                        :selected="old('scope', $parameter->scope)"
                        placeholder="Pilih Scope..."
                        :required="true"
                    />
                </div>

                <div>
                    <x-searchable-dropdown
                        label-field="label"
                        name="default_input_type"
                        label="Metode Input Default"
                        :options="collect([
                            (object) ['id' => 'value', 'label' => 'Input Nilai'],
                            (object) ['id' => 'formula', 'label' => 'Input Rumus'],
                        ])->values()"
                        :selected="old('default_input_type', $parameter->default_input_type ?: 'value')"
                        placeholder="Input Nilai"
                    />
                </div>

                <div class="md:col-span-2">
                    <label for="default_value" class="block text-sm font-semibold text-sp-navy mb-1">Default Value / Rumus Default</label>
                    <textarea id="default_value" name="default_value" rows="3" spellcheck="false"
                              placeholder="Isi angka (mis. 365) atau ekspresi formula (mis. {luas_bangunan_tindakan} / {luas_bangunan_keseluruhan})"
                              class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white font-mono leading-5 focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">{{ old('default_value', $parameter->default_value) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        Dipakai otomatis saat parameter belum memiliki nilai tersimpan pada periode/unit.
                        Jika metode <span class="font-semibold">Input Rumus</span>, isi dengan ekspresi seperti
                        <code class="font-mono font-semibold text-sp-navy">{a} / {b}</code>.
                    </p>
                    @error('default_input_type')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    @error('default_value')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-end gap-6 pb-2">
                    <div>
                        <input type="hidden" name="is_required" value="0">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" name="is_required" value="1"
                                   @checked(old('is_required', $parameter->is_required ?? false))
                                   class="h-4 w-4 rounded border-gray-300 text-sp-primary focus:ring-sp-primary/30">
                            Wajib diisi
                        </label>
                    </div>
                    <div>
                        <input type="hidden" name="is_active" value="0">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" name="is_active" value="1"
                                   @checked(old('is_active', $parameter->is_active ?? true))
                                   class="h-4 w-4 rounded border-gray-300 text-sp-primary focus:ring-sp-primary/30">
                            Aktif
                        </label>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800">
                <div class="font-semibold">Kode parameter = variabel global</div>
                <p class="mt-1">Semua parameter aktif dapat dipakai di formula komponen mana pun melalui kode masing-masing. Gunakan kode yang stabil karena formula menyimpan referensi kode tersebut.</p>
                @if($isEdit && ($parameter->entries()->exists() || $parameter->formulaVersions()->exists()))
                    <p class="mt-2 font-semibold text-amber-800">Parameter ini sudah memiliki nilai atau riwayat formula. Mengubah kode dapat membuat formula lama menjadi invalid; riwayat formula tetap disimpan.</p>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                <a href="{{ $cancelUrl }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">Batal</a>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-save"></i> Simpan
                </button>
            </div>
        </form>
    </x-card>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const codeInput = document.getElementById('code');
    const preview = document.getElementById('code-preview');

    function updatePreview() {
        if (preview) {
            preview.textContent = '{' + (codeInput?.value.trim() || 'kode_parameter') + '}';
        }
    }

    codeInput?.addEventListener('input', updatePreview);
    updatePreview();
});
</script>
@endpush
