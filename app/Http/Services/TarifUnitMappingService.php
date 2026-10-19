<?php

namespace App\Http\Services;

use App\Models\OrganizationUnit;
use App\Models\Tarif;
use App\Models\TarifUnitMapping;
use App\Services\ActivityLogService;

class TarifUnitMappingService
{
    public function __construct(protected ActivityLogService $activityLogger) {}

    /**
     * Daftar unit organisasi untuk halaman Buku Tarif, lengkap dengan jumlah
     * tarif yang sudah terdaftar di tiap unit.
     */
    public function getUnits(array $filters = [])
    {
        $query = OrganizationUnit::query()
            ->with(['type', 'parent'])
            ->withCount('tarifMappings');

        if (!empty($filters['search'])) {
            // Case-insensitive via LOWER() (konsisten PostgreSQL & SQLite).
            $like = '%'.addcslashes(mb_strtolower($filters['search']), '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(code) LIKE ?', [$like]);
            });
        }

        if (!empty($filters['type_id'])) {
            $query->where('type_id', $filters['type_id']);
        }

        $status = $filters['status'] ?? 'active';
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        return $query->orderBy('name')->paginate($this->perPage($filters))->withQueryString();
    }

    /**
     * Daftar tarif yang sudah masuk buku tarif satu unit organisasi.
     */
    public function getUnitMappings(OrganizationUnit $unit, array $filters = [])
    {
        $query = TarifUnitMapping::query()
            ->with(['tarif.jenisTarif', 'tarif.provider', 'tarif.service', 'tarif.serviceClass'])
            ->where('organization_unit_id', $unit->id);

        if (!empty($filters['search'])) {
            $like = '%'.addcslashes(mb_strtolower($filters['search']), '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->whereHas('tarif.service', function ($sq) use ($like) {
                    $sq->whereRaw('LOWER(code) LIKE ?', [$like])
                       ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                       ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
                })->orWhere('notes', 'like', $like);
            });
        }

        if (!empty($filters['status']) && in_array($filters['status'], TarifUnitMapping::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage($filters))->withQueryString();
    }

    /**
     * Cari tarif untuk picker (AJAX): minimal 2 karakter, atau tepat 1 id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchTarifs(?string $query = null, ?int $id = null, int $limit = 20): array
    {
        $base = Tarif::query()->with(['jenisTarif', 'service', 'serviceClass', 'provider']);

        if ($id) {
            return $base->whereKey($id)->get()
                ->map(fn (Tarif $tarif) => $this->tarifPayload($tarif))
                ->all();
        }

        $term = trim((string) $query);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';

        $tarifs = $base->where(function ($q) use ($like) {
            $q->whereHas('service', function ($sq) use ($like) {
                $sq->whereRaw('LOWER(code) LIKE ?', [$like])
                   ->orWhereRaw('LOWER(name) LIKE ?', [$like])
                   ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
            })->orWhereHas('serviceClass', function ($cq) use ($like) {
                $cq->whereRaw('LOWER(name) LIKE ?', [$like]);
            })->orWhereHas('provider', function ($pq) use ($like) {
                $pq->whereRaw('LOWER(code) LIKE ?', [$like])
                   ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            });
        })->orderBy('id')->limit($limit)->get();

        return $tarifs->map(fn (Tarif $tarif) => $this->tarifPayload($tarif))->all();
    }

    /**
     * Data satu tarif untuk picker: label + kolom informasi yang ditampilkan
     * di tabel input buku tarif.
     *
     * @return array<string, mixed>
     */
    protected function tarifPayload(Tarif $tarif): array
    {
        $tariff = (float) $tarif->tariff;

        return [
            'id' => $tarif->id,
            'label' => $tarif->display_label,
            'provider_code' => $tarif->provider?->code,
            'service_code' => $tarif->service?->code,
            'service_name' => $tarif->service?->description ?: $tarif->service?->name,
            'class_name' => $tarif->serviceClass?->name,
            'jenis_name' => $tarif->jenisTarif?->name,
            'surgery_type' => $tarif->surgery_type,
            'tariff' => $tariff,
            'tariff_label' => 'Rp '.number_format($tariff, 0, ',', '.'),
        ];
    }

    public function createMapping(array $data): TarifUnitMapping
    {
        $mapping = TarifUnitMapping::create($data);
        $mapping->load(['tarif.service', 'tarif.serviceClass', 'organizationUnit']);

        $this->activityLogger->log(
            $mapping,
            'created',
            ['attributes' => $mapping->toArray()],
            'Menambahkan tarif '.$mapping->tarif?->display_label.' ke buku tarif unit '.$mapping->organizationUnit?->name
        );

        return $mapping;
    }

    public function updateMapping(TarifUnitMapping $mapping, array $data): TarifUnitMapping
    {
        $mapping->load(['tarif.service', 'tarif.serviceClass', 'organizationUnit']);
        $oldData = $mapping->toArray();

        $mapping->update($data);
        $mapping->refresh();
        $mapping->load(['tarif.service', 'tarif.serviceClass', 'tarif.provider', 'organizationUnit.type']);
        $newData = $mapping->toArray();

        $this->activityLogger->logUpdated($mapping, $oldData, $newData);

        return $mapping;
    }

    public function deleteMapping(TarifUnitMapping $mapping): ?bool
    {
        $mapping->load(['tarif.service', 'tarif.serviceClass', 'organizationUnit']);

        $this->activityLogger->logDeleted($mapping);

        return $mapping->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function perPage(array $filters): int
    {
        $requested = (int) ($filters['per_page'] ?? 10);

        return in_array($requested, [5, 10, 25, 50, 100], true) ? $requested : 10;
    }
}
