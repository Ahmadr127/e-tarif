{{--
    Tabel input buku tarif (multi baris).

    Satu baris = satu tarif. Tarif dicari lewat AJAX (searchUrl, min. 2 huruf)
    dan kolom informasi tarif terisi otomatis dari hasil pencarian, ditambah
    status serta catatan. Baris dikirim sebagai items[<index>][...].

    Usage:
    <x-tarif-input-table :rows="old('items', [])" :search-url="route('tarif-units.search-tarifs')" />
--}}
@props([
    'searchUrl',
    'rows' => [],
])

@php
    $rows = is_array($rows) && count($rows) > 0 ? $rows : [[]];
@endphp

<div data-bt-root data-bt-url="{{ $searchUrl }}">
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-100 bg-white">
        <p class="text-xs text-gray-500">
            Isi satu baris untuk tiap tarif — pencarian minimal 2 huruf, kolom informasi terisi otomatis.
        </p>
        <button type="button" data-bt-add class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/40 rounded-md bg-white hover:bg-sp-hover transition-colors">
            <i class="bi bi-plus-lg"></i> Tambah Baris
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tarif</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kode Service</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Service</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Jenis Tarif</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kelas</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tarif (Rp)</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Catatan</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody data-bt-body class="divide-y divide-gray-100 bg-white">
                @foreach($rows as $index => $row)
                    @include('tarif-units.partials.input-row', ['index' => $index, 'row' => $row])
                @endforeach
            </tbody>
        </table>
    </div>

    <template data-bt-template>
        @include('tarif-units.partials.input-row', ['index' => '__INDEX__', 'row' => []])
    </template>
</div>

@once
@push('scripts')
<script>
(function () {
    const btRoot = document.querySelector('[data-bt-root]');
    if (!btRoot) return;

    const btUrl = btRoot.dataset.btUrl;
    const btBody = btRoot.querySelector('[data-bt-body]');
    const btTemplate = btRoot.querySelector('[data-bt-template]');
    let btNextIndex = 0;

    const rows = () => btBody.querySelectorAll('[data-bt-row]');
    const listOf = (row) => row.querySelector('[data-bt-list]');
    const inputOf = (row) => row.querySelector('[data-bt-input]');
    const valueOf = (row) => row.querySelector('[data-bt-value]');
    const hintOf = (row) => row.querySelector('[data-bt-hint]');

    let btOpenRow = null;

    // Daftar saran diposisikan fixed mengikuti input, supaya tidak terpotong
    // pembungkus tabel yang di-scroll dan tidak tertimpa baris di bawahnya.
    function placeList(row) {
        const list = listOf(row);
        const rect = inputOf(row).getBoundingClientRect();

        list.style.top = (rect.bottom + 4) + 'px';
        list.style.left = rect.left + 'px';
        list.style.width = rect.width + 'px';
    }

    function showList(row) {
        btOpenRow = row;
        placeList(row);
        listOf(row).classList.remove('hidden');
    }

    function hideList(row) {
        listOf(row).classList.add('hidden');
        if (btOpenRow === row) btOpenRow = null;
    }

    function hideAllLists() {
        btBody.querySelectorAll('[data-bt-list]').forEach((list) => list.classList.add('hidden'));
        btOpenRow = null;
    }

    function repositionList() {
        if (! btOpenRow) return;
        if (! btOpenRow.isConnected) {
            btOpenRow = null;
            return;
        }
        placeList(btOpenRow);
    }

    // Index baru selalu di atas index yang sudah ada di DOM (dipakai saat
    // menambah baris, termasuk setelah validasi gagal).
    rows().forEach((row) => {
        const name = valueOf(row)?.getAttribute('name') || '';
        const found = parseInt((name.match(/items\[(\d+)\]/) || [])[1], 10);
        if (! isNaN(found) && found >= btNextIndex) {
            btNextIndex = found + 1;
        }
    });

    function message(row, text, cls) {
        const list = listOf(row);
        list.innerHTML = '';
        const div = document.createElement('div');
        div.className = 'px-3 py-2 text-sm ' + (cls || 'text-gray-500');
        div.textContent = text;
        list.appendChild(div);
        showList(row);
    }

    function render(row, items) {
        if (items.length === 0) {
            message(row, 'Tarif tidak ditemukan.');
            return;
        }

        const list = listOf(row);
        list.innerHTML = '';
        items.forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'block w-full text-left px-3 py-2 text-sm hover:bg-sp-hover focus:bg-sp-hover focus:outline-none';
            btn.dataset.btId = item.id;
            btn.textContent = item.label;
            btn._btItem = item;
            list.appendChild(btn);
        });

        showList(row);
    }

    function fillInfo(row, item) {
        row.querySelectorAll('[data-bt-info]').forEach((cell) => {
            const value = item[cell.dataset.btInfo];
            cell.textContent = (value === null || value === undefined || value === '') ? '-' : value;
        });

        const hint = hintOf(row);
        if (hint) hint.textContent = item.label || '';
    }

    function clearInfo(row) {
        row.querySelectorAll('[data-bt-info]').forEach((cell) => { cell.textContent = '-'; });
        const hint = hintOf(row);
        if (hint) hint.textContent = 'Pilih tarif untuk melihat informasinya.';
    }

    async function fetchTarifs(params) {
        const res = await fetch(btUrl + '?' + params, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('gagal');
        const json = await res.json();
        return json.data || [];
    }

    async function search(row, q) {
        message(row, 'Mencari…');
        try {
            render(row, await fetchTarifs('q=' + encodeURIComponent(q)));
        } catch (e) {
            message(row, 'Pencarian gagal.', 'text-red-500');
        }
    }

    async function prefill(row) {
        const id = valueOf(row)?.value;
        if (!id) return;

        try {
            const items = await fetchTarifs('id=' + encodeURIComponent(id));
            if (items.length > 0) {
                inputOf(row).value = items[0].label;
                fillInfo(row, items[0]);
            }
        } catch (e) {
            /* biarkan kosong, user tinggal pilih ulang */
        }
    }

    function addRow() {
        const html = btTemplate.innerHTML.replace(/__INDEX__/g, String(btNextIndex++));
        btBody.insertAdjacentHTML('beforeend', html);
        const row = rows()[rows().length - 1];
        inputOf(row).focus();
    }

    btRoot.addEventListener('click', (e) => {
        const add = e.target.closest('[data-bt-add]');
        if (add) {
            addRow();
            return;
        }

        const remove = e.target.closest('[data-bt-remove]');
        if (remove) {
            const row = remove.closest('[data-bt-row]');
            // Sisakan minimal satu baris kosong.
            if (rows().length <= 1) {
                valueOf(row).value = '';
                inputOf(row).value = '';
                row.querySelector('[name$="[notes]"]').value = '';
                clearInfo(row);
            } else {
                row.remove();
            }
            return;
        }

        const option = e.target.closest('[data-bt-id]');
        if (option) {
            const row = option.closest('[data-bt-row]');
            valueOf(row).value = option.dataset.btId;
            inputOf(row).value = option.textContent;
            fillInfo(row, option._btItem || {});
            hideList(row);
            return;
        }

        // Klik di luar picker: tutup semua daftar saran.
        hideAllLists();
    });

    btRoot.addEventListener('input', (e) => {
        const input = e.target.closest('[data-bt-input]');
        if (! input) return;

        const row = input.closest('[data-bt-row]');
        clearTimeout(row._btTimer);
        // Teks berubah = pilihan lama tidak valid lagi.
        valueOf(row).value = '';
        clearInfo(row);

        const q = input.value.trim();
        if (q.length < 2) {
            hideList(row);
            return;
        }

        row._btTimer = setTimeout(() => search(row, q), 300);
    });

    // Daftar saran ikut bergerak saat halaman/tabel di-scroll atau di-resize.
    window.addEventListener('scroll', repositionList, true);
    window.addEventListener('resize', repositionList);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideAllLists();
        }
    });

    // Baris dari old input / halaman edit: lengkapi label & informasi tarif.
    rows().forEach((row) => {
        if (valueOf(row)?.value) prefill(row);
    });
})();
</script>
@endpush
@endonce
