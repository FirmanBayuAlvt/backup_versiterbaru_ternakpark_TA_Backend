<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordWeightRequest extends FormRequest
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
        return [
            // Berat badan harus diisi, berupa angka, lebih besar dari 0 (gt:0), dan maksimal 300 kg
            'weight_kg' => 'required|numeric|gt:0|max:300',
            // Tanggal pencatatan harus diisi, format tanggal valid, dan tidak boleh melebihi hari ini
            'record_date' => 'required|date|before_or_equal:today',
            // Catatan bersifat opsional, maksimal 500 karakter
            'notes' => 'nullable|string|max:500',
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
            'weight_kg.required' => 'Kolom berat badan wajib diisi.',
            'weight_kg.numeric' => 'Berat badan harus berupa angka.',
            'weight_kg.gt' => 'Berat badan harus lebih dari 0 kg.',
            'weight_kg.max' => 'Berat badan tidak boleh melebihi 300 kg.',
            'record_date.required' => 'Kolom tanggal pencatatan wajib diisi.',
            'record_date.date' => 'Format tanggal tidak valid.',
            'record_date.before_or_equal' => 'Tanggal pencatatan tidak boleh melebihi hari ini.',
            'notes.max' => 'Catatan tidak boleh melebihi 500 karakter.',
        ];
    }
}