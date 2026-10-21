<?php

namespace App\Http\Requests\Calculation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVariable extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $variable = $this->route('variable');
        $variableId = is_object($variable) ? $variable->getKey() : null;

        return [
            'code' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
                Rule::unique('calculation_variables', 'code')->ignore($variableId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'component_id' => ['nullable', 'integer', 'exists:cost_components,id'],
            'value_type' => ['required', Rule::in(['decimal', 'integer', 'boolean'])],
            'unit' => ['nullable', 'string', 'max:50'],
            'scope' => ['required', Rule::in(['global', 'unit', 'period', 'unit_period'])],
            'source_type' => ['required', Rule::in(['global', 'manual', 'integration', 'derived'])],
            'default_value' => ['nullable', 'numeric'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'unit' => $this->filled('unit') ? trim((string) $this->input('unit')) : null,
            'source_name' => $this->filled('source_name') ? trim((string) $this->input('source_name')) : null,
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode variabel wajib diisi.',
            'code.regex' => 'Kode variabel hanya boleh berisi huruf, angka, dan garis bawah.',
            'code.unique' => 'Kode variabel sudah dipakai.',
            'name.required' => 'Nama variabel wajib diisi.',
            'value_type.required' => 'Tipe data wajib dipilih.',
            'scope.required' => 'Scope variabel wajib dipilih.',
            'source_type.required' => 'Tipe input wajib dipilih.',
            'default_value.numeric' => 'Nilai default harus berupa angka.',
            'component_id.exists' => 'Komponen biaya tidak ditemukan.',
        ];
    }
}
