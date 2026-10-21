<?php

namespace App\Http\Requests\Calculation;

use Illuminate\Foundation\Http\FormRequest;

class Store extends FormRequest
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
            'unit_code' => ['required', 'string', 'max:80', 'exists:organization_units,code'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'inputs' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('unit_code')) {
            $this->merge([
                'unit_code' => mb_strtoupper(trim((string) $this->input('unit_code'))),
            ]);
        }

        $inputs = $this->input('inputs');

        if (! is_array($inputs)) {
            return;
        }

        foreach ($inputs as $code => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                unset($inputs[$code]);

                continue;
            }

            $inputs[$code] = $value;
        }

        $this->merge(['inputs' => $inputs]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_code.required' => 'Unit organisasi wajib dipilih.',
            'unit_code.exists' => 'Unit organisasi yang dipilih tidak ditemukan.',
            'period_year.required' => 'Tahun periode wajib diisi.',
            'period_year.between' => 'Tahun periode harus berada pada rentang 2000-2100.',
            'inputs.array' => 'Format input variabel tidak valid.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
