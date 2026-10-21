@extends('layouts.app')

@section('title', 'Jalankan Kalkulasi Unit Cost')

@section('content')
<div class="w-full mx-auto space-y-4">
    <x-card padding="false">
        <x-slot name="title">Jalankan Kalkulasi Unit Cost</x-slot>
        <x-slot name="subtitle">Pilih unit dan periode, lalu isi variabel yang dibutuhkan formula aktif</x-slot>
        <x-slot name="actions">
            <a href="{{ route('calculations.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-arrow-left"></i> Riwayat
            </a>
        </x-slot>

        <form method="POST" action="{{ route('calculations.store') }}" class="p-4 space-y-5">
            @csrf

            @if(session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <div class="font-semibold mb-1">Periksa kembali input berikut:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label for="unit_code" class="block text-sm font-semibold text-sp-navy mb-1">Unit Organisasi <span class="text-red-500">*</span></label>
                    <select id="unit_code" name="unit_code" required
                            class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                        <option value="">Pilih unit organisasi</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->code }}" {{ old('unit_code', request('unit_code')) === $unit->code ? 'selected' : '' }}>
                                {{ $unit->name }} ({{ $unit->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('unit_code')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="period_year" class="block text-sm font-semibold text-sp-navy mb-1">Tahun Periode <span class="text-red-500">*</span></label>
                    <input type="number" id="period_year" name="period_year" min="2000" max="2100" required
                           value="{{ old('period_year', $periodYear) }}"
                           class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    @error('period_year')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                <div class="flex items-start gap-2">
                    <i class="bi bi-info-circle mt-0.5"></i>
                    <div>
                        <span class="font-semibold">{{ $activeFormulaCount }} formula aktif</span>
                        untuk tahun {{ $periodYear }}. Variabel bertanda <span class="font-semibold">*</span>
                        direferensikan oleh formula aktif dan wajib memiliki nilai. Nilai default
                        akan dipakai bila input dikosongkan.
                    </div>
                </div>
            </div>

            @if($groups === [])
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Belum ada variabel aktif yang dapat diisi. Jalankan seeder calculation terlebih dahulu.
                </div>
            @endif

            @foreach($groups as $group)
                <section class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-bold text-sp-navy">{{ $group['name'] }}</h3>
                    </div>

                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($group['variables'] as $variable)
                            @php
                                $isRequired = in_array($variable->code, $requiredCodes, true);
                                $errorKey = 'inputs.'.$variable->code;
                                $oldValue = old($errorKey);
                                $defaultLabel = $variable->default_value !== null
                                    ? rtrim(rtrim(number_format((float) $variable->default_value, 6, ',', '.'), '0'), ',')
                                    : null;
                            @endphp

                            <div class="rounded-md border border-gray-200 bg-white p-3">
                                <label for="input-{{ $variable->code }}" class="block text-sm font-semibold text-gray-800">
                                    {{ $variable->name }}
                                    @if($isRequired)<span class="text-red-500">*</span>@endif
                                </label>

                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-gray-500">
                                    <span class="font-mono">{{ $variable->code }}</span>
                                    @if($variable->unit)
                                        <span class="inline-flex px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{{ $variable->unit }}</span>
                                    @endif
                                    @if($variable->source_name)
                                        <span class="inline-flex px-1.5 py-0.5 rounded bg-amber-50 text-amber-700">{{ $variable->source_name }}</span>
                                    @endif
                                </div>

                                <input type="number" step="any"
                                       id="input-{{ $variable->code }}"
                                       name="inputs[{{ $variable->code }}]"
                                       value="{{ $oldValue }}"
                                       @if($variable->isRatio()) min="0" max="1" @endif
                                       placeholder="{{ $defaultLabel !== null ? 'Default: '.$defaultLabel : 'Masukkan nilai' }}"
                                       class="mt-2 w-full text-sm px-3 py-2 border rounded-md outline-none transition-colors bg-white placeholder-gray-400 {{ $errors->has($errorKey) ? 'border-red-400 focus:ring-2 focus:ring-red-100' : 'border-gray-300 focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary' }}">

                                @if($variable->description)
                                    <p class="mt-1.5 text-xs text-gray-500">{{ $variable->description }}</p>
                                @endif

                                @if($variable->isRatio())
                                    <p class="mt-1 text-[11px] text-blue-600">Rasio 0 sampai 1, bukan persen (contoh: 0,25).</p>
                                @endif

                                @error($errorKey)
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div>
                <label for="notes" class="block text-sm font-semibold text-sp-navy mb-1">Catatan Run</label>
                <textarea id="notes" name="notes" rows="2"
                          placeholder="Opsional, misalnya sumber data atau dasar periode..."
                          class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4">
                <a href="{{ route('calculations.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                    <i class="bi bi-calculator"></i> Hitung dan Simpan
                </button>
            </div>
        </form>
    </x-card>
</div>
@endsection
