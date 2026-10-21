<?php

namespace App\Http\Requests\UnitCost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitCostComponent extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'category' => $this->filled('category') ? trim((string) $this->input('category')) : null,
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
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/',
                Rule::unique('unit_cost_components', 'code')->ignore($this->route('component')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode komponen wajib diisi.',
            'code.unique' => 'Kode komponen sudah dipakai.',
            'code.regex' => 'Kode komponen hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'name.required' => 'Nama komponen wajib diisi.',
        ];
    }
}
