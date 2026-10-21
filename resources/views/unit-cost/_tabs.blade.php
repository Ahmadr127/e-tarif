@php
    $tabs = [
        ['label' => 'Periode', 'route' => 'calculations.index', 'active' => request()->routeIs('calculations.index') || request()->routeIs('calculations.periods.*') || request()->routeIs('calculations.parameters.*')],
        ['label' => 'Master', 'route' => 'calculations.master', 'active' => request()->routeIs('calculations.master')],
        ['label' => 'Riwayat Hasil', 'route' => 'calculations.history.index', 'active' => request()->routeIs('calculations.history.*')],
    ];
@endphp

<div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1.5 shadow-sm">
    @foreach($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
           class="inline-flex items-center px-3 py-1.5 text-sm font-semibold rounded-md transition-colors {{ $tab['active'] ? 'bg-sp-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
