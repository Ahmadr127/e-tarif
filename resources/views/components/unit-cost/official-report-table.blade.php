@props([
    'groups' => [],
    'showFormula' => true,
    'showSummary' => true,
])

@php
    $formatNumber = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        $formatted = number_format((float) $value, 6, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    };

    $statusStyle = static function (string $status): string {
        return match ($status) {
            'valid' => 'background:#dcfce7;color:#166534;',
            'invalid' => 'background:#fee2e2;color:#991b1b;',
            default => 'background:#fef3c7;color:#92400e;',
        };
    };
@endphp

<style>
    .ucr-table { width: 100%; border-collapse: collapse; margin: 4px 0 12px; }
    .ucr-table th, .ucr-table td { border: 1px solid #cbd5e1; padding: 4px 6px; vertical-align: top; text-align: left; }
    .ucr-table th { background-color: #f1f5f9; font-size: 8.5px; text-transform: uppercase; letter-spacing: .02em; }
    .ucr-table td { font-size: 9px; }
    .ucr-table td.ucr-num { text-align: right; white-space: nowrap; }
    .ucr-table td.ucr-center { text-align: center; }
    .ucr-code { font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; }
    .ucr-formula { display: block; margin-top: 1px; color: #64748b; font-size: 7.5px; font-family: DejaVu Sans Mono, monospace; word-break: break-all; }
    .ucr-badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: bold; }
    .ucr-group-title { margin: 10px 0 2px; font-size: 10.5px; font-weight: bold; color: #0f172a; }
    .ucr-group-code { color: #64748b; font-size: 8px; font-weight: normal; font-family: DejaVu Sans Mono, monospace; }
    .ucr-group-summary { color: #475569; font-size: 8px; margin-bottom: 2px; }
    .ucr-empty { color: #64748b; font-size: 9px; font-style: italic; }
</style>

@forelse($groups as $group)
    <div class="ucr-group-title">
        {{ $group['label'] }}
        <span class="ucr-group-code">{{ $group['code'] }}</span>
    </div>

    {{-- @if($showSummary)
        @php $groupSummary = $group['summary']; @endphp
        <div class="ucr-group-summary">
            {{ (int) $groupSummary['total'] }} parameter
            &bull; Valid: {{ (int) $groupSummary['valid'] }}
            &bull; Belum lengkap: {{ (int) $groupSummary['incomplete'] }}
            &bull; Error: {{ (int) $groupSummary['invalid'] }}
        </div>
    @endif --}}

    <table class="ucr-table">
        <thead>
            <tr>
                <th style="width: 26px;">No</th>
                {{-- <th style="width: 150px;">Kode Parameter</th> --}}
                <th>Nama Parameter</th>
                <th style="width: 70px;">Satuan</th>
                {{-- <th style="width: 70px;">Metode</th> --}}
                <th style="width: 110px;">Hasil</th>
                {{-- <th style="width: 72px;">Status</th> --}}
            </tr>
        </thead>
        <tbody>
            @forelse($group['rows'] as $index => $row)
                <tr>
                    <td class="ucr-center">{{ $index + 1 }}</td>
                    {{-- <td class="ucr-code">{{ '{'.$row['code'].'}' }}</td> --}}
                    <td>
                        {{ $row['name'] }}
                        {{-- @if($showFormula && $row['input_type'] === 'formula' && $row['formula_text'] !== '')
                            <span class="ucr-formula">{{ $row['formula_text'] }}</span>
                        @endif --}}
                    </td>
                    <td>{{ $row['unit'] ?: '-' }}</td>
                    {{-- <td>{{ $row['method_label'] }}</td> --}}
                    <td class="ucr-num">{{ $formatNumber($row['result_value']) }}</td>
                    {{-- <td>
                        <span class="ucr-badge" style="{{ $statusStyle($row['status']) }}">{{ $row['status_label'] }}</span>
                    </td> --}}
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="ucr-empty">Belum ada parameter pada komponen ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@empty
    <p class="ucr-empty">Belum ada parameter aktif untuk dilaporkan.</p>
@endforelse
