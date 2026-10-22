<?php

namespace App\Services\UnitCost;

use App\Models\OrganizationUnit;
use App\Models\UnitCostPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Menyusun data laporan/surat resmi Unit Cost.
 *
 * Service ini reusable: halaman HTML, PDF, maupun ekspor lain dapat memakai
 * struktur data yang sama. Sumber angka diambil dari evaluasi periode/unit
 * terkini (termasuk default value), bukan snapshot, sehingga cocok dipakai
 * selama periode masih dapat diedit.
 */
class UnitCostOfficialReportService
{
    public function __construct(protected ParameterEvaluationService $evaluation) {}

    /**
     * ID komponen aktif yang di-assign ke unit.
     *
     * `null` berarti tidak difilter (konteks tanpa unit/global), sedangkan
     * array kosong berarti unit tidak memiliki komponen yang di-assign.
     *
     * @return array<int, int>|null
     */
    public function componentIdsForUnit(?OrganizationUnit $unit): ?array
    {
        if ($unit === null) {
            return null;
        }

        return $unit->unitCostComponents()
            ->where('unit_cost_components.is_active', true)
            ->pluck('unit_cost_components.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>|null  $componentIds  Batasi ke komponen tertentu.
     *                                              Parameter global (tanpa komponen)
     *                                              tetap disertakan.
     * @return array{
     *     period: UnitCostPeriod,
     *     unit: OrganizationUnit|null,
     *     generated_at: Carbon,
     *     groups: array<int, array<string, mixed>>,
     *     summary: array{total: int, valid: int, incomplete: int, invalid: int}
     * }
     */
    public function build(UnitCostPeriod $period, ?OrganizationUnit $unit, ?array $componentIds = null): array
    {
        $result = $this->evaluation->evaluate($period, $unit);

        $allowedComponentIds = $componentIds === null
            ? null
            : array_map('intval', $componentIds);

        $groups = [];
        $summary = [
            'total' => 0,
            'valid' => 0,
            'incomplete' => 0,
            'invalid' => 0,
        ];

        foreach ($result->parameters as $row) {
            $parameter = $row['parameter'];
            $component = $parameter->component;

            // Parameter komponen lain yang tidak di-assign ke unit tidak ikut
            // dilaporkan. Parameter global (component_id null) selalu masuk.
            if ($allowedComponentIds !== null
                && $component !== null
                && ! in_array((int) $component->id, $allowedComponentIds, true)) {
                continue;
            }

            $key = $component === null ? 'global' : (string) $component->id;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'component' => $component,
                    'label' => $component?->name ?? 'Parameter Global',
                    'code' => $component?->code ?? 'GLOBAL',
                    'summary' => [
                        'total' => 0,
                        'valid' => 0,
                        'incomplete' => 0,
                        'invalid' => 0,
                    ],
                    'rows' => [],
                ];
            }

            $status = (string) $row['status'];

            $groups[$key]['rows'][] = [
                'code' => $parameter->formulaKey(),
                'name' => $parameter->name,
                'unit' => $parameter->unit,
                'input_type' => (string) $row['input_type'],
                'method_label' => $row['input_type'] === 'formula' ? 'Input Rumus' : 'Input Nilai',
                'formula_text' => (string) ($row['formula_text'] ?? ''),
                'input_value' => $row['input_value'],
                'result_value' => $row['result_value'],
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'error' => $row['error'],
                'source' => (string) ($row['source'] ?? 'missing'),
            ];

            $groups[$key]['summary']['total']++;
            $groups[$key]['summary'][$status] = ($groups[$key]['summary'][$status] ?? 0) + 1;

            $summary['total']++;
            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }

        return [
            'period' => $period,
            'unit' => $unit,
            'generated_at' => now(),
            'groups' => array_values($groups),
            'summary' => $summary,
        ];
    }

    /**
     * Nama file PDF yang stabil untuk periode + unit.
     */
    public function filename(UnitCostPeriod $period, ?OrganizationUnit $unit): string
    {
        $parts = [
            'unit-cost',
            Str::slug((string) $period->code) ?: 'periode',
        ];

        if ($unit !== null) {
            $parts[] = Str::slug((string) $unit->code) ?: 'unit';
        }

        return implode('_', $parts).'_'.now()->format('Ymd-His').'.pdf';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'valid' => 'Valid',
            'invalid' => 'Error',
            default => 'Belum lengkap',
        };
    }
}
