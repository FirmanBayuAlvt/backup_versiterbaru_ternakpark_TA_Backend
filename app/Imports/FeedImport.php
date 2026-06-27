<?php

namespace App\Imports;

use App\Models\Feed;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Validation\Rule;

class FeedImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    /**
     * Jumlah baris yang berhasil diproses.
     *
     * @var int
     */
    private $successfulRows = 0;

    /**
     * @param array $row
     * @return \App\Models\Feed|null
     */
    public function model(array $row)
    {
        $this->successfulRows++;

        // Gunakan kolom dengan nama Indonesia (sesuai template)
        return new Feed([
            'name'          => $row['nama'],
            'category'      => $row['kategori'],
            'current_stock' => $row['stok_awal'],
            'price_per_kg'  => $row['harga_per_kg'] ?? null,
            'unit'          => $row['satuan'] ?? 'kg',
            'is_active'     => filter_var($row['aktif'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Aturan validasi untuk setiap baris.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'nama'          => 'required|string|max:100',
            'kategori'      => [
                'required',
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
            'stok_awal'     => 'required|numeric|min:0',
            'harga_per_kg'  => 'nullable|numeric|min:0',
            'satuan'        => 'nullable|string|max:10',
            'aktif'         => 'nullable|boolean',
        ];
    }

    /**
     * Pesan error kustom untuk setiap aturan validasi.
     *
     * @return array
     */
    public function customValidationMessages(): array
    {
        return [
            'nama.required'          => 'Nama pakan wajib diisi.',
            'nama.max'               => 'Nama pakan maksimal 100 karakter.',
            'kategori.required'      => 'Kategori pakan wajib dipilih.',
            'kategori.in'            => 'Kategori pakan tidak valid.',
            'stok_awal.required'     => 'Stok awal wajib diisi.',
            'stok_awal.numeric'      => 'Stok awal harus berupa angka.',
            'stok_awal.min'          => 'Stok awal tidak boleh kurang dari 0.',
            'harga_per_kg.numeric'   => 'Harga per kg harus berupa angka.',
            'harga_per_kg.min'       => 'Harga per kg tidak boleh kurang dari 0.',
            'satuan.max'             => 'Satuan maksimal 10 karakter.',
            'aktif.boolean'          => 'Nilai aktif harus bernilai true/false atau 1/0.',
        ];
    }

    /**
     * Mendapatkan jumlah baris yang berhasil diimpor.
     *
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->successfulRows;
    }
}
