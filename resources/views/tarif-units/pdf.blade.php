<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Tarif - {{ $unit->name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #111827; margin: 0; }
        h1 { font-size: 14px; margin: 0; }
        .meta { color: #4b5563; font-size: 8.5px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 3px 4px; vertical-align: top; }
        th { background-color: #f1f5f9; text-align: left; font-size: 8.5px; }
        td.num { text-align: right; white-space: nowrap; }
        td.center { text-align: center; }
        .empty { text-align: center; color: #6b7280; }
    </style>
</head>
<body>
    <h1>Buku Tarif: {{ $unit->name }}</h1>
    <div class="meta">
        {{ $unit->code }}
        &bull; {{ $unit->type?->display_name ?: ($unit->type?->name ?? '-') }}
        @if($unit->parent) &bull; Induk: {{ $unit->parent->name }} @endif
        <br>
        {{ $mappings->count() }} tarif
        @if(!empty($filters['search'])) &bull; pencarian: "{{ $filters['search'] }}" @endif
        @if(!empty($filters['status'])) &bull; status: {{ $filters['status'] === 'active' ? 'Aktif' : 'Non-Aktif' }} @endif
        &bull; dicetak {{ now()->format('d/m/Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th style="width: 70px;">Kode Service</th>
                <th>Keterangan</th>
                <th style="width: 70px;">Kelas</th>
                <th style="width: 78px;">Tarif (Rp)</th>
                <th style="width: 52px;">Status</th>
                <th style="width: 130px;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mappings as $index => $mapping)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $mapping->tarif?->service?->code ?? '-' }}</td>
                    <td>{{ $mapping->tarif?->service?->description ?: ($mapping->tarif?->service?->name ?? '-') }}</td>
                    <td>{{ $mapping->tarif?->serviceClass?->name ?? '-' }}</td>
                    <td class="num">{{ number_format((float) ($mapping->tarif?->tariff ?? 0), 0, ',', '.') }}</td>
                    <td>{{ $mapping->status === 'active' ? 'Aktif' : 'Non-Aktif' }}</td>
                    <td>{{ $mapping->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="7">Belum ada tarif di buku tarif unit ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(700, 560, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", null, 8, [0.4, 0.45, 0.5]);
        }
    </script>
</body>
</html>
