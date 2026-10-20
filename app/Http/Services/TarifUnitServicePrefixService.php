<?php

namespace App\Http\Services;

use App\Models\OrganizationUnit;
use App\Models\TarifUnitServicePrefix;
use App\Services\ActivityLogService;

/**
 * CRUD konfigurasi prefix buku tarif unit.
 *
 * Seluruh perubahan dicatat ke activity log. Prefix selalu dinormalisasi
 * (trim + huruf besar) sebelum disimpan agar unique constraint database
 * benar-benar mencegah duplikat.
 */
class TarifUnitServicePrefixService
{
    public function __construct(protected ActivityLogService $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(OrganizationUnit $unit, array $data): TarifUnitServicePrefix
    {
        $prefix = $unit->servicePrefixes()->create([
            'prefix' => TarifUnitServicePrefix::normalize($data['prefix'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->activityLogger->log(
            $prefix,
            'created',
            ['attributes' => $prefix->toArray()],
            'Menambahkan prefix '.$prefix->prefix.' ke buku tarif unit '.$unit->name
        );

        return $prefix;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TarifUnitServicePrefix $prefix, array $data): TarifUnitServicePrefix
    {
        $oldData = $prefix->toArray();

        $prefix->update([
            'prefix' => TarifUnitServicePrefix::normalize($data['prefix'] ?? $prefix->prefix),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        $prefix->refresh();

        $this->activityLogger->logUpdated($prefix, $oldData, $prefix->toArray());

        return $prefix;
    }

    public function delete(TarifUnitServicePrefix $prefix): bool
    {
        $prefix->loadMissing('organizationUnit');

        $this->activityLogger->logDeleted(
            $prefix,
            'Menghapus prefix '.$prefix->prefix.' dari buku tarif unit '.($prefix->organizationUnit?->name ?? '-')
        );

        return (bool) $prefix->delete();
    }
}
