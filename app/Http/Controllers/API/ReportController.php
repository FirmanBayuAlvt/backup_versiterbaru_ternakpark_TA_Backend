<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Livestock;
use App\Models\Pen;
use App\Models\Feed;
use App\Models\WeightRecord;
use App\Models\FeedingRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Get summary data for reports.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(): JsonResponse
    {
        try {
            $totalLivestocks = Livestock::count();
            $totalPens = Pen::count();
            $totalFeedTypes = Feed::count();
            $totalFeedStock = Feed::sum('current_stock');

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_livestocks'     => $totalLivestocks,
                    'total_pens'           => $totalPens,
                    'total_feed_types'     => $totalFeedTypes,
                    'total_feed_stock_kg'  => round($totalFeedStock, 2),
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Report summary error: ' . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance data (ADG, FCR, mortality, occupancy).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function performance(): JsonResponse
    {
        try {
            // Subquery untuk menghitung selisih berat dan selisih hari per record
            $subquery = DB::table('weight_records as wr')
                ->select(
                    'wr.livestock_id',
                    'wr.record_date',
                    'wr.weight_kg',
                    DB::raw('LAG(wr.weight_kg) OVER (PARTITION BY wr.livestock_id ORDER BY wr.record_date) as prev_weight'),
                    DB::raw('DATEDIFF(wr.record_date, LAG(wr.record_date) OVER (PARTITION BY wr.livestock_id ORDER BY wr.record_date)) as days_diff')
                );

            // Query utama untuk menghitung rata-rata ADG per bulan
            $adgData = DB::table(DB::raw('(' . $subquery->toSql() . ') as sub'))
                ->mergeBindings($subquery)
                ->select(
                    DB::raw('DATE_FORMAT(record_date, "%Y-%m") as month'),
                    DB::raw('AVG( (weight_kg - prev_weight) / days_diff ) as avg_adg')
                )
                ->whereNotNull('prev_weight')
                ->where('days_diff', '>', 0)
                ->groupBy('month')
                ->orderBy('month', 'asc')
                ->get();

            $chartLabels = [];
            $chartValues = [];
            foreach ($adgData as $row) {
                $chartLabels[] = $row->month;
                $chartValues[] = round($row->avg_adg, 3);
            }

            // Rata-rata ADG keseluruhan
            $livestocks = Livestock::all();
            $totalAdg = 0;
            foreach ($livestocks as $livestock) {
                $totalAdg += $livestock->average_daily_gain;
            }
            $count = $livestocks->count();
            $avgAdg = $count > 0 ? round($totalAdg / $count, 3) : 0;

            // FCR (Feed Conversion Ratio)
            $totalFeed = FeedingRecord::sum('quantity_kg');
            $totalWeightGain = 0;
            foreach ($livestocks as $livestock) {
                $gain = $livestock->current_weight - $livestock->initial_weight;
                if ($gain > 0) {
                    $totalWeightGain += $gain;
                }
            }
            $fcr = $totalWeightGain > 0 ? round($totalFeed / $totalWeightGain, 2) : 0;

            // Mortalitas
            $dead = Livestock::where('status', false)->count();
            $total = Livestock::count();
            $mortalityRate = $total > 0 ? round(($dead / $total) * 100, 2) : 0;

            // Okupansi
            $totalCapacity = Pen::sum('capacity');
            $totalOccupancy = Livestock::where('status', true)->count();
            $occupancyRate = $totalCapacity > 0 ? round(($totalOccupancy / $totalCapacity) * 100, 2) : 0;

            // Detail per bulan untuk tabel
            $detail = [];
            foreach ($adgData as $row) {
                $detail[] = [
                    'bulan'      => $row->month,
                    'adg'        => round($row->avg_adg, 3),
                    'fcr'        => 0,
                    'mortalitas' => 0,
                    'okupansi'   => 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'average_daily_gain'     => $avgAdg,
                    'feed_conversion_ratio'  => $fcr,
                    'mortality_rate'         => $mortalityRate,
                    'occupancy_rate'         => $occupancyRate,
                    'chart_labels'           => $chartLabels,
                    'chart_adg_values'       => $chartValues,
                    'detail'                 => $detail,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Report performance error: ' . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Get growth data (average weight for last 4 weeks).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function growth(): JsonResponse
    {
        try {
            // Ambil data 4 minggu terakhir dari weight_records
            $startDate = now()->subWeeks(4)->startOfWeek();
            $endDate = now();

            $weights = WeightRecord::whereBetween('record_date', [$startDate, $endDate])
                ->orderBy('record_date', 'asc')
                ->get();

            if ($weights->isNotEmpty()) {
                // Kelompokkan per minggu menggunakan awal minggu sebagai key
                $grouped = [];
                foreach ($weights as $weight) {
                    $weekStart = $weight->record_date->copy()->startOfWeek();
                    $key = $weekStart->format('Y-m-d');
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = ['total' => 0, 'count' => 0];
                    }
                    $grouped[$key]['total'] += $weight->weight_kg;
                    $grouped[$key]['count']++;
                }

                // Ambil maksimal 4 minggu terakhir
                $weeks = array_keys($grouped);
                sort($weeks);
                $last4Weeks = array_slice($weeks, -4);

                $labels = [];
                $data = [];
                foreach ($last4Weeks as $weekStart) {
                    $avg = $grouped[$weekStart]['total'] / $grouped[$weekStart]['count'];
                    $date = \Carbon\Carbon::parse($weekStart);
                    $labels[] = "Minggu " . $date->format('j M');
                    $data[] = round($avg, 2);
                }

                // Jika kurang dari 4 minggu, padding dengan data terakhir
                while (count($labels) < 4) {
                    array_unshift($labels, "Minggu " . (count($labels) + 1));
                    array_unshift($data, $data[0] ?? 0);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => $labels,
                        'data'   => $data,
                    ],
                ]);
            }

            // FALLBACK: tidak ada weight records, gunakan initial_weight dan current_weight
            $livestocks = Livestock::all();
            $livestockCount = $livestocks->count();

            if ($livestockCount === 0) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
                        'data'   => [0, 0, 0, 0],
                    ],
                ]);
            }

            $totalInitial = 0;
            $totalCurrent = 0;
            foreach ($livestocks as $livestock) {
                $totalInitial += $livestock->initial_weight;
                $totalCurrent += $livestock->current_weight;
            }

            $avgInitial = $totalInitial / $livestockCount;
            $avgCurrent = $totalCurrent / $livestockCount;

            // Interpolasi linear dari minggu 1 ke minggu 4
            if ($avgInitial == $avgCurrent) {
                $data = [$avgInitial, $avgInitial, $avgInitial, $avgInitial];
            } else {
                $step = ($avgCurrent - $avgInitial) / 3;
                $data = [
                    round($avgInitial, 2),
                    round($avgInitial + $step, 2),
                    round($avgInitial + ($step * 2), 2),
                    round($avgCurrent, 2),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
                    'data'   => $data,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Growth report error: ' . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data pertumbuhan: ' . $exception->getMessage(),
            ], 500);
        }
    }
}