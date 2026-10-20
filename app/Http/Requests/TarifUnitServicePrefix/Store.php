<?php

namespace App\Http\Requests\TarifUnitServicePrefix;

use App\Models\OrganizationUnit;
use App\Models\TarifUnitServicePrefix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi tambah prefix. Unit berasal dari route (bukan input form) agar
 * pengguna tidak bisa memindahkan prefix ke unit lain lewat manipulasi field.
 */
class Store extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi ditangani middleware permission + scope unit
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'prefix' => TarifUnitServicePrefix::normalize($this->input('prefix')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unitId = $this->unitId();

        return [
            'prefix' => [
                'required',
                'string',
                'max:'.TarifUnitServicePrefix::MAX_LENGTH,
                'regex:'.TarifUnitServicePrefix::PATTERN,
                Rule::unique('tarif_unit_service_prefixes', 'prefix')
                    ->where(fn ($query) => $query->where('organization_unit_id', $unitId)),
            ],
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Unit dari route (mode admin) atau unit profil pengguna (mode unit
     * sendiri). Dipakai untuk unique constraint per unit.
     */
    protected function unitId(): ?int
    {
        $unit = $this->route('organization_unit');

        if ($unit instanceof OrganizationUnit) {
            return $unit->id;
        }

        if (is_numeric($unit)) {
            return (int) $unit;
        }

        return auth()->user()?->organizationUnit?->id;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prefix.required' => 'Prefix wajib diisi.',
            'prefix.max' => 'Prefix maksimal '.TarifUnitServicePrefix::MAX_LENGTH.' karakter.',
            'prefix.regex' => 'Prefix hanya boleh huruf, angka, titik, dan strip (tanpa spasi atau wildcard).',
            'prefix.unique' => 'Prefix ini sudah terdaftar pada unit tersebut.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
