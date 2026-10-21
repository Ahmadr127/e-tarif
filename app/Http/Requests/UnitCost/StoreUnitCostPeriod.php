<?php

namespace App\Http\Requests\UnitCost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitCostPeriod extends FormRequest
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
            'code' => ['required', 'string', 'max:80', Rule::unique('unit_cost_periods', 'code')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('unit_cost_periods', 'name')
                    ->where(fn ($query) => $query->where('period_year', (int) $this->input('period_year'))),
            ],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'closed'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'status' => $this->input('status') ?: 'draft',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode periode wajib diisi.',
            'code.unique' => 'Kode periode sudah dipakai.',
            'name.required' => 'Nama periode wajib diisi.',
            'name.unique' => 'Nama periode sudah dipakai pada tahun yang sama.',
            'period_year.required' => 'Tahun periode wajib diisi.',
            'period_year.between' => 'Tahun periode harus berada pada rentang 2000-2100.',
            'status.in' => 'Status periode tidak dikenal.',
        ];
    }
}
