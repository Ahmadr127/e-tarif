@props([
    'pagination' => null,
    'perPage' => 25,
    'perPageOptions' => [5, 10, 25, 50, 100],
])

@if($pagination && $pagination->total() > 0)
    <div class="mt-3 flex flex-col sm:flex-row items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <label for="per-page-{{ $section ?? 'master' }}" class="text-xs text-gray-500">Tampilkan</label>
            <select
                id="per-page-{{ $section ?? 'master' }}"
                onchange="const p = new URLSearchParams(window.location.search); p.set('per_page', this.value); p.delete('page'); window.location = window.location.pathname + '?' + p.toString();"
                class="px-2 py-1 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary"
            >
                @foreach($perPageOptions as $option)
                    <option value="{{ $option }}" {{ (int) $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                @endforeach
            </select>
            <span class="text-xs text-gray-500">per halaman</span>
        </div>
        <p class="text-xs text-gray-500">
            Menampilkan {{ $pagination->firstItem() ?? 0 }}–{{ $pagination->lastItem() ?? 0 }} dari {{ $pagination->total() }} data
        </p>
        <div class="text-sm">{{ $pagination->links() }}</div>
    </div>
@endif
