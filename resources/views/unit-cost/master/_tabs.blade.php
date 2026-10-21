@php
    $masterTabs = [
        'components' => ['label' => 'Komponen', 'icon' => 'bi-diagram-3', 'count' => $counts['components'] ?? 0],
        'parameters' => ['label' => 'Parameter', 'icon' => 'bi-list-ul', 'count' => $counts['parameters'] ?? 0],
        'formula-history' => ['label' => 'Riwayat Formula', 'icon' => 'bi-clock-history', 'count' => $counts['formula_history'] ?? 0],
    ];
@endphp

<div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1.5 shadow-sm">
    @foreach($masterTabs as $key => $tab)
        <a href="{{ route('calculations.master', ['section' => $key]) }}"
           class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition-colors {{ $section === $key ? 'bg-sp-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
            <i class="bi {{ $tab['icon'] }}"></i>
            {{ $tab['label'] }}
            <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $section === $key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600' }}">
                {{ $tab['count'] }}
            </span>
        </a>
    @endforeach
</div>
