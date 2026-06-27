<?php

namespace App\Console\Commands;

use App\Models\Livestock;
use Illuminate\Console\Command;

class ExportTrainingData extends Command
{
    protected $signature = 'ml:export {--output=storage/training_data.csv}';
    protected $description = 'Ekspor data untuk training model TensorFlow';

    public function handle()
    {
        $livestocks = Livestock::with(['weightRecords', 'feedingRecords.feed', 'pen'])->get();

        $data = [];
        foreach ($livestocks as $l) {
            // Berat awal (record pertama)
            $firstWeight = $l->weightRecords->sortBy('record_date')->first();
            $initialWeight = $firstWeight ? $firstWeight->weight_kg : $l->initial_weight;

            // Berat akhir (record terakhir)
            $lastWeight = $l->weightRecords->sortByDesc('record_date')->first();
            $finalWeight = $lastWeight ? $lastWeight->weight_kg : $initialWeight;

            // Konsumsi pakan per kategori
            $feedSilase = $l->feedingRecords->where('feed.category', 'silase')->sum('quantity_kg');
            $feedConcentrate = $l->feedingRecords->where('feed.category', 'konsentrat')->sum('quantity_kg');

            $data[] = [
                'initial_weight'   => $initialWeight,
                'age_days'         => $l->age_days,
                'feed_silase'      => $feedSilase,
                'feed_concentrate' => $feedConcentrate,
                'gender'           => $l->gender,
                'pen_category'     => $l->pen->category ?? 'unknown',
                'final_weight'     => $finalWeight,
            ];
        }

        if (empty($data)) {
            $this->error('Tidak ada data untuk diekspor.');
            return;
        }

        $output = $this->option('output');
        $file = fopen($output, 'w');
        fputcsv($file, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        $this->info("Data berhasil diekspor ke {$output}");
    }
}
