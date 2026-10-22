@php
    $isEdit = $period->exists;
    $action = $isEdit
        ? route('calculations.periods.update', $period)
        : route('calculations.periods.store');
@endphp

<x-card padding="false">
    <x-slot name="title">{{ $isEdit ? 'Edit Periode' : 'Tambah Periode' }}</x-slot>
    <x-slot name="subtitle">Periode menjadi wadah konfigurasi parameter unit cost</x-slot>
    <x-slot name="actions">
        <a href="{{ route('calculations.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </x-slot>

    <form method="POST" action="{{ $action }}" class="p-4 space-y-4">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <div class="font-semibold mb-1">Periode belum dapat disimpan:</div>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="code" class="block text-sm font-semibold text-sp-navy mb-1">Kode Periode <span class="text-red-500">*</span></label>
                <input type="text" id="code" name="code" required maxlength="80"
                       value="{{ old('code', $period->code) }}"
                       placeholder="contoh: UC-2026"
                       class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white font-mono focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="name" class="block text-sm font-semibold text-sp-navy mb-1">Nama Periode <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name" required maxlength="255"
                       value="{{ old('name', $period->name) }}"
                       placeholder="contoh: Tahun Anggaran 2026"
                       class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="period_year" class="block text-sm font-semibold text-sp-navy mb-1">Tahun <span class="text-red-500">*</span></label>
                <input type="number" id="period_year" name="period_year" required min="2000" max="2100"
                       value="{{ old('period_year', $period->period_year) }}"
                       class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                @error('period_year')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>

            <div>
                <x-searchable-dropdown
                    label-field="label"
                    name="status"
                    label="Status"
                    :options="collect([
                        (object) ['id' => 'draft', 'label' => 'Draf'],
                        (object) ['id' => 'active', 'label' => 'Aktif'],
                        (object) ['id' => 'closed', 'label' => 'Ditutup'],
                    ])"
                    :selected="old('status', $period->status)"
                    placeholder="Pilih Status..."
                />
            </div>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800">
            Kode periode harus unik. Kombinasi tahun dan nama periode juga tidak boleh duplikat.
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <a href="{{ route('calculations.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">Batal</a>
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-save"></i> Simpan
            </button>
        </div>
    </form>
</x-card>
