<?php

namespace App\Console\Commands;

use App\Models\Livestock;
use App\Models\HppDetail;
use App\Models\FeedingRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateHpp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hpp:calculate {--force : Recalculate all even if already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghitung Harga Pokok Produksi (HPP) dari data real yang sudah ada';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai perhitungan HPP...');

        $livestocks = Livestock::all();
        $total = $livestocks->count();
        $updated = 0;
        $created = 0;

        if ($total === 0) {
            $this->warn('Tidak ada data ternak. HPP tidak dapat dihitung.');
            return;
        }

        $bar = $this->output->createProgressBar($total);

        foreach ($livestocks as $livestock) {
            // Hitung total biaya pakan dari feeding records
            $feedCost = FeedingRecord::where('livestock_id', $livestock->id)
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->sum(DB::raw('feeding_records.quantity_kg * feeds.price_per_kg'));

            // Purchase cost (kolom purchase_price di livestocks)
            $purchaseCost = $livestock->purchase_price ?? 0;

            // Operational cost (default 0, bisa dikembangkan nanti)
            $operationalCost = 0;

            // Cek apakah sudah ada record HPP
            $hpp = HppDetail::where('livestock_id', $livestock->id)->first();

            if ($hpp) {
                if ($this->option('force') || $hpp->feed_cost != $feedCost || $hpp->purchase_cost != $purchaseCost) {
                    $hpp->update([
                        'purchase_cost' => $purchaseCost,
                        'feed_cost' => $feedCost,
                        'operational_cost' => $operationalCost,
                    ]);
                    $updated++;
                }
            } else {
                HppDetail::create([
                    'livestock_id' => $livestock->id,
                    'purchase_cost' => $purchaseCost,
                    'feed_cost' => $feedCost,
                    'operational_cost' => $operationalCost,
                ]);
                $created++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai. Created: {$created}, Updated: {$updated}, Total: {$total}");
    }
}
