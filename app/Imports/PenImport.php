<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    private int $successCount = 0;
    private int $rowIndex = 0;

    // Mapping kategori dari teks di CSV ke nilai ENUM database
    private array $categoryMapping = [
        'Kandang Fattening' => 'Fattening',
        'Fattening' => 'Fattening',
        'Kandang Kawin' => 'Kawin',
        'Kawin' => 'Kawin',
        'Kandang Prasapih' => 'Prasapih',
        'Prasapih' => 'Prasapih',
        'Kandang Melahirkan' => 'Melahirkan',
        'Melahirkan' => 'Melahirkan',
        'Kandang Menyusui' => 'Menyusui',
        'Menyusui' => 'Menyusui',
        'Kandang Karantina' => 'Karantina',
        'Karantina' => 'Karantina',
    ];

    public function headingRow(): int
    {
        return 1;
    }

    public function model(array $row): ?array
    {
        $this->rowIndex++;

        // Log raw row untuk debugging
        Log::info("PenImport - Processing row {$this->rowIndex}", ['row' => $row]);

        // Ambil nilai berdasarkan urutan kolom (karena header numerik atau asosiatif)
        $rowValues = array_values($row);
        
        $name     = trim($rowValues[0] ?? '');
        $code     = trim($rowValues[1] ?? '');
        $categoryRaw = trim($rowValues[2] ?? '');
        $capacity = (int) ($rowValues[3] ?? 0);
        $status   = trim($rowValues[4] ?? 'active');

        // Mapping kategori
        $category = $this->categoryMapping[$categoryRaw] ?? null;
        
        if ($category === null) {
            Log::warning("PenImport - Row {$this->rowIndex}: kategori tidak dikenal '{$categoryRaw}', skipping");
            return null;
        }

        Log::info("PenImport - Extracted values row {$this->rowIndex}", [
            'name' => $name,
            'code' => $code,
            'category_raw' => $categoryRaw,
            'category_mapped' => $category,
            'capacity' => $capacity,
            'status' => $status
        ]);

        // Skip baris header jika baris pertama berisi teks 'nama' atau 'name'
        if ($this->rowIndex === 1 && (strtolower($name) === 'nama' || strtolower($name) === 'name')) {
            Log::info("PenImport - Skipping header row");
            return null;
        }

        // Validasi
        if (empty($name)) {
            Log::warning("PenImport - Row {$this->rowIndex}: empty name");
            return null;
        }
        if (empty($category)) {
            Log::warning("PenImport - Row {$this->rowIndex}: empty category after mapping");
            return null;
        }
        if ($capacity <= 0) {
            Log::warning("PenImport - Row {$this->rowIndex}: invalid capacity", ['name' => $name, 'capacity' => $capacity]);
            return null;
        }

        $status = in_array($status, ['active', 'inactive']) ? $status : 'active';

        // Cek duplikat (case-insensitive)
        $exists = DB::table('pens')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->when(!empty($code), function ($query) use ($code) {
                return $query->orWhereRaw('LOWER(code) = ?', [strtolower($code)]);
            })
            ->exists();

        if ($exists) {
            Log::warning("PenImport - Row {$this->rowIndex}: duplicate name/code", ['name' => $name, 'code' => $code]);
            return null;
        }

        // Insert ke database
        try {
            DB::table('pens')->insert([
                'name'       => $name,
                'code'       => empty($code) ? null : $code,
                'category'   => $category,
                'capacity'   => $capacity,
                'status'     => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->successCount++;
            Log::info("PenImport - Row {$this->rowIndex}: SUCCESS", ['name' => $name]);
        } catch (\Exception $e) {
            Log::error("PenImport - Row {$this->rowIndex}: Insert failed", [
                'error' => $e->getMessage(),
                'name' => $name
            ]);
        }

        return null;
    }

    public function getRowCount(): int
    {
        return $this->successCount;
    }
}