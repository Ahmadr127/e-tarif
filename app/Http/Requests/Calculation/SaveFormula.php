<?php

namespace App\Http\Requests\Calculation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFormula extends FormRequest
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
            'code' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'component_id' => ['nullable', 'integer', 'exists:cost_components,id'],
            'expression' => ['required', 'string', 'max:2000'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_scope' => ['required', Rule::in(['global', 'unit', 'period', 'unit_period'])],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'source_note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'expression' => trim((string) $this->input('expression')),
            'result_unit' => $this->filled('result_unit') ? trim((string) $this->input('result_unit')) : null,
            'source_note' => $this->filled('source_note') ? trim((string) $this->input('source_note')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode formula wajib diisi.',
            'code.regex' => 'Kode formula hanya boleh berisi huruf, angka, dan garis bawah.',
            'name.required' => 'Nama formula wajib diisi.',
            'expression.required' => 'Ekspresi formula wajib diisi.',
            'expression.max' => 'Ekspresi formula maksimal 2000 karakter.',
            'result_scope.required' => 'Scope hasil wajib dipilih.',
            'effective_until.after_or_equal' => 'Masa berlaku akhir tidak boleh sebelum masa berlaku awal.',
        ];
    }
}
