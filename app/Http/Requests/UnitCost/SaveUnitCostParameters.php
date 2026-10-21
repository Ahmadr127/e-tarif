<?php

namespace App\Http\Requests\UnitCost;

use Illuminate\Foundation\Http\FormRequest;

class SaveUnitCostParameters extends FormRequest
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
            'entries' => ['required', 'array'],
            'entries.*.input_type' => ['required', 'in:value,formula'],
            'entries.*.input_value' => ['nullable', 'numeric'],
            'entries.*.formula_text' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.required' => 'Tidak ada parameter yang dikirim.',
            'entries.*.input_type.required' => 'Metode input wajib dipilih.',
            'entries.*.input_type.in' => 'Metode input harus Input Nilai atau Input Rumus.',
            'entries.*.input_value.numeric' => 'Nilai input harus berupa angka.',
            'entries.*.formula_text.max' => 'Rumus maksimal 2000 karakter.',
        ];
    }
}
