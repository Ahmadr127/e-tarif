<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Unit Cost - {{ $period->name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #111827; margin: 0; }
        h1 { font-size: 14px; margin: 0; text-align: center; text-transform: uppercase; letter-spacing: .04em; }
        .subtitle { text-align: center; color: #4b5563; font-size: 9px; margin-top: 2px; }
        .meta { margin-top: 10px; color: #374151; font-size: 9px; }
        .meta table { border-collapse: collapse; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .meta td.label { color: #6b7280; width: 90px; }
        .summary { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .summary td { border: 1px solid #cbd5e1; padding: 5px 8px; text-align: center; width: 25%; }
        .summary .value { font-size: 13px; font-weight: bold; }
        .summary .label { font-size: 8px; color: #475569; text-transform: uppercase; }
        hr { border: none; border-top: 1.5px solid #0f172a; margin: 10px 0 4px; }
    </style>
</head>
<body>
    <h1>Laporan Hasil Perhitungan Unit Cost</h1>
    <div class="subtitle">{{ config('app.name') }}</div>

    <div class="meta">
        <table>
            <tr>
                <td class="label">Periode</td>
                <td>: {{ $period->name }} ({{ $period->code }}) &mdash; Tahun {{ $period->period_year }}</td>
            </tr>
            <tr>
                <td class="label">Unit</td>
                <td>: {{ $unit?->name ?? 'Tanpa unit (global)' }}{{ $unit ? ' ('.$unit->code.')' : '' }}</td>
            </tr>
            <tr>
                <td class="label">Dicetak</td>
                <td>: {{ $generated_at->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    {{-- <table class="summary">
        <tr>
            <td>
                <div class="value">{{ (int) $summary['total'] }}</div>
                <div class="label">Total Parameter</div>
            </td>
            <td>
                <div class="value" style="color:#166534;">{{ (int) $summary['valid'] }}</div>
                <div class="label">Valid</div>
            </td>
            <td>
                <div class="value" style="color:#92400e;">{{ (int) $summary['incomplete'] }}</div>
                <div class="label">Belum Lengkap</div>
            </td>
            <td>
                <div class="value" style="color:#991b1b;">{{ (int) $summary['invalid'] }}</div>
                <div class="label">Error</div>
            </td>
        </tr>
    </table> --}}

    <hr>

    @if($groups === [])
        <p style="color:#64748b;font-size:9px;font-style:italic;">
            Tidak ada komponen yang di-assign ke unit ini. Atur assignment komponen
            terlebih dahulu melalui halaman Unit Cost.
        </p>
    @else
        <x-unit-cost.official-report-table :groups="$groups" :show-formula="true" :show-summary="true" />
    @endif

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(240, 812, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", null, 8, [0.4, 0.45, 0.5]);
        }
    </script>
</body>
</html>
