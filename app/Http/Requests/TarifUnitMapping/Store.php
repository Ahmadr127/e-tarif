<?php

namespace App\Http\Requests\TarifUnitMapping;

use App\Models\TarifUnitMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Store extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Input buku tarif dikirim sebagai beberapa baris (items) sekaligus.
     */
    public function rules(): array
    {
        return [
            'organization_unit_id' => 'required|integer|exists:organization_units,id',
            'items' => 'required|array|min:1',
            'items.*.tarif_id' => [
                'required',
                'integer',
                'distinct',
                'exists:tarifs,id',
                Rule::unique('tarif_unit_mappings', 'tarif_id')
                    ->where(fn ($query) => $query->where('organization_unit_id', $this->input('organization_unit_id'))),
            ],
            'items.*.status' => 'required|string|in:'.implode(',', TarifUnitMapping::STATUSES),
            'items.*.notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu tarif harus diisi.',
            'items.min' => 'Minimal satu tarif harus diisi.',
            'items.*.tarif_id.required' => 'Pilih tarif untuk baris ini.',
            'items.*.tarif_id.distinct' => 'Tarif yang sama dipilih lebih dari sekali.',
            'items.*.tarif_id.unique' => 'Tarif ini sudah ada di buku tarif unit yang dipilih.',
            'items.*.tarif_id.exists' => 'Tarif yang dipilih tidak ditemukan.',
            'items.*.status.required' => 'Status wajib diisi.',
            'items.*.status.in' => 'Status tidak valid.',
            'items.*.notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
