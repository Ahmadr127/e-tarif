@php
    // $index: kunci baris (number untuk baris lama, string untuk template).
    $row = $row ?? [];
    $index = $index ?? '__INDEX__';
    $tarifId = old("items.{$index}.tarif_id", $row['tarif_id'] ?? null);
    $status = old("items.{$index}.status", $row['status'] ?? 'active');
    $notes = old("items.{$index}.notes", $row['notes'] ?? '');
    $error = $errors->first("items.{$index}.tarif_id");
    $inputClass = 'w-full text-sm px-3 py-2 border rounded-md outline-none transition-colors bg-white placeholder-gray-400 border-gray-300 focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary';
@endphp

<tr data-bt-row class="align-top">
    <td class="px-4 py-3 min-w-[20rem]">
        <div class="relative" data-bt-picker>
            <input type="hidden" name="items[{{ $index }}][tarif_id]" value="{{ $tarifId }}" data-bt-value>
            <input
                type="text"
                autocomplete="off"
                spellcheck="false"
                data-bt-input
                placeholder="Ketik min. 2 huruf — kode service / keterangan / kelas…"
                class="{{ $inputClass }}"
            >
            {{-- Daftar saran diposisikan fixed oleh JS: kalau absolute, ia
                 terpotong pembungkus tabel yang bisa di-scroll. --}}
            <div data-bt-list class="hidden fixed z-50 max-h-64 overflow-auto bg-white border border-gray-300 rounded-md shadow-lg"></div>
        </div>
        @if($error)
            <p class="mt-1 text-xs text-red-500">{{ $error }}</p>
        @endif
        <p class="mt-1 text-xs text-gray-400" data-bt-hint>
            {{ $tarifId ? 'Memuat informasi tarif…' : 'Pilih tarif untuk melihat informasinya.' }}
        </p>
    </td>
    <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-700" data-bt-info="service_code">-</td>
    <td class="px-4 py-3 min-w-[14rem] text-sm text-gray-900" data-bt-info="service_name">-</td>
    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600" data-bt-info="jenis_name">-</td>
    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700" data-bt-info="class_name">-</td>
    <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900" data-bt-info="tariff_label">-</td>
    <td class="px-4 py-3 whitespace-nowrap">
        <select name="items[{{ $index }}][status]" required class="text-sm px-2 py-1.5 border rounded-md outline-none transition-colors bg-white border-gray-300 focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary">
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Non-Aktif</option>
        </select>
    </td>
    <td class="px-4 py-3 min-w-[12rem]">
        <input type="text" name="items[{{ $index }}][notes]" value="{{ $notes }}" placeholder="Opsional"
               class="{{ $inputClass }}">
    </td>
    <td class="px-4 py-3 whitespace-nowrap text-right">
        <button type="button" data-bt-remove title="Hapus baris"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-500 border border-gray-300 bg-white hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-colors">
            <i class="bi bi-trash text-xs"></i>
        </button>
    </td>
</tr>
