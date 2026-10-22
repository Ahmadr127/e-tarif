@php
    $filterComponents = $filterComponents ?? collect();
    $categories = $categories ?? collect();
    $periodOptions = $periodOptions ?? collect();

    $searchPlaceholder = match ($section) {
        'components' => 'Cari kode, nama, atau deskripsi komponen...',
        'parameters' => 'Cari kode atau nama parameter...',
        default => 'Cari nama/kode parameter atau ekspresi...',
    };

    $inputClass = 'w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors';
@endphp

<form method="GET" action="{{ route('calculations.master') }}"
      class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
    <input type="hidden" name="section" value="{{ $section }}">
    @if(request('per_page'))
        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
    @endif

    <div class="flex flex-col lg:flex-row lg:items-end gap-3">
        <div class="flex-1 min-w-0">
            <label for="filter-search" class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="text"
                       id="filter-search"
                       name="search"
                       value="{{ $filters['search'] }}"
                       placeholder="{{ $searchPlaceholder }}"
                       class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-md bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
            </div>
        </div>

        @if($section === 'components')
            <div class="lg:w-48">
                <x-searchable-dropdown
                    label-field="label"
                    name="category"
                    label="Kategori"
                    :options="$categories->map(fn ($category) => (object) ['id' => $category, 'label' => $category])->values()"
                    :selected="$filters['category'] !== '' ? $filters['category'] : null"
                    placeholder="Semua Kategori"
                    empty-option="Semua Kategori"
                />
            </div>
        @elseif($section === 'parameters')
            <div class="lg:w-56">
                <x-searchable-dropdown
                    label-field="label"
                    name="component"
                    label="Komponen"
                    :options="collect([(object) ['id' => 'global', 'label' => 'Tanpa Komponen (Global)']])->concat(
                        $filterComponents->map(fn ($component) => (object) [
                            'id' => (string) $component->id,
                            'label' => $component->code.' · '.$component->name,
                        ])
                    )"
                    :selected="$filters['component'] !== '' ? $filters['component'] : null"
                    placeholder="Semua Komponen"
                    empty-option="Semua Komponen"
                />
            </div>
            <div class="lg:w-40">
                <x-searchable-dropdown
                    label-field="label"
                    name="scope"
                    label="Scope"
                    :options="collect($scopeLabels)->map(fn ($label, $value) => (object) ['id' => $value, 'label' => $label])->values()"
                    :selected="$filters['scope'] !== '' ? $filters['scope'] : null"
                    placeholder="Semua Scope"
                    empty-option="Semua Scope"
                />
            </div>
        @else
            <div class="lg:w-60">
                <x-searchable-dropdown
                    label-field="label"
                    name="period"
                    label="Periode"
                    :options="$periodOptions->map(fn ($period) => (object) [
                        'id' => (string) $period->id,
                        'label' => $period->name.' ('.$period->period_year.')',
                    ])->values()"
                    :selected="$filters['period'] !== '' ? $filters['period'] : null"
                    placeholder="Semua Periode"
                    empty-option="Semua Periode"
                />
            </div>
            <div class="lg:w-40">
                <label for="filter-date-from" class="block text-xs font-semibold text-gray-600 mb-1">Dari Tanggal</label>
                <input type="date" id="filter-date-from" name="date_from" value="{{ $filters['date_from'] }}"
                       class="{{ $inputClass }}">
            </div>
            <div class="lg:w-40">
                <label for="filter-date-to" class="block text-xs font-semibold text-gray-600 mb-1">Sampai Tanggal</label>
                <input type="date" id="filter-date-to" name="date_to" value="{{ $filters['date_to'] }}"
                       class="{{ $inputClass }}">
            </div>
        @endif

        @if($section !== 'formula-history')
            <div class="lg:w-40">
                <x-searchable-dropdown
                    label-field="label"
                    name="status"
                    label="Status"
                    :options="collect([
                        (object) ['id' => 'active', 'label' => 'Aktif'],
                        (object) ['id' => 'inactive', 'label' => 'Nonaktif'],
                    ])"
                    :selected="$filters['status'] !== '' ? $filters['status'] : null"
                    placeholder="Semua Status"
                    empty-option="Semua Status"
                />
            </div>
        @endif

        <div class="flex items-center gap-2">
            <a href="{{ route('calculations.master', ['section' => $section]) }}"
               class="inline-flex items-center px-3 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-x-lg mr-1.5 text-xs"></i> Reset
            </a>
            <button type="submit"
                    class="inline-flex items-center px-3 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-funnel mr-1.5 text-xs"></i> Filter
            </button>
        </div>
    </div>
</form>
