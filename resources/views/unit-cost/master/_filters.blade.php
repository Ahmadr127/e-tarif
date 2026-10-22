@php
    $filterComponents = $filterComponents ?? collect();
    $categories = $categories ?? collect();
    $periodOptions = $periodOptions ?? collect();

    $searchPlaceholder = match ($section) {
        'components' => 'Cari kode, nama, atau deskripsi komponen...',
        'parameters' => 'Cari kode atau nama parameter...',
        default => 'Cari nama/kode parameter atau ekspresi...',
    };

    $selectClass = 'w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors';
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
                       class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
            </div>
        </div>

        @if($section === 'components')
            <div class="lg:w-48">
                <label for="filter-category" class="block text-xs font-semibold text-gray-600 mb-1">Kategori</label>
                <select id="filter-category" name="category" class="{{ $selectClass }}">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
        @elseif($section === 'parameters')
            <div class="lg:w-52">
                <label for="filter-component" class="block text-xs font-semibold text-gray-600 mb-1">Komponen</label>
                <select id="filter-component" name="component" class="{{ $selectClass }}">
                    <option value="">Semua Komponen</option>
                    <option value="global" @selected($filters['component'] === 'global')>Tanpa Komponen (Global)</option>
                    @foreach($filterComponents as $component)
                        <option value="{{ $component->id }}" @selected($filters['component'] === (string) $component->id)>
                            {{ $component->code }} · {{ $component->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="lg:w-40">
                <label for="filter-scope" class="block text-xs font-semibold text-gray-600 mb-1">Scope</label>
                <select id="filter-scope" name="scope" class="{{ $selectClass }}">
                    <option value="">Semua Scope</option>
                    @foreach($scopeLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['scope'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div class="lg:w-56">
                <label for="filter-period" class="block text-xs font-semibold text-gray-600 mb-1">Periode</label>
                <select id="filter-period" name="period" class="{{ $selectClass }}">
                    <option value="">Semua Periode</option>
                    @foreach($periodOptions as $period)
                        <option value="{{ $period->id }}" @selected($filters['period'] === (string) $period->id)>
                            {{ $period->name }} ({{ $period->period_year }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="lg:w-40">
                <label for="filter-date-from" class="block text-xs font-semibold text-gray-600 mb-1">Dari Tanggal</label>
                <input type="date" id="filter-date-from" name="date_from" value="{{ $filters['date_from'] }}"
                       class="{{ $selectClass }}">
            </div>
            <div class="lg:w-40">
                <label for="filter-date-to" class="block text-xs font-semibold text-gray-600 mb-1">Sampai Tanggal</label>
                <input type="date" id="filter-date-to" name="date_to" value="{{ $filters['date_to'] }}"
                       class="{{ $selectClass }}">
            </div>
        @endif

        @if($section !== 'formula-history')
            <div class="lg:w-36">
                <label for="filter-status" class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                <select id="filter-status" name="status" class="{{ $selectClass }}">
                    <option value="">Semua Status</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                </select>
            </div>
        @endif

        <div class="flex items-center gap-2">
            <a href="{{ route('calculations.master', ['section' => $section]) }}"
               class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                <i class="bi bi-x-lg mr-1.5 text-xs"></i> Reset
            </a>
            <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-funnel mr-1.5 text-xs"></i> Filter
            </button>
        </div>
    </div>
</form>
