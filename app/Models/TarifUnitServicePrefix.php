<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prefix kode service yang menentukan cakupan buku tarif satu unit.
 *
 * Prefix hanya dicocokkan dari AWAL `services.code` (bukan substring) dan
 * disimpan dalam huruf besar. Satu unit boleh punya beberapa prefix aktif;
 * unit induk TIDAK mewariskan prefix ke unit anak.
 */
class TarifUnitServicePrefix extends Model
{
    use HasFactory;

    public const MAX_LENGTH = 50;

    /**
     * Karakter yang diizinkan: huruf, angka, titik, dan strip.
     * Wildcard SQL (`%`, `_`), backslash, dan spasi ditolak agar prefix
     * tidak pernah berubah menjadi pola pencarian yang lebih luas.
     */
    public const PATTERN = '/^[A-Z0-9][A-Z0-9.\-]*$/';

    protected $table = 'tarif_unit_service_prefixes';

    protected $fillable = [
        'organization_unit_id',
        'prefix',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<OrganizationUnit, $this>
     */
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    /**
     * Normalisasi konsisten: trim + huruf besar.
     * Spasi di tengah tidak "dibuang diam-diam"; validasi akan menolaknya.
     */
    public static function normalize(?string $prefix): string
    {
        return mb_strtoupper(trim((string) $prefix));
    }

    /**
     * Prefix valid = tidak kosong, panjang wajar, dan hanya karakter aman.
     */
    public static function isValid(?string $prefix): bool
    {
        $prefix = self::normalize($prefix);

        return $prefix !== ''
            && mb_strlen($prefix) <= self::MAX_LENGTH
            && preg_match(self::PATTERN, $prefix) === 1;
    }

    /**
     * Escape wildcard LIKE (`!`, `%`, `_`) memakai karakter escape `!`.
     *
     * Backslash sengaja tidak dipakai: pada PostgreSQL + PDO, `ESCAPE '\'`
     * membuat parser parameter PDO salah menghitung placeholder. Prefix yang
     * lolos validasi tidak memuat karakter ini, escaping tetap dipakai sebagai
     * lapisan pertahanan.
     */
    public static function escapeLike(string $prefix): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix);
    }

    /**
     * @param  Builder<TarifUnitServicePrefix>  $query
     * @return Builder<TarifUnitServicePrefix>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
