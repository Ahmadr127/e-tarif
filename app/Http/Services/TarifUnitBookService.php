<?php

namespace App\Http\Services;

use App\Models\OrganizationUnit;
use App\Models\Tarif;
use App\Models\TarifUnitServicePrefix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query buku tarif unit berbasis prefix kode service.
 *
 * Buku tarif bukan salinan/assignment tarif: hasilnya selalu query langsung
 * ke tabel `tarifs`, sehingga perubahan nominal, periode, atau penambahan
 * tarif baru langsung terlihat tanpa sinkronisasi apa pun.
 *
 * Prefix TIDAK di-cache. Pengukuran pada data aktual (10.789 service)
 * menunjukkan satu query prefix terindeks jauh lebih murah daripada
 * menyimpan cache di database cache store (default proyek ini), yang justru
 * menambah query dan risiko data basi. Prefix diambil sekali per request.
 */
class TarifUnitBookService
{
    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public const DEFAULT_PER_PAGE = 25;

    public function __construct(protected TarifService $tarifService) {}

    /**
     * Daftar unit untuk halaman pemilih buku tarif.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getUnits(array $filters = [])
    {
        $query = OrganizationUnit::query()
            ->with(['type', 'parent'])
            ->withCount([
                'servicePrefixes as service_prefixes_count',
                'servicePrefixes as active_prefixes_count' => fn ($q) => $q->where('is_active', true),
            ]);

        if (! empty($filters['search'])) {
            // Case-insensitive via LOWER() (konsisten PostgreSQL & SQLite).
            $like = '%'.addcslashes(mb_strtolower($filters['search']), '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like]);
            });
        }

        if (! empty($filters['type_id'])) {
            $query->where('type_id', $filters['type_id']);
        }

        $status = $filters['status'] ?? 'active';
        if ($status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        return $query->orderBy('name')
            ->paginate($this->perPage($filters, [10, 25, 50], 10))
            ->withQueryString();
    }

    /**
     * Semua konfigurasi prefix unit (aktif & nonaktif), urut untuk tampilan.
     *
     * @return Collection<int, TarifUnitServicePrefix>
     */
    public function prefixes(OrganizationUnit $unit): Collection
    {
        return $unit->servicePrefixes()
            ->orderByDesc('is_active')
            ->orderBy('prefix')
            ->get();
    }

    /**
     * Prefix aktif satu unit, sudah dinormalisasi dan unik.
     *
     * @return array<int, string>
     */
    public function activePrefixes(OrganizationUnit $unit): array
    {
        return $this->normalizePrefixes(
            $this->prefixes($unit)->where('is_active', true)->pluck('prefix')
        );
    }

    /**
     * @param  iterable<int|string, mixed>  $prefixes
     * @return array<int, string>
     */
    public function normalizePrefixes(iterable $prefixes): array
    {
        $normalized = [];

        foreach ($prefixes as $prefix) {
            $value = TarifUnitServicePrefix::normalize((string) $prefix);
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * Query dasar buku tarif satu unit (dipakai halaman, export, dan
     * pengecekan detail). Filter memakai TarifService::applyFilters agar
     * pencarian/status/periode konsisten dengan halaman daftar tarif.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $prefixes  prefix aktif yang sudah diambil
     * @return Builder<Tarif>
     */
    public function query(OrganizationUnit $unit, array $filters = [], ?array $prefixes = null): Builder
    {
        $query = Tarif::query()
            ->with([
                'jenisTarif:id,name',
                'provider:id,code,name',
                'service:id,code,name,description',
                'serviceClass:id,code,name',
            ])
            ->forServicePrefixes($prefixes ?? $this->activePrefixes($unit));

        $this->tarifService->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Satu halaman buku tarif (default 25 baris, maksimal 50 yang disarankan
     * tetap tersedia sebagai pilihan eksplisit).
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $prefixes
     */
    public function paginate(OrganizationUnit $unit, array $filters = [], ?array $prefixes = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->query($unit, $filters, $prefixes)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * Query export dengan urutan deterministik agar chunk aman.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $prefixes
     * @return Builder<Tarif>
     */
    public function exportQuery(OrganizationUnit $unit, array $filters = [], ?array $prefixes = null): Builder
    {
        return $this->query($unit, $filters, $prefixes)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Apakah tarif tertentu termasuk cakupan unit (dipakai halaman detail).
     *
     * @param  array<int, string>|null  $prefixes
     */
    public function contains(OrganizationUnit $unit, Tarif $tarif, ?array $prefixes = null): bool
    {
        return Tarif::query()
            ->forServicePrefixes($prefixes ?? $this->activePrefixes($unit))
            ->whereKey($tarif->getKey())
            ->exists();
    }

    /**
     * Prefix unit yang cocok dengan satu kode service (badge halaman detail).
     * Pencocokan selalu dari awal kode.
     *
     * @param  array<int, string>|null  $prefixes
     * @return array<int, string>
     */
    public function matchingPrefixes(OrganizationUnit $unit, ?string $serviceCode, ?array $prefixes = null): array
    {
        $code = TarifUnitServicePrefix::normalize($serviceCode);

        if ($code === '') {
            return [];
        }

        return array_values(array_filter(
            $prefixes ?? $this->activePrefixes($unit),
            static fn (string $prefix) => str_starts_with($code, $prefix)
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, int>  $allowed
     */
    protected function perPage(array $filters, array $allowed = self::PER_PAGE_OPTIONS, int $default = self::DEFAULT_PER_PAGE): int
    {
        $requested = (int) ($filters['per_page'] ?? $default);

        return in_array($requested, $allowed, true) ? $requested : $default;
    }
}
