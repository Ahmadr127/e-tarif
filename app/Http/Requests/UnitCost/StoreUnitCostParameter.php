<?php

namespace App\Http\Requests\UnitCost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitCostParameter extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'component_id' => $this->filled('component_id') ? (int) $this->input('component_id') : null,
            'code' => trim((string) $this->input('code')),
            'variable' => trim((string) $this->input('variable')),
            'name' => trim((string) $this->input('name')),
            'unit' => $this->filled('unit') ? trim((string) $this->input('unit')) : null,
            'source_name' => $this->filled('source_name') ? trim((string) $this->input('source_name')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'component_id' => ['nullable', 'integer', 'exists:unit_cost_components,id'],
            'code' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
                Rule::unique('unit_cost_parameters', 'code'),
            ],
            'variable' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
                Rule::unique('unit_cost_parameters', 'variable'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'value_type' => ['required', 'in:decimal,integer,boolean,string'],
            'unit' => ['nullable', 'string', 'max:50'],
            'scope' => ['required', 'in:global,unit,period,unit_period'],
            'source_type' => ['required', 'in:global,manual,integration,derived'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'component_id.exists' => 'Komponen yang dipilih tidak ditemukan.',
            'code.required' => 'Kode parameter wajib diisi.',
            'code.unique' => 'Kode parameter sudah dipakai.',
            'code.regex' => 'Kode parameter hanya boleh berisi huruf, angka, dan garis bawah, serta diawali huruf atau garis bawah.',
            'variable.required' => 'Variable global wajib diisi.',
            'variable.unique' => 'Variable global sudah dipakai parameter lain.',
            'variable.regex' => 'Variable global hanya boleh berisi huruf, angka, dan garis bawah, serta diawali huruf atau garis bawah.',
            'name.required' => 'Nama parameter wajib diisi.',
            'value_type.required' => 'Tipe nilai wajib dipilih.',
            'scope.required' => 'Scope wajib dipilih.',
            'source_type.required' => 'Sumber data wajib dipilih.',
        ];
    }
}
