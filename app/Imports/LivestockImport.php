<?php

namespace App\Imports;

use App\Models\Livestock;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class LivestockImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    private int $successCount = 0;
    private int $rowIndex = 0;

    public function headingRow(): int
    {
        return 1;
    }

    public function model(array $row): ?array
    {
        $this->rowIndex++;

        Log::info("LivestockImport - Baris {$this->rowIndex} mentah", $row);

        // Ambil nilai dengan berbagai kemungkinan nama kolom (case-insensitive)
        $earTag        = $this->getValueFromRow($row, ['ear_tag', 'Ear Tag', 'ear tag', 'EarTag']);
        $breedType     = $this->getValueFromRow($row, ['breed_type', 'Breed Type', 'breed type', 'jenis']);
        $gender        = $this->getValueFromRow($row, ['gender', 'Gender', 'jenis_kelamin']);
        $birthDateRaw  = $this->getValueFromRow($row, ['birth_date', 'Birth Date', 'birth date', 'tanggal_lahir']);
        $initialWeightRaw = $this->getValueFromRow($row, ['initial_weight', 'Initial Weight', 'initial weight', 'berat_awal']);
        
        // Kolom opsional (tidak wajib)
        $healthStatus  = $this->getValueFromRow($row, ['health_status', 'Health Status', 'health status', 'status_kesehatan']);
        $notes         = $this->getValueFromRow($row, ['notes', 'Notes', 'catatan']);
        $condition     = $this->getValueFromRow($row, ['condition', 'Condition', 'kondisi']);
        $dateInRaw     = $this->getValueFromRow($row, ['date_in', 'Date In', 'date in', 'tanggal_masuk']);
        $fatherEarTag  = $this->getValueFromRow($row, ['father_ear_tag', 'Father Ear Tag', 'father ear tag', 'induk_jantan']);
        $motherEarTag  = $this->getValueFromRow($row, ['mother_ear_tag', 'Mother Ear Tag', 'mother ear tag', 'induk_betina']);
        $purchasePriceRaw = $this->getValueFromRow($row, ['purchase_price', 'Purchase Price', 'purchase price', 'harga_beli']);

        // Validasi wajib (tanpa kandang)
        if (empty($earTag)) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Ear tag kosong, dilewati.");
            return null;
        }
        if (empty($breedType)) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Breed type kosong, dilewati.", ['ear_tag' => $earTag]);
            return null;
        }
        if (empty($gender)) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Gender kosong, dilewati.", ['ear_tag' => $earTag]);
            return null;
        }
        if (empty($birthDateRaw)) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Birth date kosong, dilewati.", ['ear_tag' => $earTag]);
            return null;
        }
        if (empty($initialWeightRaw)) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Initial weight kosong, dilewati.", ['ear_tag' => $earTag]);
            return null;
        }

        // Parse tanggal lahir
        $birthDate = $this->parseDate($birthDateRaw);
        if ($birthDate === null) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Format birth date tidak valid: '{$birthDateRaw}'", ['ear_tag' => $earTag]);
            return null;
        }

        // Parse tanggal masuk (opsional)
        $dateIn = $this->parseDate($dateInRaw);

        // Konversi initial_weight
        $initialWeight = (float) $initialWeightRaw;
        if ($initialWeight <= 0) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Initial weight harus > 0, nilai: {$initialWeight}", ['ear_tag' => $earTag]);
            return null;
        }

        // Normalisasi gender
        $gender = strtolower(trim($gender));
        if ($gender !== 'male' && $gender !== 'female') {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Gender tidak valid: '{$gender}'", ['ear_tag' => $earTag]);
            return null;
        }

        // Normalisasi health_status (default 'good')
        $healthStatus = strtolower(trim($healthStatus));
        $allowedHealth = ['excellent', 'good', 'fair', 'poor'];
        if (!in_array($healthStatus, $allowedHealth)) {
            $healthStatus = 'good';
        }

        // Konversi purchase_price
        $purchasePrice = null;
        if (is_numeric($purchasePriceRaw)) {
            $purchasePrice = (float) $purchasePriceRaw;
        }

        // Cek duplikat ear_tag (case-insensitive)
        $exists = Livestock::whereRaw('LOWER(ear_tag) = ?', [strtolower($earTag)])->exists();
        if ($exists) {
            Log::warning("LivestockImport - Baris {$this->rowIndex}: Ear tag '{$earTag}' sudah ada, dilewati.");
            return null;
        }

        // Insert data (pen_id dibiarkan NULL)
        try {
            $livestock = new Livestock([
                'ear_tag'        => $earTag,
                'breed_type'     => $breedType,
                'gender'         => $gender,
                'birth_date'     => $birthDate,
                'initial_weight' => $initialWeight,
                'health_status'  => $healthStatus,
                'notes'          => $notes,
                'pen_id'         => null,   // ← TIDAK ADA KANDANG, diisi manual nanti
                'condition'      => $condition,
                'date_in'        => $dateIn,
                'father_ear_tag' => empty($fatherEarTag) ? null : $fatherEarTag,
                'mother_ear_tag' => empty($motherEarTag) ? null : $motherEarTag,
                'purchase_price' => $purchasePrice,
                'status'         => true,
            ]);
            $livestock->save();

            $this->successCount++;
            Log::info("LivestockImport - Baris {$this->rowIndex}: BERHASIL diimpor (tanpa kandang)", ['ear_tag' => $earTag]);
        } catch (\Exception $e) {
            Log::error("LivestockImport - Baris {$this->rowIndex}: Gagal insert database", [
                'error_message' => $e->getMessage(),
                'ear_tag' => $earTag
            ]);
        }

        return null;
    }

    private function getValueFromRow(array $row, array $possibleKeys): string
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && !is_null($row[$key]) && $row[$key] !== '') {
                return trim((string) $row[$key]);
            }
        }
        return '';
    }

    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getRowCount(): int
    {
        return $this->successCount;
    }
}