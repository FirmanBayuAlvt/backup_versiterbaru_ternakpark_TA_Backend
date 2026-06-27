<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\LivestockResource;
use App\Models\Livestock;
use App\Models\Logbook;
use App\Models\FeedingRecord;
use App\Models\Feed;
use App\Models\Pen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProgramController extends Controller
{
    /**
     * Data untuk program Fattening (penggemukan) – versi sederhana.
     */
    public function fattening(): JsonResponse
    {
        try {
            $livestocks = Livestock::with('pen')
                ->whereHas('pen', function ($query) {
                    $query->where('category', 'like', '%Fattening%');
                })
                ->get();

            $total      = $livestocks->count();
            $avgWeight  = $livestocks->avg('current_weight');
            $avgAdg     = $livestocks->avg('average_daily_gain');

            return response()->json([
                'success' => true,
                'data'    => [
                    'total'      => $total,
                    'avg_weight' => round($avgWeight, 2),
                    'avg_adg'    => round($avgAdg, 3),
                    'livestocks' => LivestockResource::collection($livestocks),
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Fattening API error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Data lengkap untuk halaman Fattening (statistik + tabel detail).
     */
    public function fatteningDetailed(): JsonResponse
    {
        try {
            $livestocks = Livestock::with('pen')
                ->whereHas('pen', function ($query) {
                    $query->where('category', 'like', '%Fattening%');
                })
                ->get();

            $total          = $livestocks->count();
            $totalWeight    = $livestocks->sum('current_weight');
            $avgAdg         = $livestocks->avg('average_daily_gain');
            $maleCount      = $livestocks->where('gender', 'male')->count();
            $femaleCount    = $livestocks->where('gender', 'female')->count();

            $adgHigh   = 0;
            $adgMedium = 0;
            $adgLow    = 0;
            foreach ($livestocks as $livestock) {
                $adg = $livestock->average_daily_gain;
                if ($adg > 0.1) {
                    $adgHigh++;
                } elseif ($adg >= 0 && $adg <= 0.1) {
                    $adgMedium++;
                } elseif ($adg < 0) {
                    $adgLow++;
                }
            }

            $getSubKategori = function ($ageDays) {
                if ($ageDays <= 30) {
                    return 'Cempe (0-1 Bulan)';
                }
                if ($ageDays <= 90) {
                    return 'Cempe Prasapih (1-3 Bulan)';
                }
                if ($ageDays <= 180) {
                    return 'Lepas Sapih (3-6 Bulan)';
                }
                if ($ageDays <= 365) {
                    return 'Bakalan (6-12 Bulan)';
                }
                return 'Dewasa (>12 Bulan)';
            };

            $males = [];
            foreach ($livestocks as $livestock) {
                if ($livestock->gender === 'male') {
                    $males[] = [
                        'ear_tag'            => $livestock->ear_tag,
                        'breed_type'         => $livestock->breed_type,
                        'day_on_farm'        => $livestock->day_on_farm,
                        'sub_kategori'       => $getSubKategori($livestock->age_days),
                        'current_weight'     => $livestock->current_weight,
                        'average_daily_gain' => $livestock->average_daily_gain,
                    ];
                }
            }

            $females = [];
            foreach ($livestocks as $livestock) {
                if ($livestock->gender === 'female') {
                    $females[] = [
                        'ear_tag'            => $livestock->ear_tag,
                        'breed_type'         => $livestock->breed_type,
                        'day_on_farm'        => $livestock->day_on_farm,
                        'sub_kategori'       => $getSubKategori($livestock->age_days),
                        'current_weight'     => $livestock->current_weight,
                        'average_daily_gain' => $livestock->average_daily_gain,
                    ];
                }
            }

            $adgNegative = [];
            foreach ($livestocks as $livestock) {
                if ($livestock->average_daily_gain < 0) {
                    $adgNegative[] = [
                        'ear_tag'            => $livestock->ear_tag,
                        'day_on_farm'        => $livestock->day_on_farm,
                        'pen_name'           => optional($livestock->pen)->name ?? '-',
                        'current_weight'     => $livestock->current_weight,
                        'average_daily_gain' => $livestock->average_daily_gain,
                    ];
                }
            }

            $adgZeroToPointOne = [];
            foreach ($livestocks as $livestock) {
                $adg = $livestock->average_daily_gain;
                if ($adg >= 0 && $adg <= 0.1) {
                    $adgZeroToPointOne[] = [
                        'ear_tag'            => $livestock->ear_tag,
                        'age_days'           => $livestock->age_days,
                        'pen_name'           => optional($livestock->pen)->name ?? '-',
                        'current_weight'     => $livestock->current_weight,
                        'average_daily_gain' => $adg,
                    ];
                }
            }

            $adgAbovePointOne = [];
            foreach ($livestocks as $livestock) {
                if ($livestock->average_daily_gain > 0.1) {
                    $adgAbovePointOne[] = [
                        'ear_tag'            => $livestock->ear_tag,
                        'age_days'           => $livestock->age_days,
                        'pen_name'           => optional($livestock->pen)->name ?? '-',
                        'current_weight'     => $livestock->current_weight,
                        'average_daily_gain' => $livestock->average_daily_gain,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total'                     => $total,
                    'total_weight'              => round($totalWeight, 2),
                    'avg_adg'                   => round($avgAdg, 3),
                    'male_count'                => $maleCount,
                    'female_count'              => $femaleCount,
                    'adg_high'                  => $adgHigh,
                    'adg_medium'                => $adgMedium,
                    'adg_low'                   => $adgLow,
                    'males'                     => $males,
                    'females'                   => $females,
                    'adg_negative'              => $adgNegative,
                    'adg_zero_to_point_one'     => $adgZeroToPointOne,
                    'adg_above_point_one'       => $adgAbovePointOne,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('FatteningDetailed API error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Data timbang untuk halaman Fattening (statistik ADG minus, upweight minus, dll).
     */
    public function fatteningTimbang(Request $request): JsonResponse
    {
        try {
            $query = Livestock::with(['pen', 'weightRecords'])
                ->whereHas('pen', function ($q) {
                    $q->where('category', 'like', '%Fattening%');
                });

            if ($request->filled('tagging')) {
                $query->where('ear_tag', 'like', '%' . $request->tagging . '%');
            }
            if ($request->filled('jenis')) {
                $query->where('breed_type', $request->jenis);
            }
            if ($request->filled('kategori')) {
                $query->whereHas('pen', function ($q) use ($request) {
                    $q->where('category', $request->kategori);
                });
            }

            $livestocks = $query->get();

            $minus2xList        = [];
            $upweightMinusList  = [];
            $timbangPerBulan    = [];

            foreach ($livestocks as $livestock) {
                // ADG minus 2x berturut-turut
                $weights = $livestock->weightRecords->sortByDesc('record_date')->take(3);
                $weightValues = $weights->pluck('weight_kg')->values()->toArray();
                if (count($weightValues) >= 3) {
                    $diff1 = $weightValues[0] - $weightValues[1];
                    $diff2 = $weightValues[1] - $weightValues[2];
                    if ($diff1 < 0 && $diff2 < 0) {
                        $minus2xList[] = [
                            'ear_tag'       => $livestock->ear_tag,
                            'pen_name'      => $livestock->pen->name ?? '-',
                            'pen_category'  => $livestock->pen->category ?? '-',
                            'breed_type'    => $livestock->breed_type,
                            'adg'           => round($diff1, 4),
                        ];
                    }
                }

                // Upweight minus (berat terbaru < berat awal)
                $latestWeight = $livestock->weightRecords->sortByDesc('record_date')->first();
                if ($latestWeight && $latestWeight->weight_kg < $livestock->initial_weight) {
                    $upweightMinusList[] = [
                        'ear_tag'       => $livestock->ear_tag,
                        'pen_name'      => $livestock->pen->name ?? '-',
                        'pen_category'  => $livestock->pen->category ?? '-',
                        'breed_type'    => $livestock->breed_type,
                        'adg'           => round($livestock->average_daily_gain, 4),
                    ];
                }

                // Data timbang per bulan (BB IN, BB 4,3,2,1 bulan lalu, BB terbaru)
                $records = $livestock->weightRecords->sortBy('record_date');
                $now = now();
                $months = [];
                for ($i = 4; $i >= 0; $i--) {
                    $target = $now->copy()->subMonths($i);
                    $record = $records->filter(function ($r) use ($target) {
                        return $r->record_date->format('Y-m') == $target->format('Y-m');
                    })->last();
                    $months[] = $record ? $record->weight_kg : '-';
                }
                $latest = $records->last();
                $timbangPerBulan[] = [
                    'ear_tag' => $livestock->ear_tag,
                    'bb_in'   => $livestock->initial_weight,
                    'bb_4'    => $months[0] ?? '-',
                    'bb_3'    => $months[1] ?? '-',
                    'bb_2'    => $months[2] ?? '-',
                    'bb_1'    => $months[3] ?? '-',
                    'bb_now'  => $latest ? $latest->weight_kg : $livestock->current_weight,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'qty_minus_2x'        => count($minus2xList),
                    'qty_upweight_minus'  => count($upweightMinusList),
                    'minus_2x_list'       => $minus2xList,
                    'upweight_minus_list' => $upweightMinusList,
                    'timbang_per_bulan'   => $timbangPerBulan,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('FatteningTimbang API error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Data ADG & FCR untuk halaman Fattening.
     */
    public function fatteningAdgFcr(Request $request): JsonResponse
    {
        try {
            $livestocks = Livestock::with(['pen', 'weightRecords'])
                ->whereHas('pen', function ($q) {
                    $q->where('category', 'like', '%Fattening%');
                })
                ->get();

            if ($livestocks->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'avg_adg'            => 0,
                        'total_upweight'     => 0,
                        'qty_ternak'         => 0,
                        'fcr'                => 0,
                        'qty_pakan'          => 0,
                        'adg_bulan_labels'   => [],
                        'adg_bulan_values'   => [],
                        'data_ternak'        => [],
                        'pakan_labels'       => [],
                        'pakan_values'       => [],
                        'pakan_rincian'      => [],
                        'fcr_bulan_labels'   => [],
                        'fcr_bulan_values'   => [],
                    ],
                ]);
            }

            $totalUpweight = 0;
            $totalAdg      = 0;
            $qtyTernak     = $livestocks->count();
            foreach ($livestocks as $livestock) {
                $totalUpweight += max(0, $livestock->current_weight - $livestock->initial_weight);
                $totalAdg      += $livestock->average_daily_gain;
            }
            $avgAdg = $qtyTernak > 0 ? $totalAdg / $qtyTernak : 0;

            $totalFeed = FeedingRecord::whereHas('pen', function ($q) {
                $q->where('category', 'like', '%Fattening%');
            })->sum('quantity_kg');

            $fcr = $totalUpweight > 0 ? round($totalFeed / $totalUpweight, 2) : 0;

            $pakanStats = Feed::whereHas('feedingRecords', function ($q) {
                $q->whereHas('pen', function ($q2) {
                    $q2->where('category', 'like', '%Fattening%');
                });
            })->get();

            $pakanLabels   = [];
            $pakanValues   = [];
            $pakanRincian  = [];
            foreach ($pakanStats as $feed) {
                $keluar = $feed->feedingRecords()
                    ->whereHas('pen', function ($q) {
                        $q->where('category', 'like', '%Fattening%');
                    })
                    ->sum('quantity_kg');
                if ($keluar > 0) {
                    $pakanLabels[] = $feed->name;
                    $pakanValues[] = $keluar;
                    $pakanRincian[] = [
                        'nama_pakan'   => $feed->name,
                        'keluar_kg'    => $keluar,
                        'persentase'   => round(($keluar / max($totalFeed, 1)) * 100, 1),
                    ];
                }
            }

            $monthlyAdg = [];
            foreach ($livestocks as $livestock) {
                $weights = $livestock->weightRecords->sortBy('record_date');
                $prev = null;
                foreach ($weights as $weight) {
                    if ($prev !== null) {
                        $month = $weight->record_date->format('Y-m');
                        $gain = $weight->weight_kg - $prev->weight_kg;
                        $days = $prev->record_date->diffInDays($weight->record_date);
                        $adg = $days > 0 ? $gain / $days : 0;
                        if (!isset($monthlyAdg[$month])) {
                            $monthlyAdg[$month] = ['total' => 0, 'count' => 0];
                        }
                        $monthlyAdg[$month]['total'] += $adg;
                        $monthlyAdg[$month]['count']++;
                    }
                    $prev = $weight;
                }
            }

            $adgBulanLabels = [];
            $adgBulanValues = [];
            foreach ($monthlyAdg as $month => $data) {
                $adgBulanLabels[] = $month;
                $adgBulanValues[] = round($data['total'] / $data['count'], 3);
            }

            $dataTernak = [];
            foreach ($livestocks as $livestock) {
                $latestWeightRecord = $livestock->weightRecords->sortByDesc('record_date')->first();
                $bulan = '-';
                if ($latestWeightRecord && $latestWeightRecord->record_date) {
                    $bulan = $latestWeightRecord->record_date->format('M Y');
                }
                $dataTernak[] = [
                    'ear_tag'   => $livestock->ear_tag,
                    'bb'        => round($livestock->current_weight, 2),
                    'upweight'  => round(max(0, $livestock->current_weight - $livestock->initial_weight), 2),
                    'adg'       => round($livestock->average_daily_gain, 3),
                    'bulan'     => $bulan,
                ];
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startDate = $request->start_date;
                $endDate   = $request->end_date;
                $filteredData = [];
                foreach ($dataTernak as $item) {
                    $livestock = $livestocks->firstWhere('ear_tag', $item['ear_tag']);
                    if ($livestock) {
                        $hasRecord = $livestock->weightRecords->whereBetween('record_date', [$startDate, $endDate])->count() > 0;
                        if ($hasRecord) {
                            $filteredData[] = $item;
                        }
                    }
                }
                $dataTernak = $filteredData;
            }

            $fcrBulanLabels = [];
            $fcrBulanValues = [];

            return response()->json([
                'success' => true,
                'data' => [
                    'avg_adg'            => round($avgAdg, 3),
                    'total_upweight'     => round($totalUpweight, 2),
                    'qty_ternak'         => $qtyTernak,
                    'fcr'                => $fcr,
                    'qty_pakan'          => round($totalFeed, 2),
                    'adg_bulan_labels'   => $adgBulanLabels,
                    'adg_bulan_values'   => $adgBulanValues,
                    'data_ternak'        => $dataTernak,
                    'pakan_labels'       => $pakanLabels,
                    'pakan_values'       => $pakanValues,
                    'pakan_rincian'      => $pakanRincian,
                    'fcr_bulan_labels'   => $fcrBulanLabels,
                    'fcr_bulan_values'   => $fcrBulanValues,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('FatteningAdgFcr API error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Data untuk halaman Breeding (ringkasan utama + data untuk grafik).
     */
    public function breeding(): JsonResponse
    {
        try {
            $totalSemuaTernak = Livestock::count();

            $qtyIndukan = Livestock::where('gender', 'female')
                ->where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
                ->count();

            $qtyPejantan = Livestock::where('gender', 'male')
                ->where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
                ->count();

            $qtyAnakan = Livestock::where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) < 365')
                ->count();

            $totalBreedingOverall = $qtyIndukan + $qtyPejantan + $qtyAnakan;

            $indukanList = Livestock::where('gender', 'female')
                ->where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
                ->get();

            $qtyHamil    = 0;
            $qtyMenyusui = 0;
            $qtySakit    = 0;
            foreach ($indukanList as $livestock) {
                if (stripos($livestock->condition, 'hamil') !== false || stripos($livestock->condition, 'bunting') !== false) {
                    $qtyHamil++;
                }
                if (stripos($livestock->condition, 'menyusui') !== false) {
                    $qtyMenyusui++;
                }
                if (stripos($livestock->condition, 'sakit') !== false) {
                    $qtySakit++;
                }
            }
            $qtyTidakHamil = $qtyIndukan - $qtyHamil - $qtyMenyusui;
            $qtyAfkir = Livestock::where('status', false)->orWhere('condition', 'afkir')->count();

            $totalIndukan = $qtyIndukan;
            $persenSehat    = $totalIndukan > 0 ? round((($totalIndukan - $qtyHamil - $qtyMenyusui - $qtySakit) / $totalIndukan) * 100, 1) : 0;
            $persenMenyusui = $totalIndukan > 0 ? round(($qtyMenyusui / $totalIndukan) * 100, 1) : 0;
            $persenHamil    = $totalIndukan > 0 ? round(($qtyHamil / $totalIndukan) * 100, 1) : 0;
            $persenAfkir    = 0;
            $persenSakit    = $totalIndukan > 0 ? round(($qtySakit / $totalIndukan) * 100, 1) : 0;

            $ageRanges = [
                'Cempe (0-1 Bulan)'     => Livestock::where('status', true)->whereRaw('DATEDIFF(CURDATE(), birth_date) <= 30')->count(),
                'Cempe Prasapih (1-3 Bulan)' => Livestock::where('status', true)->whereRaw('DATEDIFF(CURDATE(), birth_date) BETWEEN 31 AND 90')->count(),
                'Lepas Sapih (3-6 Bulan)'   => Livestock::where('status', true)->whereRaw('DATEDIFF(CURDATE(), birth_date) BETWEEN 91 AND 180')->count(),
                'Dara (6-12 Bulan)'         => Livestock::where('status', true)->whereRaw('DATEDIFF(CURDATE(), birth_date) BETWEEN 181 AND 365')->count(),
                'Dewasa (>12 Bulan)'        => Livestock::where('status', true)->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')->count(),
            ];
            $ageLabels = array_keys($ageRanges);
            $ageDistribution = array_values($ageRanges);

            $anakanLepas = Livestock::whereRaw('DATEDIFF(CURDATE(), birth_date) BETWEEN 90 AND 365')->count();
            $anakanBelum = Livestock::whereRaw('DATEDIFF(CURDATE(), birth_date) < 90')->count();

            $laktasiRaw = Logbook::where('event_type', 'Melahirkan')
                ->selectRaw('livestock_id, count(*) as jumlah')
                ->groupBy('livestock_id')
                ->get();
            $laktasiCount = ['Pertama' => 0, 'Kedua' => 0, 'Ketiga' => 0, 'Keempat' => 0, 'Kelima' => 0];
            foreach ($laktasiRaw as $item) {
                $jml = $item->jumlah;
                if ($jml == 1) {
                    $laktasiCount['Pertama']++;
                } elseif ($jml == 2) {
                    $laktasiCount['Kedua']++;
                } elseif ($jml == 3) {
                    $laktasiCount['Ketiga']++;
                } elseif ($jml == 4) {
                    $laktasiCount['Keempat']++;
                } else {
                    $laktasiCount['Kelima']++;
                }
            }

            $anak = [];
            $anakQuery = Livestock::with('pen')
                ->where(function ($q) {
                    $q->whereRaw('DATEDIFF(CURDATE(), birth_date) < 365')
                      ->orWhereNotNull('father_ear_tag')
                      ->orWhereNotNull('mother_ear_tag');
                })
                ->limit(200)
                ->get();
            foreach ($anakQuery as $l) {
                $anak[] = [
                    'ear_tag'        => $l->ear_tag,
                    'breed_type'     => $l->breed_type,
                    'gender'         => $l->gender,
                    'current_weight' => $l->current_weight,
                    'age_days'       => $l->age_days,
                ];
            }

            $indukBetina = [];
            $indukBetinaQuery = Livestock::with('pen')
                ->where('gender', 'female')
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
                ->where('status', true)
                ->get();
            foreach ($indukBetinaQuery as $l) {
                $indukBetina[] = [
                    'id'             => $l->id,
                    'ear_tag'        => $l->ear_tag,
                    'breed_type'     => $l->breed_type,
                    'birth_date'     => optional($l->birth_date)->format('Y-m-d'),
                    'current_weight' => $l->current_weight,
                    'age_days'       => $l->age_days,
                    'condition'      => $l->condition,
                    'pen_name'       => optional($l->pen)->name ?? '-',
                ];
            }

            $indukJantan = [];
            $indukJantanQuery = Livestock::with('pen')
                ->where('gender', 'male')
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
                ->where('status', true)
                ->get();
            foreach ($indukJantanQuery as $l) {
                $indukJantan[] = [
                    'id'             => $l->id,
                    'ear_tag'        => $l->ear_tag,
                    'breed_type'     => $l->breed_type,
                    'current_weight' => $l->current_weight,
                    'age_days'       => $l->age_days,
                    'pen_name'       => optional($l->pen)->name ?? '-',
                ];
            }

            $afkir = [];
            $afkirQuery = Livestock::with('pen')
                ->where('status', false)
                ->orWhere('condition', 'afkir')
                ->get();
            foreach ($afkirQuery as $l) {
                $afkir[] = [
                    'ear_tag'        => $l->ear_tag,
                    'breed_type'     => $l->breed_type,
                    'pen'            => $l->pen,
                    'age_days'       => $l->age_days,
                    'current_weight' => $l->current_weight,
                ];
            }

            $qtyKawin = Livestock::whereHas('pen', function ($q) {
                $q->where('category', 'like', '%Kawin%');
            })->where('gender', 'female')->count();

            $qtyKarantina = Livestock::whereHas('pen', function ($q) {
                $q->where('category', 'like', '%Karantina%');
            })->where('gender', 'female')->count();

            $qtyPersiapan = Livestock::whereHas('pen', function ($q) {
                $q->where('category', 'like', '%Persiapan Breeding%');
            })->where('gender', 'female')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_overall'                  => $totalBreedingOverall,
                    'total_semua_ternak'             => $totalSemuaTernak,
                    'qty_anakan'                     => $qtyAnakan,
                    'qty_indukan'                    => $qtyIndukan,
                    'qty_pejantan'                   => $qtyPejantan,
                    'qty_betina_hamil'               => $qtyHamil,
                    'qty_betina_menyusui'            => $qtyMenyusui,
                    'qty_betina_tidak_hamil'         => $qtyTidakHamil,
                    'qty_afkir'                      => $qtyAfkir,
                    'persen_sehat'                   => $persenSehat,
                    'persen_menyusui'                => $persenMenyusui,
                    'persen_hamil'                   => $persenHamil,
                    'persen_afkir'                   => $persenAfkir,
                    'persen_sakit'                   => $persenSakit,
                    'age_labels'                     => $ageLabels,
                    'age_distribution'               => $ageDistribution,
                    'anakan_lepas_sapih'             => $anakanLepas,
                    'anakan_belum_lepas_sapih'       => $anakanBelum,
                    'laktasi_data'                   => $laktasiCount,
                    'anak'                           => $anak,
                    'induk_betina'                   => $indukBetina,
                    'induk_jantan'                   => $indukJantan,
                    'induk_betina_afkir'             => $afkir,
                    'qty_betina_kawin'               => $qtyKawin,
                    'qty_betina_karantina'           => $qtyKarantina,
                    'qty_betina_persiapan_breeding'  => $qtyPersiapan,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Breeding API error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mendapatkan keluarga ternak berdasarkan ear tag.
     */
    public function getFamily(Request $request): JsonResponse
    {
        try {
            $earTag = $request->query('ear_tag');
            if (empty($earTag)) {
                return response()->json(['success' => false, 'message' => 'Parameter ear_tag diperlukan.'], 400);
            }

            $livestock = Livestock::where('ear_tag', $earTag)->first();
            if (!$livestock) {
                return response()->json(['success' => false, 'message' => 'Ternak tidak ditemukan.'], 404);
            }

            $father = null;
            if (!empty($livestock->father_ear_tag)) {
                $father = Livestock::where('ear_tag', $livestock->father_ear_tag)->first();
            }

            $mother = null;
            if (!empty($livestock->mother_ear_tag)) {
                $mother = Livestock::where('ear_tag', $livestock->mother_ear_tag)->first();
            }

            $children = [];
            $childrenQuery = Livestock::where('father_ear_tag', $earTag)
                ->orWhere('mother_ear_tag', $earTag)
                ->get();
            foreach ($childrenQuery as $c) {
                $children[] = [
                    'ear_tag'        => $c->ear_tag,
                    'breed_type'     => $c->breed_type,
                    'gender'         => $c->gender,
                    'current_weight' => $c->current_weight,
                    'age_days'       => $c->age_days,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'selected' => [
                        'ear_tag'        => $livestock->ear_tag,
                        'breed_type'     => $livestock->breed_type,
                        'gender'         => $livestock->gender,
                        'birth_date'     => optional($livestock->birth_date)->format('Y-m-d'),
                        'current_weight' => $livestock->current_weight,
                    ],
                    'father' => $father ? [
                        'ear_tag'        => $father->ear_tag,
                        'breed_type'     => $father->breed_type,
                        'current_weight' => $father->current_weight,
                    ] : null,
                    'mother' => $mother ? [
                        'ear_tag'        => $mother->ear_tag,
                        'breed_type'     => $mother->breed_type,
                        'current_weight' => $mother->current_weight,
                    ] : null,
                    'children' => $children,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('GetFamily API error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Data untuk halaman Indukan (Breeding) – tampilan detail.
     */
    public function breedingIndukan(Request $request): JsonResponse
    {
        try {
            $indukan = Livestock::with('pen')
                ->where('gender', 'female')
                ->where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
                ->get();

            $qtyIndukan      = $indukan->count();
            $qtyHamil        = 0;
            $qtyMenyusui     = 0;
            $qtySakit        = 0;
            foreach ($indukan as $livestock) {
                if (stripos($livestock->condition, 'hamil') !== false) {
                    $qtyHamil++;
                }
                if (stripos($livestock->condition, 'menyusui') !== false) {
                    $qtyMenyusui++;
                }
                if (stripos($livestock->condition, 'sakit') !== false) {
                    $qtySakit++;
                }
            }
            $qtyTidakHamil = $qtyIndukan - $qtyHamil - $qtyMenyusui;
            $qtyAfkir = Livestock::where('status', false)->orWhere('condition', 'afkir')->count();

            $persenSehat    = $qtyIndukan > 0 ? round((($qtyIndukan - $qtyHamil - $qtyMenyusui - $qtySakit) / $qtyIndukan) * 100, 1) : 0;
            $persenMenyusui = $qtyIndukan > 0 ? round(($qtyMenyusui / $qtyIndukan) * 100, 1) : 0;
            $persenHamil    = $qtyIndukan > 0 ? round(($qtyHamil / $qtyIndukan) * 100, 1) : 0;
            $persenAfkir    = $qtyIndukan > 0 ? round(($qtyAfkir / ($qtyIndukan + $qtyAfkir)) * 100, 1) : 0;
            $persenSakit    = $qtyIndukan > 0 ? round(($qtySakit / $qtyIndukan) * 100, 1) : 0;

            $ipi = Logbook::where('event_type', 'Melahirkan')
                ->whereHas('livestock', function ($q) {
                    $q->where('gender', 'female');
                })
                ->selectRaw('AVG(CAST(new_tag AS UNSIGNED)) as avg_anak')
                ->value('avg_anak');
            $skorIpi = $ipi ? round($ipi, 2) : 0;

            $qtyKandangKawin = Livestock::whereHas('pen', function ($q) {
                $q->where('category', 'Kawin');
            })->where('gender', 'female')->count();

            $qtyKarantina = Livestock::whereHas('pen', function ($q) {
                $q->where('category', 'Karantina');
            })->where('gender', 'female')->count();

            $qtyPersiapan = Livestock::whereHas('pen', function ($q) {
                $q->where('category', 'Persiapan Breeding');
            })->where('gender', 'female')->count();

            $getSubKategori = function ($ageDays) {
                if ($ageDays <= 30) {
                    return 'Cempe (0-1 Bulan)';
                }
                if ($ageDays <= 90) {
                    return 'Cempe Prasapih (1-3 Bulan)';
                }
                if ($ageDays <= 180) {
                    return 'Lepas Sapih (3-6 Bulan)';
                }
                if ($ageDays <= 365) {
                    return 'Dara (6-12 Bulan)';
                }
                return 'Dewasa (>12 Bulan)';
            };

            $dataSakit           = [];
            $dataKandangKawin    = [];
            $dataMenyusui        = [];
            $dataHamil           = [];
            $dataCalonIndukan    = [];
            $dataSubKategori     = [];

            foreach ($indukan as $livestock) {
                if (stripos($livestock->condition, 'sakit') !== false) {
                    $dataSakit[] = [
                        'ear_tag'        => $livestock->ear_tag,
                        'breed_type'     => $livestock->breed_type,
                        'pen_name'       => optional($livestock->pen)->name ?? '-',
                        'sub_kategori'   => $getSubKategori($livestock->age_days),
                        'current_weight' => $livestock->current_weight,
                    ];
                }
                if (stripos($livestock->condition, 'menyusui') !== false) {
                    $dataMenyusui[] = [
                        'ear_tag'      => $livestock->ear_tag,
                        'sub_kategori' => $getSubKategori($livestock->age_days),
                        'pen_name'     => optional($livestock->pen)->name ?? '-',
                    ];
                }
                if (stripos($livestock->condition, 'hamil') !== false) {
                    $dataHamil[] = [
                        'ear_tag'      => $livestock->ear_tag,
                        'sub_kategori' => $getSubKategori($livestock->age_days),
                        'pen_name'     => optional($livestock->pen)->name ?? '-',
                    ];
                }
                $dataSubKategori[] = [
                    'ear_tag'      => $livestock->ear_tag,
                    'sub_kategori' => $getSubKategori($livestock->age_days),
                    'pen_name'     => optional($livestock->pen)->name ?? '-',
                    'umur'         => $livestock->age_days,
                    'kondisi'      => $livestock->condition ?? 'Sehat',
                ];
            }

            $kandangKawinQuery = Livestock::whereHas('pen', function ($q) {
                $q->where('category', 'Kawin');
            })->where('gender', 'female')->get();

            foreach ($kandangKawinQuery as $livestock) {
                $dataKandangKawin[] = [
                    'ear_tag'        => $livestock->ear_tag,
                    'breed_type'     => $livestock->breed_type,
                    'pen_name'       => optional($livestock->pen)->name ?? '-',
                    'sub_kategori'   => $getSubKategori($livestock->age_days),
                    'current_weight' => $livestock->current_weight,
                ];
            }

            $calonQuery = Livestock::where('gender', 'female')
                ->where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) BETWEEN 180 AND 365')
                ->with('pen')
                ->get();

            foreach ($calonQuery as $livestock) {
                $dataCalonIndukan[] = [
                    'ear_tag'        => $livestock->ear_tag,
                    'breed_type'     => $livestock->breed_type,
                    'pen_name'       => optional($livestock->pen)->name ?? '-',
                    'sub_kategori'   => $getSubKategori($livestock->age_days),
                    'current_weight' => $livestock->current_weight,
                ];
            }

            $logbooks = Logbook::where('event_type', 'Melahirkan')
                ->whereHas('livestock', function ($q) {
                    $q->where('gender', 'female');
                })
                ->with('livestock')
                ->get();

            $grouped = [];
            foreach ($logbooks as $log) {
                $grouped[$log->livestock_id][] = $log;
            }

            $dataJenisKelahiran = [];
            foreach ($grouped as $lid => $logs) {
                $livestock = $logs[0]->livestock;
                if (!$livestock) {
                    continue;
                }
                $tipe = [];
                foreach ($logs as $idx => $log) {
                    $tipe['tipe_laktasi_' . ($idx + 1)] = $log->description ?? 'Tunggal';
                }
                $dataJenisKelahiran[] = array_merge([
                    'ear_tag'        => $livestock->ear_tag,
                    'pen_name'       => optional($livestock->pen)->name ?? '-',
                    'umur'           => $livestock->age_days,
                    'current_weight' => $livestock->current_weight,
                ], $tipe);
            }

            $listTagging = array_values(array_unique($indukan->pluck('ear_tag')->toArray()));

            return response()->json([
                'success' => true,
                'data' => [
                    'qty_indukan'            => $qtyIndukan,
                    'qty_hamil'              => $qtyHamil,
                    'qty_menyusui'           => $qtyMenyusui,
                    'qty_tidak_hamil'        => $qtyTidakHamil,
                    'qty_kandang_kawin'      => $qtyKandangKawin,
                    'qty_karantina'          => $qtyKarantina,
                    'qty_persiapan'          => $qtyPersiapan,
                    'skor_ipi'               => $skorIpi,
                    'persen_sehat'           => $persenSehat,
                    'persen_menyusui'        => $persenMenyusui,
                    'persen_hamil'           => $persenHamil,
                    'persen_afkir'           => $persenAfkir,
                    'persen_sakit'           => $persenSakit,
                    'data_sakit'             => $dataSakit,
                    'data_kandang_kawin'     => $dataKandangKawin,
                    'data_menyusui'          => $dataMenyusui,
                    'data_hamil'             => $dataHamil,
                    'data_calon_indukan'     => $dataCalonIndukan,
                    'data_sub_kategori'      => $dataSubKategori,
                    'data_jenis_kelahiran'   => $dataJenisKelahiran,
                    'list_tagging'           => $listTagging,
                    'performa_indukan'       => 'Belum pernah hamil',
                    'performa_anakan'        => 'Belum Mempunyai anak',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BreedingIndukan API error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Detail indukan berdasarkan ear tag (real data, no dummy).
     */
    public function breedingIndukanDetail(Request $request): JsonResponse
    {
        try {
            $tag = $request->query('tag');
            if (empty($tag)) {
                return response()->json(['success' => false, 'message' => 'Parameter tag diperlukan'], 400);
            }

            $livestock = Livestock::with('pen', 'weightRecords')->where('ear_tag', $tag)->first();
            if (!$livestock) {
                return response()->json(['success' => false, 'message' => 'Ternak tidak ditemukan'], 404);
            }

            $birthLogs = Logbook::where('livestock_id', $livestock->id)
                ->where('event_type', 'Melahirkan')
                ->orderBy('event_date')
                ->get();

            $totalChildren = 0;
            $totalBirths = $birthLogs->count();
            foreach ($birthLogs as $log) {
                preg_match('/(\d+)/', $log->description ?? '', $matches);
                $childCount = (int)($matches[0] ?? 0);
                $totalChildren += $childCount;
            }
            $avgChildren = $totalBirths > 0 ? $totalChildren / $totalBirths : 0;
            $skorIpi = $avgChildren > 0 ? round($avgChildren * 10, 2) : null;
            $performaIndukan = $totalBirths > 0 ? "{$totalBirths} kali melahirkan, total {$totalChildren} anak" : null;

            $children = Livestock::where(function ($q) use ($tag) {
                $q->where('father_ear_tag', $tag)
                  ->orWhere('mother_ear_tag', $tag);
            })->get();
            $avgChildWeight = $children->avg('current_weight');
            $performaAnakan = $children->count() > 0 ? round($avgChildWeight, 2) . ' kg (rata-rata berat)' : null;

            $laktasi = [];
            $i = 1;
            foreach ($birthLogs as $log) {
                $laktasi['laktasi_' . $i] = [
                    'tanggal'        => $log->event_date->format('Y-m-d'),
                    'anak'           => $log->description ?? '-',
                    'tipe_kelahiran' => $log->handling ?? '-',
                    'bb_induk'       => $log->new_tag ?? $livestock->current_weight,
                    'bb_lahir'       => $log->new_pen_category ?? '-',
                    'bb_lepas_sapih' => '-',
                    'sex_anak'       => '-',
                ];
                $i++;
            }

            $getSubKategori = function ($ageDays) {
                if ($ageDays <= 30) {
                    return 'Cempe (0-1 Bulan)';
                }
                if ($ageDays <= 90) {
                    return 'Cempe Prasapih (1-3 Bulan)';
                }
                if ($ageDays <= 180) {
                    return 'Lepas Sapih (3-6 Bulan)';
                }
                if ($ageDays <= 365) {
                    return 'Dara (6-12 Bulan)';
                }
                return 'Dewasa (>12 Bulan)';
            };

            $data = [
                'ear_tag'            => $livestock->ear_tag,
                'gender'             => $livestock->gender,
                'breed_type'         => $livestock->breed_type,
                'current_weight'     => $livestock->current_weight,
                'last_weight_date'   => optional($livestock->weightRecords->sortByDesc('record_date')->first())->record_date,
                'birth_date'         => optional($livestock->birth_date)->format('Y-m-d'),
                'age_days'           => $livestock->age_days,
                'condition'          => $livestock->condition,
                'status'             => $livestock->status,
                'pen'                => $livestock->pen,
                'reproductive_age'   => $livestock->reproductive_age,
                'father_ear_tag'     => $livestock->father_ear_tag,
                'mother_ear_tag'     => $livestock->mother_ear_tag,
                'sub_kategori'       => $getSubKategori($livestock->age_days),
                'jangan_dikawin'     => (bool) ($livestock->condition === 'tidak boleh dikawinkan'),
                'sedang_kawin_dengan'=> null,
                'skor_ipi'           => $skorIpi,
                'performa_indukan'   => $performaIndukan,
                'performa_anakan'    => $performaAnakan,
            ];

            foreach ($laktasi as $key => $val) {
                $data[$key] = $val;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('BreedingIndukanDetail API error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Data untuk halaman Pejantan (Breeding) – versi dengan statistik akurat dari seluruh data.
     */
    public function breedingPejantan(Request $request): JsonResponse
    {
        try {
            $baseQuery = Livestock::with('pen')
                ->where('gender', 'male')
                ->where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365');

            if ($request->filled('status')) {
                $baseQuery->where('status', $request->status == '1');
            }
            if ($request->filled('breed')) {
                $baseQuery->where('breed_type', $request->breed);
            }
            if ($request->filled('pen')) {
                $baseQuery->where('pen_id', $request->pen);
            }

            $allPejantan = $baseQuery->get();

            $totalPejantan = $allPejantan->count();
            $aktifCount    = $allPejantan->where('status', true)->count();
            $nonaktifCount = $allPejantan->where('status', false)->count();

            $totalBb   = $allPejantan->sum('current_weight');
            $totalUmur = $allPejantan->sum('age_days');
            $totalAdg  = $allPejantan->sum('average_daily_gain');

            $avgBb   = $totalPejantan > 0 ? $totalBb / $totalPejantan : 0;
            $avgUmur = $totalPejantan > 0 ? $totalUmur / $totalPejantan : 0;
            $avgAdg  = $totalPejantan > 0 ? $totalAdg / $totalPejantan : 0;

            $kandangKawin = $allPejantan->filter(function ($livestock) {
                return $livestock->pen && $livestock->pen->category === 'Kawin';
            })->count();

            $karantinaCount = $allPejantan->filter(function ($livestock) {
                return $livestock->pen && $livestock->pen->category === 'Karantina';
            })->count();

            $breedGroups = $allPejantan->groupBy('breed_type')->map->count();
            $breedLabels = $breedGroups->keys();
            $breedCounts = $breedGroups->values();

            $perPage = $request->input('per_page', 15);
            $pejantan = $baseQuery->paginate($perPage);

            $dataPejantan = $pejantan->map(function ($p) {
                return [
                    'id'                 => $p->id,
                    'ear_tag'            => $p->ear_tag,
                    'breed_type'         => $p->breed_type,
                    'current_weight'     => $p->current_weight,
                    'age_days'           => $p->age_days,
                    'average_daily_gain' => $p->average_daily_gain,
                    'pen_name'           => optional($p->pen)->name ?? '-',
                    'status'             => $p->status,
                    'condition'          => $p->condition ?? '-',
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_pejantan'   => $totalPejantan,
                    'aktif_count'      => $aktifCount,
                    'nonaktif_count'   => $nonaktifCount,
                    'avg_bb'           => round($avgBb, 2),
                    'avg_umur'         => round($avgUmur, 0),
                    'kandang_kawin'    => $kandangKawin,
                    'karantina_count'  => $karantinaCount,
                    'avg_adg'          => round($avgAdg, 3),
                    'breed_labels'     => $breedLabels,
                    'breed_counts'     => $breedCounts,
                    'pejantan'         => $dataPejantan,
                    'pagination' => [
                        'current_page' => $pejantan->currentPage(),
                        'per_page'     => $pejantan->perPage(),
                        'total'        => $pejantan->total(),
                        'last_page'    => $pejantan->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BreedingPejantan API error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Data untuk halaman Anakan (Breeding). Hanya menampilkan ternak dengan umur < 365 hari.
     */
    public function breedingAnakan(Request $request): JsonResponse
    {
        try {
            $query = Livestock::with('pen')
                ->where('status', true)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) < 365');

            if ($request->filled('gender')) {
                $query->where('gender', $request->gender);
            }
            if ($request->filled('weaned')) {
                if ($request->weaned == '1') {
                    $query->whereRaw('DATEDIFF(CURDATE(), birth_date) > 90');
                } else {
                    $query->whereRaw('DATEDIFF(CURDATE(), birth_date) <= 90');
                }
            }
            if ($request->filled('induk')) {
                $query->where(function ($q) use ($request) {
                    $q->where('mother_ear_tag', $request->induk)
                      ->orWhere('father_ear_tag', $request->induk);
                });
            }

            $anakan = $query->get();

            $totalAnak   = $anakan->count();
            $jantanCount = $anakan->where('gender', 'male')->count();
            $betinaCount = $anakan->where('gender', 'female')->count();
            $totalBb     = $anakan->sum('current_weight');
            $avgBb       = $totalAnak > 0 ? $totalBb / $totalAnak : 0;

            $lepasSapih = $anakan->filter(function ($a) {
                return $a->age_days > 90;
            })->count();
            $belumLepas = $totalAnak - $lepasSapih;

            $kandangKhusus = $anakan->filter(function ($a) {
                return $a->pen_id !== null;
            })->count();

            $mortalitas = Livestock::where('status', false)
                ->whereRaw('DATEDIFF(CURDATE(), birth_date) < 365')
                ->count();

            $ageLabels = ['0-30', '31-60', '61-90', '91-120', '>120'];
            $ageDistribusi = [0, 0, 0, 0, 0];
            foreach ($anakan as $a) {
                $age = $a->age_days;
                if ($age <= 30) {
                    $ageDistribusi[0]++;
                } elseif ($age <= 60) {
                    $ageDistribusi[1]++;
                } elseif ($age <= 90) {
                    $ageDistribusi[2]++;
                } elseif ($age <= 120) {
                    $ageDistribusi[3]++;
                } else {
                    $ageDistribusi[4]++;
                }
            }

            $dataAnakan = $anakan->map(function ($a) {
                return [
                    'id'             => $a->id,
                    'ear_tag'        => $a->ear_tag,
                    'breed_type'     => $a->breed_type,
                    'gender'         => $a->gender,
                    'current_weight' => $a->current_weight,
                    'age_days'       => $a->age_days,
                    'mother_ear_tag' => $a->mother_ear_tag ?? '-',
                    'father_ear_tag' => $a->father_ear_tag ?? '-',
                    'is_weaned'      => $a->age_days > 90,
                    'pen_name'       => optional($a->pen)->name ?? '-',
                ];
            })->values();

            $perPage = 15;
            $currentPage = $request->input('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginated = $dataAnakan->slice($offset, $perPage)->values();
            $total = $dataAnakan->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_anak'      => $totalAnak,
                    'jantan_count'    => $jantanCount,
                    'betina_count'    => $betinaCount,
                    'avg_bb'          => round($avgBb, 2),
                    'lepas_sapih'     => $lepasSapih,
                    'belum_lepas'     => $belumLepas,
                    'kandang_khusus'  => $kandangKhusus,
                    'mortalitas'      => $mortalitas,
                    'age_labels'      => $ageLabels,
                    'age_distribution'=> $ageDistribusi,
                    'anakan'          => $paginated,
                    'pagination' => [
                        'current_page' => $currentPage,
                        'per_page'     => $perPage,
                        'total'        => $total,
                        'last_page'    => ceil($total / $perPage),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BreedingAnakan API error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Data untuk halaman Kawin & IB (Breeding) – versi lengkap dengan filter.
     */
    public function breedingKawinIb(Request $request): JsonResponse
    {
        try {
            $queryKawin = Logbook::with('livestock', 'newPen')
                ->where('event_type', 'Kawin');
            $queryIb = Logbook::with('livestock')
                ->where('event_type', 'IB');

            // Filter berdasarkan tipe
            $type = $request->input('type');
            if ($type === 'kawin') {
                $queryIb->whereRaw('1 = 0');
            } elseif ($type === 'ib') {
                $queryKawin->whereRaw('1 = 0');
            }

            // Filter status
            $status = $request->input('status');
            if ($status && in_array($status, ['success', 'failed', 'pending'])) {
                $queryKawin->where('handling', $status);
                $queryIb->where('handling', $status);
            }

            // Filter tanggal
            if ($request->filled('start_date')) {
                $startDate = $request->start_date;
                $queryKawin->whereDate('event_date', '>=', $startDate);
                $queryIb->whereDate('event_date', '>=', $startDate);
            }
            if ($request->filled('end_date')) {
                $endDate = $request->end_date;
                $queryKawin->whereDate('event_date', '<=', $endDate);
                $queryIb->whereDate('event_date', '<=', $endDate);
            }

            // Ambil data dengan batasan (misal 50 terbaru)
            $kawinData = $queryKawin->orderBy('event_date', 'desc')->limit(50)->get()->map(function ($log) {
                return [
                    'id'            => $log->id,
                    'mating_date'   => $log->event_date ? $log->event_date->format('Y-m-d') : '-',
                    'female_tag'    => optional($log->livestock)->ear_tag ?? '-',
                    'male_tag'      => $log->new_tag ?? '-',
                    'pen_name'      => optional($log->newPen)->name ?? '-',
                    'status'        => $log->handling ?? 'pending',
                    'pregnancy_date'=> $log->pregnancy_date ? $log->pregnancy_date->format('Y-m-d') : '-',
                ];
            })->toArray();

            $ibData = $queryIb->orderBy('event_date', 'desc')->limit(50)->get()->map(function ($log) {
                return [
                    'id'            => $log->id,
                    'ib_date'       => $log->event_date ? $log->event_date->format('Y-m-d') : '-',
                    'female_tag'    => optional($log->livestock)->ear_tag ?? '-',
                    'semen_source'  => $log->new_tag ?? '-',
                    'officer_name'  => $log->officer_name ?? '-',
                    'status'        => $log->handling ?? 'pending',
                    'pregnancy_date'=> $log->pregnancy_date ? $log->pregnancy_date->format('Y-m-d') : '-',
                ];
            })->toArray();

            // Hitung statistik dari seluruh data (tanpa filter untuk ringkasan)
            $totalKawin = Logbook::where('event_type', 'Kawin')->count();
            $totalIb    = Logbook::where('event_type', 'IB')->count();
            $totalPerkawinan = $totalKawin + $totalIb;

            $pregnantCount = Livestock::where('condition', 'like', '%hamil%')->count();
            $activeMale = Livestock::where('gender', 'male')->where('status', true)->count();
            $monthlyCount = Logbook::where('event_type', 'Kawin')
                ->whereYear('event_date', now()->year)
                ->whereMonth('event_date', now()->month)
                ->count();

            $totalKawinSuccess = Logbook::where('event_type', 'Kawin')
                ->where('handling', 'success')
                ->count();
            $successRate = $totalKawin > 0 ? round(($totalKawinSuccess / $totalKawin) * 100, 1) : 0;

            $ibOfficer = 0;
            if (Schema::hasColumn('logbooks', 'officer_name')) {
                $ibOfficer = Logbook::where('event_type', 'IB')
                    ->whereNotNull('officer_name')
                    ->distinct('officer_name')
                    ->count('officer_name');
            }

            // Trend 6 bulan terakhir (hanya untuk kawin alami)
            $trendLabels = [];
            $trendValues = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $trendLabels[] = $month->format('M Y');
                $trendValues[] = Logbook::where('event_type', 'Kawin')
                    ->whereYear('event_date', $month->year)
                    ->whereMonth('event_date', $month->month)
                    ->count();
            }

            // Paginasi sederhana untuk frontend
            $perPage = 10;
            $currentPage = $request->input('page', 1);
            $offset = ($currentPage - 1) * $perPage;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_kawin'        => $totalKawin,
                    'kawin_alami'        => $totalKawin,
                    'total_ib'           => $totalIb,
                    'total_perkawinan'   => $totalPerkawinan,
                    'success_rate'       => $successRate,
                    'pregnant_count'     => $pregnantCount,
                    'monthly_count'      => $monthlyCount,
                    'active_male'        => $activeMale,
                    'ib_officer'         => $ibOfficer,
                    'trend_labels'       => $trendLabels,
                    'trend_values'       => $trendValues,
                    'kawin'              => array_slice($kawinData, $offset, $perPage),
                    'ib'                 => array_slice($ibData, $offset, $perPage),
                    'pagination'         => [
                        'current_page' => $currentPage,
                        'per_page'     => $perPage,
                        'total'        => count($kawinData),
                        'last_page'    => max(1, ceil(count($kawinData) / $perPage)),
                    ],
                    'ib_pagination'      => [
                        'current_page' => $currentPage,
                        'per_page'     => $perPage,
                        'total'        => count($ibData),
                        'last_page'    => max(1, ceil(count($ibData) / $perPage)),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('breedingKawinIb error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail perkawinan (Kawin Alami atau IB) berdasarkan ID.
     */
    public function breedingKawinIbDetail(Request $request): JsonResponse
    {
        try {
            $id = $request->query('id');
            $type = $request->query('type');

            if (!$id || !$type) {
                return response()->json(['success' => false, 'message' => 'Parameter id dan type diperlukan'], 400);
            }

            $query = Logbook::with('livestock', 'newPen');
            if ($type === 'kawin') {
                $query->where('event_type', 'Kawin');
            } elseif ($type === 'ib') {
                $query->where('event_type', 'IB');
            } else {
                return response()->json(['success' => false, 'message' => 'Type tidak valid'], 400);
            }

            $log = $query->find($id);

            if (!$log) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }

            if ($type === 'kawin') {
                $data = [
                    'id'            => $log->id,
                    'mating_date'   => $log->event_date ? $log->event_date->format('Y-m-d') : null,
                    'female_tag'    => optional($log->livestock)->ear_tag,
                    'male_tag'      => $log->new_tag,
                    'pen_name'      => optional($log->newPen)->name,
                    'status'        => $log->handling ?? 'pending',
                    'pregnancy_date'=> $log->pregnancy_date ? $log->pregnancy_date->format('Y-m-d') : null,
                    'officer_name'  => $log->officer_name,
                    'notes'         => $log->description,
                ];
            } else {
                $data = [
                    'id'            => $log->id,
                    'ib_date'       => $log->event_date ? $log->event_date->format('Y-m-d') : null,
                    'female_tag'    => optional($log->livestock)->ear_tag,
                    'semen_source'  => $log->new_tag,
                    'officer_name'  => $log->officer_name,
                    'status'        => $log->handling ?? 'pending',
                    'pregnancy_date'=> $log->pregnancy_date ? $log->pregnancy_date->format('Y-m-d') : null,
                    'notes'         => $log->description,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('BreedingKawinIbDetail error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menyimpan data perkawinan baru (Kawin Alami atau IB).
     */
    public function breedingKawinIbStore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'jenis'          => 'required|in:kawin,ib',
                'event_date'     => 'required|date',
                'female_tag'     => 'required|exists:livestocks,id',
                'male_tag'       => 'nullable|exists:livestocks,id',
                'semen_source'   => 'nullable|string|max:100',
                'pen_id'         => 'nullable|exists:pens,id',
                'officer_name'   => 'nullable|string|max:100',
                'status'         => 'required|in:pending,success,failed',
                'pregnancy_date' => 'nullable|date',
                'description'    => 'nullable|string',
            ]);

            $female = Livestock::find($validated['female_tag']);

            if ($validated['jenis'] === 'kawin') {
                $eventType = 'Kawin';
                $newTag = $validated['male_tag'] ? Livestock::find($validated['male_tag'])->ear_tag : null;
            } else {
                $eventType = 'IB';
                $newTag = $validated['semen_source'];
            }

            $logbook = Logbook::create([
                'livestock_id'    => $female->id,
                'event_date'      => $validated['event_date'],
                'event_type'      => $eventType,
                'description'     => $validated['description'] ?? null,
                'handling'        => $validated['status'],
                'new_tag'         => $newTag,
                'new_pen_id'      => $validated['pen_id'] ?? null,
                'officer_name'    => $validated['officer_name'] ?? null,
                'pregnancy_date'  => $validated['pregnancy_date'] ?? null,
            ]);

            if ($validated['status'] === 'success') {
                $female->condition = 'Hamil';
                $female->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Data tersimpan',
                'data'    => $logbook,
            ]);
        } catch (\Exception $e) {
            Log::error('Store kawin/ib error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== TABEL UNTUK HALAMAN UTAMA BREEDING ====================

    /**
     * Data untuk tabel Induk Betina di halaman utama Breeding.
     */
    public function breedingInduk(Request $request): JsonResponse
    {
        try {
            $query = Livestock::with('pen')
                ->where('gender', 'female')
                ->where('status', true);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('ear_tag', 'like', "%{$search}%")
                      ->orWhere('breed_type', 'like', "%{$search}%");
                });
            }
            if ($request->filled('pen_id')) {
                $query->where('pen_id', $request->pen_id);
            }

            $induk = $query->paginate($request->input('per_page', 15));

            $data = $induk->map(function ($livestock) {
                return [
                    'ear_tag'        => $livestock->ear_tag,
                    'breed_type'     => $livestock->breed_type,
                    'current_weight' => $livestock->current_weight,
                    'age_days'       => $livestock->age_days,
                    'condition'      => $livestock->condition ?? '-',
                    'pen_name'       => optional($livestock->pen)->name ?? '-',
                    'id'             => $livestock->id,
                ];
            })->values();

            return response()->json([
                'success'    => true,
                'data'       => $data,
                'pagination' => [
                    'current_page' => $induk->currentPage(),
                    'per_page'     => $induk->perPage(),
                    'total'        => $induk->total(),
                    'last_page'    => $induk->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BreedingInduk error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Data untuk tabel Pejantan di halaman utama Breeding.
     */
    public function breedingJantan(Request $request): JsonResponse
    {
        try {
            $query = Livestock::with('pen')
                ->where('gender', 'male')
                ->where('status', true);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('ear_tag', 'like', "%{$search}%")
                      ->orWhere('breed_type', 'like', "%{$search}%");
                });
            }
            if ($request->filled('pen_id')) {
                $query->where('pen_id', $request->pen_id);
            }

            $pejantan = $query->paginate($request->input('per_page', 15));

            $data = $pejantan->map(function ($livestock) {
                return [
                    'ear_tag'        => $livestock->ear_tag,
                    'breed_type'     => $livestock->breed_type,
                    'current_weight' => $livestock->current_weight,
                    'age_days'       => $livestock->age_days,
                    'pen_name'       => optional($livestock->pen)->name ?? '-',
                    'id'             => $livestock->id,
                ];
            })->values();

            return response()->json([
                'success'    => true,
                'data'       => $data,
                'pagination' => [
                    'current_page' => $pejantan->currentPage(),
                    'per_page'     => $pejantan->perPage(),
                    'total'        => $pejantan->total(),
                    'last_page'    => $pejantan->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BreedingJantan error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }
}