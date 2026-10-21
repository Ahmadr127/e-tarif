<?php

namespace App\Http\Requests\Calculation;

use Illuminate\Foundation\Http\FormRequest;

class SaveVariableValues extends FormRequest
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
        return [
            'unit_code' => ['nullable', 'string', 'max:80', 'exists:organization_units,code'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('unit_code')) {
            $this->merge([
                'unit_code' => mb_strtoupper(trim((string) $this->input('unit_code'))),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_code.exists' => 'Unit organisasi tidak ditemukan.',
            'period_year.required' => 'Tahun periode wajib diisi.',
            'period_year.between' => 'Tahun periode harus berada pada rentang 2000-2100.',
            'values.array' => 'Format nilai variabel tidak valid.',
            'notes.*.max' => 'Catatan nilai maksimal 500 karakter.',
        ];
    }
}
