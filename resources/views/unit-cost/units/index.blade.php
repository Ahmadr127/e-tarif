@extends('layouts.app')

@section('title', 'Unit Cost')

@section('content')
<div class="w-full mx-auto space-y-4"
     x-data="unitComponentAssign(@js([
         'actionTemplate' => route('calculations.units.components.update', ['unit' => '__UNIT__']),
     ]))"
     @keydown.escape.window="close()">

    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
        <span class="font-semibold text-gray-700">Unit Cost</span>
        <i class="bi bi-chevron-right text-xs"></i>
        <span>Daftar Unit</span>
    </div>

    <x-card padding="false">
        <x-slot name="title">Unit Cost per Unit</x-slot>
        <x-slot name="subtitle">Pilih unit untuk membuka komponen periode berjalan, atau atur komponen yang dipakai tiap unit.</x-slot>
        <x-slot name="actions">
            <a href="{{ route('calculations.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-navy border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-calendar3"></i> Kelola Periode
            </a>
        </x-slot>

        <div class="px-4 py-3 border-b border-gray-100 bg-white">
            <form method="GET" action="{{ route('calculations.units.index') }}" class="flex flex-col lg:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label for="search" class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Cari kode atau nama unit..."
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-full bg-gray-50 placeholder-gray-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                    </div>
                </div>
                <div class="lg:w-80">
                    @if($periods->isNotEmpty())
                        <x-searchable-dropdown
                            label-field="label"
                            name="period_id"
                            label="Periode"
                            :options="$periods->map(fn ($periodOption) => (object) [
                                'id' => (string) $periodOption->id,
                                'label' => $periodOption->name
                                    .(str_contains($periodOption->name, (string) $periodOption->period_year) ? '' : ' ('.$periodOption->period_year.')'),
                            ])"
                            :selected="$selectedPeriod ? (string) $selectedPeriod->id : null"
                            placeholder="Pilih Periode"
                            onchange="this.form.submit()"
                        />
                    @else
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Periode</label>
                        <div class="py-1.5 text-sm text-gray-500">
                            Belum ada periode.
                            <a href="{{ route('calculations.periods.create') }}" class="font-semibold text-sp-primary hover:underline">Buat periode</a>.
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('calculations.units.index') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        <i class="bi bi-x-lg mr-1.5 text-xs"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-funnel mr-1.5 text-xs"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <x-table :columns="['No', 'Unit', 'Komponen', 'Periode', 'Aksi']"
                 :pagination="$units" :perPage="$perPage"
                 :perPageOptions="[10, 25, 50, 100]"
                 empty="Tidak ada unit aktif."
                 class="border-0 rounded-none shadow-none">
            @foreach($units as $unit)
                @php
                    $assigned = $unit->unitCostComponents;
                    $assignedIds = $assigned->pluck('id')->map(fn ($id) => (string) $id)->values();
                    $previewNames = $assigned->take(3)->pluck('name');
                    $remaining = $assigned->count() - $previewNames->count();
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-center text-gray-500">{{ ($units->firstItem() ?? 1) + $loop->index }}</td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900">{{ $unit->name }}</div>
                        <div class="mt-0.5 text-xs text-gray-500">
                            <span class="font-mono">{{ $unit->code }}</span>
                            @if($unit->type)
                                <span class="mx-1">·</span>{{ $unit->type->display_name }}
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-1">
                            <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full {{ $assigned->isEmpty() ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                                {{ $assigned->count() }} komponen
                            </span>
                            @foreach($previewNames as $name)
                                <span class="inline-flex px-2 py-0.5 text-[11px] rounded-full bg-gray-100 text-gray-700">{{ $name }}</span>
                            @endforeach
                            @if($remaining > 0)
                                <span class="text-[11px] text-gray-500">+{{ $remaining }} lainnya</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                        {{ $selectedPeriod?->name ?? '-' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                            @if($selectedPeriod)
                                <a href="{{ route('calculations.periods.show', ['period' => $selectedPeriod, 'unit_id' => $unit->id]) }}"
                                   title="Buka komponen periode terpilih"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-sp-primary border border-sp-primary/30 rounded hover:bg-sp-primary/5">
                                    <i class="bi bi-folder2-open"></i> Buka
                                </a>
                            @else
                                <span title="Belum ada periode"
                                      class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-gray-400 border border-gray-200 rounded bg-gray-50 cursor-not-allowed">
                                    <i class="bi bi-folder2-open"></i> Buka
                                </span>
                            @endif
                            <button type="button"
                                    @click="show(@js([
                                        'id' => $unit->id,
                                        'name' => $unit->name,
                                        'assigned' => $assignedIds,
                                    ]))"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-sp-navy border border-gray-300 rounded bg-white hover:bg-gray-50 transition-colors">
                                <i class="bi bi-sliders"></i> Assign Komponen
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    {{-- Modal assign komponen --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="close()"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-sp-navy">Assign Komponen</h3>
                    <p class="text-xs text-gray-500 mt-0.5 truncate" x-text="unitName"></p>
                </div>
                <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form :action="action" method="POST" class="flex-1 flex flex-col min-h-0">
                @csrf
                @method('PUT')

                <div class="px-5 py-3 border-b border-gray-100">
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                        <input type="search" x-model="search" placeholder="Cari komponen..."
                               class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-3 py-2">
                    @forelse($components as $component)
                        <label x-show="matches(@js($component->code.' '.$component->name))"
                               class="flex items-start gap-2 rounded-md px-2 py-1.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox"
                                   name="component_ids[]"
                                   value="{{ $component->id }}"
                                   x-model="selected"
                                   class="mt-0.5 rounded border-gray-300 text-sp-primary focus:ring-sp-primary/30">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-800">{{ $component->name }}</span>
                                <span class="block text-[11px] font-mono text-gray-500">{{ $component->code }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500 px-2 py-4">
                            Belum ada komponen master.
                            <a href="{{ route('calculations.master') }}" class="font-semibold text-sp-primary hover:underline">Tambahkan komponen</a>.
                        </p>
                    @endforelse
                </div>

                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between gap-3">
                    <span class="text-xs text-gray-500"><span x-text="selected.length"></span> komponen dipilih</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="close()"
                                class="px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                            Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function unitComponentAssign(config) {
    return {
        open: false,
        unitName: '',
        action: '',
        search: '',
        selected: [],

        show(unit) {
            this.unitName = unit.name;
            this.action = config.actionTemplate.replace('__UNIT__', unit.id);
            this.selected = (unit.assigned || []).map(String);
            this.search = '';
            this.open = true;
        },

        close() {
            this.open = false;
        },

        matches(text) {
            return this.search === ''
                || String(text).toLowerCase().includes(this.search.toLowerCase());
        },
    };
}
</script>
@endpush
