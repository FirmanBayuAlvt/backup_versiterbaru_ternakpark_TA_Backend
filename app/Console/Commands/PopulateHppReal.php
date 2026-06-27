<?php

namespace App\Console\Commands;

use App\Models\Livestock;
use App\Models\HppDetail;
use App\Models\FeedingRecord;
use App\Models\Pen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateHppReal extends Command
{
    protected $signature = 'hpp:populate-real';
    protected $description = 'Isi HPP dengan data real (estimasi harga pembelian & alokasi pakan per kandang)';

    public function handle()
    {
        $this->info('Memulai populasi data HPP real...');

        // Step 1: Pastikan semua ternak memiliki purchase_price (estimasi jika kosong)
        $this->ensurePurchasePrices();

        // Step 2: Hitung alokasi biaya pakan per kandang ke setiap ternak
        $this->allocateFeedCosts();

        // Step 3: Hitung HPP final dan simpan ke hpp_details
        $this->calculateFinalHpp();

        $this->info('Selesai! HPP sekarang berisi data real.');
    }

    private function ensurePurchasePrices()
    {
        $this->info('Memastikan semua ternak memiliki harga pembelian...');

        $livestocks = Livestock::whereNull('purchase_price')->orWhere('purchase_price', 0)->get();
        $updated = 0;

        foreach ($livestocks as $livestock) {
            // Estimasi harga: berat awal (kg) * Rp 50.000 (harga pasaran domba)
            $estimatedPrice = $livestock->initial_weight * 50000;
            $livestock->purchase_price = $estimatedPrice;
            $livestock->saveQuietly(); // tanpa trigger agar tidak loop
            $updated++;
        }

        $this->info("{$updated} ternak telah diisi estimasi harga pembelian.");
    }

    private function allocateFeedCosts()
    {
        $this->info('Menghitung alokasi biaya pakan per kandang...');

        // Ambil semua feeding record yang memiliki pen_id (per kandang) dan dalam 1 tahun terakhir
        $feedingRecords = FeedingRecord::whereNotNull('pen_id')
            ->where('feeding_date', '>=', now()->subYear())
            ->with('feed', 'pen')
            ->get();

        $grouped = $feedingRecords->groupBy('pen_id');

        foreach ($grouped as $penId => $records) {
            $pen = Pen::find($penId);
            if (!$pen) continue;

            // Hitung total biaya pakan kandang ini
            $totalCost = 0;
            foreach ($records as $record) {
                $totalCost += $record->quantity_kg * ($record->feed->price_per_kg ?? 0);
            }

            // Dapatkan jumlah ternak yang pernah berada di kandang ini (aktif selama periode)
            $livestockIds = $records->pluck('livestock_id')->filter()->unique()->values();
            if ($livestockIds->isEmpty()) {
                // Jika tidak ada livestock_id yang tercatat, ambil semua ternak di kandang tersebut (status aktif)
                $livestocksInPen = Livestock::where('pen_id', $penId)->where('status', true)->get();
                $count = $livestocksInPen->count();
                if ($count == 0) continue;
                $perAnimalCost = $totalCost / $count;
                // Alokasikan ke setiap ternak di kandang
                foreach ($livestocksInPen as $livestock) {
                    $this->addFeedCostToLivestock($livestock->id, $perAnimalCost);
                }
            } else {
                // Ada livestock_id spesifik, alokasikan langsung ke masing-masing
                $perAnimalCost = $totalCost / $livestockIds->count();
                foreach ($livestockIds as $livestockId) {
                    $this->addFeedCostToLivestock($livestockId, $perAnimalCost);
                }
            }
        }

        $this->info('Alokasi biaya pakan selesai.');
    }

    private function addFeedCostToLivestock($livestockId, $cost)
    {
        // Simpan ke temporary storage (misal session atau kita langsung update nanti di final step)
        // Agar tidak ribet, kita simpan di atribut dinamis atau kita kumpulkan di array
        // Untuk kemudahan, kita gunakan cache sementara.
        $key = "feed_cost_{$livestockId}";
        $current = cache($key, 0);
        cache([$key => $current + $cost], now()->addHour());
    }

    private function calculateFinalHpp()
    {
        $this->info('Menghitung HPP final dan menyimpan ke database...');

        $livestocks = Livestock::all();
        $created = 0;
        $updated = 0;

        foreach ($livestocks as $livestock) {
            $purchaseCost = $livestock->purchase_price ?? 0;
            $feedCost = cache("feed_cost_{$livestock->id}", 0);
            $operationalCost = 0; // bisa dikembangkan

            $hpp = HppDetail::where('livestock_id', $livestock->id)->first();
            if ($hpp) {
                $hpp->update([
                    'purchase_cost' => $purchaseCost,
                    'feed_cost' => $feedCost,
                    'operational_cost' => $operationalCost,
                ]);
                $updated++;
            } else {
                HppDetail::create([
                    'livestock_id' => $livestock->id,
                    'purchase_cost' => $purchaseCost,
                    'feed_cost' => $feedCost,
                    'operational_cost' => $operationalCost,
                ]);
                $created++;
            }

            // Hapus cache
            cache()->forget("feed_cost_{$livestock->id}");
        }

        $this->info("HPP tersimpan: {$created} baru, {$updated} diperbarui.");
    }
}