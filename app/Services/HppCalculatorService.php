<?php

namespace App\Services;

use App\Models\Livestock;
use App\Models\HppDetail;
use App\Models\FeedingRecord;
use Illuminate\Support\Facades\DB;

class HppCalculatorService
{
    /**
     * Hitung Harga Pokok Produksi (HPP) untuk satu ekor ternak berdasarkan ID-nya.
     *
     * @param int $livestockId
     * @return void
     */
    public function calculateForLivestock($livestockId)
    {
        $livestock = Livestock::find($livestockId);
        if (!$livestock) {
            return;
        }

        // Menghitung total biaya pakan dari feeding records yang terkait dengan ternak ini
        $feedCost = FeedingRecord::where('livestock_id', $livestockId)
            ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
            ->sum(DB::raw('feeding_records.quantity_kg * feeds.price_per_kg'));

        // Mengambil harga pembelian ternak dari kolom purchase_price (jika ada)
        $purchaseCost = $livestock->purchase_price ?? 0;

        // Biaya operasional (masih 0, dapat dikembangkan di masa mendatang)
        $operationalCost = 0;

        // Menyimpan atau memperbarui data HPP untuk ternak tersebut
        HppDetail::updateOrCreate(
            ['livestock_id' => $livestockId],
            [
                'purchase_cost'   => $purchaseCost,
                'feed_cost'       => $feedCost,
                'operational_cost'=> $operationalCost,
            ]
        );
    }

    /**
     * Menghitung HPP untuk semua ternak yang ada di database.
     * Berguna untuk seeder atau command inisialisasi awal.
     *
     * @return void
     */
    public function calculateAll()
    {
        $livestocks = Livestock::all();
        foreach ($livestocks as $livestock) {
            $this->calculateForLivestock($livestock->id);
        }
    }
}