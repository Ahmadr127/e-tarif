{{--
    Picker Tarif (AJAX)

    Dipakai form pemetaan tarif karena tabel tarifs bisa berisi belasan ribu
    baris — opsi tidak dikirim sekaligus, tapi dicari ke endpoint
    tarif-units.search-tarifs (min. 2 huruf).

    Usage:
    <x-tarif-picker name="tarif_id" :selected="$selectedTarif" />
--}}
@props([
    'name' => 'tarif_id',
    'label' => 'Tarif',
    'selected' => null, // App\Models\Tarif|null
    'required' => true,
    'searchUrl' => null,
])

@php
    $inputId = 'tarif-picker-'.Str::random(8);
    $selectedId = old($name, $selected?->id);
    $selectedLabel = $selected?->display_label ?? '';
    // Endpoint pencarian bisa berbeda antar area (admin vs unit sendiri).
    $searchUrl = $searchUrl ?: route('tarif-units.search-tarifs');
@endphp

<div data-tp-root data-tp-url="{{ $searchUrl }}" class="relative">
    @if($label)
        <label for="{{ $inputId }}" class="block text-sm font-semibold text-sp-navy mb-1">
            {{ $label }}@if($required)<span class="text-red-500"> *</span>@endif
        </label>
    @endif

    {{-- Nilai yang dikirim ke server --}}
    <input type="hidden" name="{{ $name }}" data-tp-value value="{{ $selectedId }}">

    <input
        id="{{ $inputId }}"
        type="text"
        autocomplete="off"
        spellcheck="false"
        data-tp-input
        data-tp-initial="{{ $selectedLabel }}"
        placeholder="Ketik min. 2 huruf — kode service / keterangan / kelas…"
        class="w-full text-sm px-3 py-2 border rounded-md outline-none transition-colors bg-white placeholder-gray-400 border-gray-300 focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary"
    >

    <div data-tp-list class="hidden absolute z-20 mt-1 w-full max-h-64 overflow-auto bg-white border border-gray-300 rounded-md shadow-lg"></div>

    <p class="mt-1 text-xs text-gray-400">Pencarian mencakup kode service, keterangan, kelas, dan provider.</p>

    @error($name)
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>

@once
@push('scripts')
<script>
(function () {
    let tpTimer = null;

    const tpList = (root) => root.querySelector('[data-tp-list]');
    const tpInput = (root) => root.querySelector('[data-tp-input]');
    const tpValue = (root) => root.querySelector('[data-tp-value]');

    function tpMessage(root, text, cls) {
        const list = tpList(root);
        list.innerHTML = '';
        const div = document.createElement('div');
        div.className = 'px-3 py-2 text-sm ' + (cls || 'text-gray-500');
        div.textContent = text;
        list.appendChild(div);
        list.classList.remove('hidden');
    }

    function tpRender(root, items) {
        if (items.length === 0) {
            tpMessage(root, 'Tarif tidak ditemukan.');
            return;
        }
        const list = tpList(root);
        list.innerHTML = '';
        items.forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'block w-full text-left px-3 py-2 text-sm hover:bg-sp-hover focus:bg-sp-hover focus:outline-none';
            btn.dataset.tpId = item.id;
            btn.dataset.tpLabel = item.label;
            btn.textContent = item.label;
            list.appendChild(btn);
        });
        list.classList.remove('hidden');
    }

    async function tpFetch(root, params) {
        const res = await fetch(root.dataset.tpUrl + '?' + params, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('gagal');
        const json = await res.json();
        return json.data || [];
    }

    async function tpSearch(root, q) {
        tpMessage(root, 'Mencari…');
        try {
            tpRender(root, await tpFetch(root, 'q=' + encodeURIComponent(q)));
        } catch (e) {
            tpMessage(root, 'Pencarian gagal.', 'text-red-500');
        }
    }

    async function tpPrefill(root, id) {
        try {
            const items = await tpFetch(root, 'id=' + encodeURIComponent(id));
            if (items.length > 0) tpInput(root).value = items[0].label;
        } catch (e) { /* biarkan kosong, user tinggal pilih ulang */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-tp-root]').forEach((root) => {
            const initial = tpInput(root).dataset.tpInitial || '';
            if (initial) {
                tpInput(root).value = initial;
            } else if (tpValue(root).value) {
                // Ada nilai tersimpan (edit / old input) tapi label belum tampil.
                tpPrefill(root, tpValue(root).value);
            }
        });
    });

    document.addEventListener('input', (e) => {
        const input = e.target.closest('[data-tp-input]');
        if (!input) return;
        const root = input.closest('[data-tp-root]');
        clearTimeout(tpTimer);
        // Teks berubah = pilihan lama tidak valid lagi.
        tpValue(root).value = '';
        const q = input.value.trim();
        if (q.length < 2) {
            tpList(root).classList.add('hidden');
            return;
        }
        tpTimer = setTimeout(() => tpSearch(root, q), 300);
    });

    document.addEventListener('click', (e) => {
        const item = e.target.closest('[data-tp-id]');
        if (item) {
            const root = item.closest('[data-tp-root]');
            tpValue(root).value = item.dataset.tpId;
            tpInput(root).value = item.dataset.tpLabel;
            tpList(root).classList.add('hidden');
            return;
        }
        document.querySelectorAll('[data-tp-root]').forEach((root) => {
            if (!root.contains(e.target)) tpList(root).classList.add('hidden');
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-tp-list]').forEach((list) => list.classList.add('hidden'));
        }
    });
})();
</script>
@endpush
@endonce
