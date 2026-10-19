<?php

namespace App\Http\Requests\TarifUnitMapping;

use App\Models\TarifUnitMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Update extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mapping = $this->route('tarif_unit');
        $mappingId = $mapping instanceof TarifUnitMapping ? $mapping->id : $mapping;

        return [
            'tarif_id' => [
                'required',
                'integer',
                'exists:tarifs,id',
                Rule::unique('tarif_unit_mappings', 'tarif_id')
                    ->where(fn ($query) => $query->where('organization_unit_id', $this->input('organization_unit_id')))
                    ->ignore($mappingId),
            ],
            'organization_unit_id' => 'required|integer|exists:organization_units,id',
            'status' => 'required|string|in:'.implode(',', TarifUnitMapping::STATUSES),
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'tarif_id.unique' => 'Tarif ini sudah dipetakan ke unit yang dipilih.',
            'tarif_id.exists' => 'Tarif yang dipilih tidak ditemukan.',
            'organization_unit_id.exists' => 'Unit organisasi yang dipilih tidak ditemukan.',
        ];
    }
}
