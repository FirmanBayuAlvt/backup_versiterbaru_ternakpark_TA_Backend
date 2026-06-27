<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Ambil ID pakan dari parameter route (jika sedang update)
        $feedId = $this->route('feed');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Unique hanya pada pakan yang aktif (is_active = true)
                Rule::unique('feeds', 'name')->where(function ($query) {
                    return $query->where('is_active', true);
                })->ignore($feedId),
            ],
            'category' => [
                'required',
                'string',
                Rule::in([
                    'silase',
                    'cf_jember',
                    'jagung_halus',
                    'konsentrat',
                    'hijauan',
                    'konsentrat_buatan',
                    'mineral',
                    'vitamin',
                ]),
            ],
            'current_stock' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'price_per_kg' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'unit' => [
                'nullable',
                'string',
                'max:10',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom error messages for validator failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pakan wajib diisi.',
            'name.unique' => 'Nama pakan sudah terdaftar pada pakan aktif.',
            'category.required' => 'Kategori pakan wajib dipilih.',
            'category.in' => 'Kategori pakan tidak valid.',
            'current_stock.numeric' => 'Stok harus berupa angka.',
            'current_stock.min' => 'Stok tidak boleh kurang dari 0.',
            'price_per_kg.numeric' => 'Harga harus berupa angka.',
            'price_per_kg.min' => 'Harga tidak boleh kurang dari 0.',
            'is_active.boolean' => 'Status harus bernilai benar atau salah.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Konversi nilai is_active dari string '1'/'0' menjadi boolean jika diperlukan
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}