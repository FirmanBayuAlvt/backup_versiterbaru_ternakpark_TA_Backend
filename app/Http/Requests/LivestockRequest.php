<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LivestockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');
        $livestockId = $this->route('livestock');

        return [
            // Identitas Utama
            'ear_tag' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('livestocks', 'ear_tag')->ignore($livestockId),
            ],
            'breed_type' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:100',
            ],
            'gender' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                Rule::in(['male', 'female']),
            ],

            // Tanggal & Berat
            'birth_date' => [
                $isUpdate ? 'sometimes' : 'required',
                'date',
                'before:today',
            ],
            'initial_weight' => [
                $isUpdate ? 'sometimes' : 'required',
                'numeric',
                'min:0',
                'max:200',
            ],

            // Status (boolean)
            'status' => [
                'nullable',
                'boolean',
            ],

            // Kesehatan & Catatan
            'health_status' => 'nullable|string|in:excellent,good,fair,poor',
            'notes' => 'nullable|string|max:1000',

            // Relasi & File
            'pen_id' => 'nullable|exists:pens,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB

            // Field Tambahan (Migrasi)
            'condition' => 'nullable|string|max:100',
            'date_in' => 'nullable|date',
            'day_on_farm' => 'nullable|integer|min:0',
            'reproductive_age' => 'nullable|string|max:50',
            'date_of_death_or_sold' => 'nullable|date',
            'father_ear_tag' => 'nullable|string|max:50',
            'mother_ear_tag' => 'nullable|string|max:50',

            // Harga Pembelian (HPP)
            'purchase_price' => 'nullable|numeric|min:0',

            // Field khusus frontend (logbook event), tidak disimpan ke tabel livestocks
            'logbook_event' => 'nullable|string|max:50',
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
            'ear_tag.required' => 'Ear tag harus diisi.',
            'ear_tag.unique' => 'Ear tag sudah digunakan.',
            'breed_type.required' => 'Jenis ternak harus dipilih.',
            'gender.required' => 'Jenis kelamin harus dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid (male/female).',
            'birth_date.required' => 'Tanggal lahir harus diisi.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'initial_weight.required' => 'Berat awal harus diisi.',
            'initial_weight.numeric' => 'Berat awal harus berupa angka.',
            'initial_weight.min' => 'Berat awal minimal 0 kg.',
            'initial_weight.max' => 'Berat awal maksimal 200 kg.',
            'status.boolean' => 'Status harus bernilai true atau false.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar harus JPEG, PNG, atau JPG.',
            'image.max' => 'Ukuran gambar maksimal 5MB.',
            'date_in.date' => 'Format tanggal masuk tidak valid (YYYY-MM-DD).',
            'date_of_death_or_sold.date' => 'Format tanggal kematian/terjual tidak valid (YYYY-MM-DD).',
            'health_status.in' => 'Status kesehatan harus salah satu dari: excellent, good, fair, poor.',
            'purchase_price.numeric' => 'Harga pembelian harus berupa angka.',
            'purchase_price.min' => 'Harga pembelian tidak boleh kurang dari 0.',
        ];
    }
}