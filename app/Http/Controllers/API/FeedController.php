<?php

namespace App\Http\Controllers\API;

use App\Models\Feed;
use App\Models\FeedingRecord;
use App\Models\Livestock;
use App\Models\Pen;
use App\Models\FeedPurchase;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeedRequest;
use App\Http\Resources\FeedResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Imports\FeedImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\ValidationException;

class FeedController extends Controller
{
    /**
     * Display a listing of feeds with optional filters and pagination.
     * Default hanya menampilkan pakan yang aktif (is_active = true).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Feed::query();

            // Filter berdasarkan kategori
            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            // Filter berdasarkan status aktif
            // Jika parameter show_inactive=true, tampilkan semua; default hanya aktif
            if ($request->has('show_inactive') && $request->boolean('show_inactive')) {
                // Tidak perlu filter tambahan, tampilkan semua
            } else {
                $query->where('is_active', true);
            }

            // Filter manual is_active (jika dikirim)
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $feeds = $query->paginate($request->input('per_page', 15));

            // Hitung low stock hanya dari pakan aktif
            $lowStockCount = Feed::where('is_active', true)
                ->where('current_stock', '<', 100)
                ->count();

            $totalStockValue = Feed::where('is_active', true)
                ->select(DB::raw('SUM(current_stock * price_per_kg) as total'))
                ->value('total') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'feed_types' => FeedResource::collection($feeds),
                    'total_types' => Feed::where('is_active', true)->count(),
                    'low_stock_count' => $lowStockCount,
                    'stock_summary' => [
                        'total_stock_kg' => Feed::where('is_active', true)->sum('current_stock'),
                        'total_value' => $totalStockValue,
                    ],
                    'pagination' => [
                        'current_page' => $feeds->currentPage(),
                        'per_page' => $feeds->perPage(),
                        'total' => $feeds->total(),
                        'last_page' => $feeds->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Feed index error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pakan: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created feed.
     *
     * @param  \App\Http\Requests\FeedRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(FeedRequest $request): JsonResponse
    {
        try {
            $feed = Feed::create($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Pakan berhasil ditambahkan.',
                'data' => new FeedResource($feed),
            ], 201);
        } catch (\Exception $exception) {
            Log::error('Store feed error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pakan: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified feed.
     *
     * @param  \App\Models\Feed  $feed
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Feed $feed): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new FeedResource($feed),
            ]);
        } catch (\Exception $exception) {
            Log::error('Show feed error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menampilkan detail pakan.',
            ], 500);
        }
    }

    /**
     * Update the specified feed.
     *
     * @param  \App\Http\Requests\FeedRequest  $request
     * @param  \App\Models\Feed  $feed
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(FeedRequest $request, Feed $feed): JsonResponse
    {
        try {
            $validated = $request->validated();
            $feed->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Pakan berhasil diperbarui.',
                'data' => new FeedResource($feed),
            ]);
        } catch (\Exception $exception) {
            Log::error('Update feed error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pakan: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete the specified feed (set inactive).
     *
     * @param  \App\Models\Feed  $feed
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Feed $feed): JsonResponse
    {
        try {
            $feed->update(['is_active' => false]);
            return response()->json([
                'success' => true,
                'message' => 'Pakan berhasil dinonaktifkan.',
            ]);
        } catch (\Exception $exception) {
            Log::error('Destroy feed error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menonaktifkan pakan.',
            ], 500);
        }
    }

    /**
     * Get stock summary of feeds, including low stock alerts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stockSummary(): JsonResponse
    {
        try {
            $feeds = Feed::where('is_active', true)->get();

            $totalStockValue = Feed::where('is_active', true)
                ->select(DB::raw('SUM(current_stock * price_per_kg) as total'))
                ->value('total') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'feed_types' => FeedResource::collection($feeds),
                    'low_stock_alerts' => $feeds->filter->is_stock_low->values()->map(function ($feed) {
                        return [
                            'id' => $feed->id,
                            'name' => $feed->name,
                            'current_stock' => $feed->current_stock,
                            'category' => $feed->category,
                        ];
                    })->values(),
                    'stock_summary' => [
                        'total_stock_kg' => $feeds->sum('current_stock'),
                        'total_value' => $totalStockValue,
                        'low_stock_count' => $feeds->filter->is_stock_low->count(),
                    ],
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Stock summary error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil ringkasan stok.',
            ], 500);
        }
    }

    /**
     * Get feed requirements based on pen categories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function requirements(): JsonResponse
    {
        try {
            $activeFeeds = Feed::where('is_active', true)->get();
            $feedNames = $activeFeeds->pluck('name')->toArray();

            $penCategoryFeedsMap = [
                'Fattening' => ['Silase', 'Complete Feed Kediri', 'Complete Feed Jember', 'Ampas Tahu', 'Complete Feed Madiun', 'Onggok', 'Jagung', 'LAK 105', 'Nutrifeed'],
                'Fattening Percobaan' => ['Silase', 'Complete Feed Jember', 'Jagung', 'LAK 105', 'Pakchong'],
                'Kawin' => ['Silase', 'Complete Feed Jember', 'Pakchong', 'Jagung', 'Complete Feed Madiun', 'LAK 105', 'Nutrifeed'],
                'Melahirkan' => ['Silase', 'Jagung', 'Complete Feed Jember', 'Complete Feed Madiun', 'LAK 105', 'Pakchong', 'Nutrifeed', 'Crepfeed'],
                'Menyusui' => ['Silase', 'Complete Feed Jember', 'Jagung', 'Complete Feed Madiun', 'Pakchong', 'Nutrifeed', 'LAK 105', 'Crepfeed'],
                'Prasapih' => ['Silase', 'Complete Feed Jember', 'Jagung', 'Complete Feed Madiun', 'Pakchong', 'Crepfeed', 'LAK 105', 'Nutrifeed'],
                'Kambing' => ['Silase', 'Complete Feed Madiun', 'Complete Feed Jember', 'Gembilina', 'Pakchong', 'Nutrifeed', 'LAK 105', 'Pongkol Ketela', 'Jagung', 'Ramban'],
            ];

            $penCategoryFeeds = [];
            foreach ($penCategoryFeedsMap as $category => $feedList) {
                $filteredFeeds = array_intersect($feedList, $feedNames);
                $penCategoryFeeds[$category] = array_values($filteredFeeds);
            }

            $penCategories = Pen::distinct('category')->pluck('category')->toArray();

            $requirements = [];
            $totalDaily = 0;
            $totalCost = 0;

            foreach ($penCategories as $category) {
                $livestockCount = Livestock::whereHas('pen', function ($query) use ($category) {
                    $query->where('category', $category);
                })->where('status', true)->count();

                if ($livestockCount > 0) {
                    $dailyKg = $livestockCount * 1.5;
                    $cost = $dailyKg * 3500;
                    $requirements[$category] = [
                        'livestock_count' => $livestockCount,
                        'daily_kg' => round($dailyKg, 2),
                        'daily_cost' => round($cost, 2),
                        'weekly_kg' => round($dailyKg * 7, 2),
                        'weekly_cost' => round($cost * 7, 2),
                        'monthly_kg' => round($dailyKg * 30, 2),
                        'monthly_cost' => round($cost * 30, 2),
                    ];
                    $totalDaily += $dailyKg;
                    $totalCost += $cost;
                }
            }

            $recentUsage = FeedingRecord::with('feed', 'pen')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($record) {
                    return [
                        'date' => $record->feeding_date->format('Y-m-d'),
                        'feed' => $record->feed->name ?? '-',
                        'pen' => $record->pen->name ?? '-',
                        'quantity_kg' => $record->quantity_kg,
                        'notes' => $record->notes,
                    ];
                });

            // ==================== HITUNG KOMPOSISI HARIAN ====================
            $thirtyDaysAgo = now()->subDays(30);

            $composition = [
                'silase' => 0,
                'cf_jember' => 0,
                'jagung_halus' => 0,
            ];

            $feedingRecords = FeedingRecord::with('feed')
                ->where('feeding_date', '>=', $thirtyDaysAgo)
                ->get();

            foreach ($feedingRecords as $record) {
                $category = $record->feed->category ?? '';
                if ($category === 'silase') {
                    $composition['silase'] += $record->quantity_kg;
                } elseif ($category === 'cf_jember') {
                    $composition['cf_jember'] += $record->quantity_kg;
                } elseif ($category === 'jagung_halus') {
                    $composition['jagung_halus'] += $record->quantity_kg;
                }
            }

            // ==================== HITUNG USAGE BY FEED ====================
            $usageByFeed = FeedingRecord::with('feed')
                ->where('feeding_date', '>=', $thirtyDaysAgo)
                ->select('feed_id', DB::raw('SUM(quantity_kg) as total_kg'))
                ->groupBy('feed_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'feed_name' => $item->feed->name ?? 'Unknown',
                        'total_kg' => round($item->total_kg, 2),
                    ];
                })
                ->sortByDesc('total_kg')
                ->values()
                ->toArray();

            // Bentuk array objek untuk grafik (category + daily_ration_kg)
            $penCategoriesWithRation = [];
            foreach ($penCategories as $category) {
                $dailyRation = isset($requirements[$category]) ? $requirements[$category]['daily_kg'] : 0;
                $penCategoriesWithRation[] = [
                    'category' => $category,
                    'daily_ration_kg' => $dailyRation,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'requirements' => [
                        'daily' => [
                            'total_kg' => round($totalDaily, 2),
                            'cost' => round($totalCost, 2),
                            'composition' => $composition,
                        ],
                        'weekly' => [
                            'total_kg' => round($totalDaily * 7, 2),
                            'cost' => round($totalCost * 7, 2),
                        ],
                        'monthly' => [
                            'total_kg' => round($totalDaily * 30, 2),
                            'cost' => round($totalCost * 30, 2),
                        ],
                    ],
                    'pen_categories' => $penCategoriesWithRation,
                    'pen_category_feeds' => $penCategoryFeeds,
                    'pen_category_requirements' => $requirements,
                    'usage_by_feed' => $usageByFeed,
                    'recent_usage' => $recentUsage,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Feed requirements error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Record feeding activity (decrease stock and create feeding record).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordFeeding(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'feed_id' => 'required|exists:feeds,id',
                'quantity_kg' => 'required|numeric|min:0',
                'pen_id' => 'nullable|exists:pens,id',
                'notes' => 'nullable|string',
            ]);

            $feed = Feed::findOrFail($validated['feed_id']);
            $feed->decrement('current_stock', $validated['quantity_kg']);

            FeedingRecord::create([
                'feed_id' => $validated['feed_id'],
                'quantity_kg' => $validated['quantity_kg'],
                'pen_id' => $validated['pen_id'] ?? null,
                'feeding_date' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pemberian pakan berhasil dicatat.',
                'data' => new FeedResource($feed),
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            Log::error('Record feeding error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat pemberian pakan: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new feeding record (penggunaan pakan harian) via form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFeedingRecord(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'feed_id'      => 'required|exists:feeds,id',
                'quantity_kg'  => 'required|numeric|min:0',
                'pen_id'       => 'nullable|exists:pens,id',
                'feeding_date' => 'required|date',
                'notes'        => 'nullable|string',
            ]);

            $feedingRecord = FeedingRecord::create($validated);

            $feed = Feed::find($validated['feed_id']);
            if ($feed) {
                $feed->decrement('current_stock', $validated['quantity_kg']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pemakaian pakan berhasil dicatat.',
                'data' => $feedingRecord,
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            Log::error('Store feeding record error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Store a new feed purchase record (pengadaan pakan).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFeedPurchase(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date'           => 'required|date',
                'supplier'       => 'nullable|string|max:255',
                'feed_name'      => 'required|string|max:255',
                'price_per_unit' => 'nullable|numeric|min:0',
                'quantity'       => 'required|numeric|min:0',
                'unit'           => 'nullable|string|max:50',
                'notes'          => 'nullable|string',
            ]);

            $purchase = FeedPurchase::create($validated);

            $existingFeed = Feed::where('name', $validated['feed_name'])->first();
            if ($existingFeed) {
                $existingFeed->increment('current_stock', $validated['quantity']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengadaan pakan berhasil dicatat.',
                'data'    => $purchase,
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            Log::error('Store feed purchase error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Import feeds from Excel file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls,csv',
            ]);

            $import = new FeedImport();
            Excel::import($import, $request->file('file'));

            $failures = $import->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi data gagal. Periksa kembali data Anda.',
                    'errors' => $errors
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data pakan berhasil diimpor',
                'imported' => $import->getRowCount(),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $exception->errors()
            ], 422);
        } catch (\Exception $exception) {
            Log::error('Import feed error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Update stock of a feed (menambah stok).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'feed_id' => 'required|exists:feeds,id',
                'add_stock_kg' => 'required|numeric|min:0',
                'price_per_kg' => 'nullable|numeric|min:0',
            ]);

            $feed = Feed::findOrFail($validated['feed_id']);
            $feed->increment('current_stock', $validated['add_stock_kg']);

            if (isset($validated['price_per_kg'])) {
                $feed->update(['price_per_kg' => $validated['price_per_kg']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stok pakan berhasil diperbarui.',
                'data' => new FeedResource($feed),
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $exception->errors()], 422);
        } catch (\Exception $exception) {
            Log::error('Update stock error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui stok: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Get complete feed analytics data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function analytics(): JsonResponse
    {
        try {
            $categories = ['Kawin', 'Fattening', 'Melahirkan', 'Menyusui', 'Prasapih', 'Persiapan Breeding', 'Karantina'];
            $categoryCosts = [];
            foreach ($categories as $category) {
                $cost = FeedingRecord::whereHas('pen', function ($query) use ($category) {
                    $query->where('category', $category);
                })
                    ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                    ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                    ->value('total') ?? 0;
                $categoryCosts[$category] = round($cost, 2);
            }

            $earlyCost = FeedingRecord::where('feeding_date', '<', '2024-01-01')
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $uncategorizedCost = FeedingRecord::whereNull('pen_id')
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $totalOutCost = FeedingRecord::join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $hibahCost = FeedingRecord::where('notes', 'like', '%hibah%')
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $terjualCost = 0;

            $percobaanCost = FeedingRecord::whereHas('pen', function ($query) {
                $query->where('category', 'Fattening Percobaan');
            })
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $monthlyUsage = FeedingRecord::selectRaw('DATE_FORMAT(feeding_date, "%Y-%m") as month, SUM(quantity_kg) as total_kg')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return ['month' => $item->month, 'total_kg' => round($item->total_kg, 2)];
                });

            $feedTypeUsage = FeedingRecord::with('feed')
                ->selectRaw('feed_id, SUM(quantity_kg) as total_kg')
                ->groupBy('feed_id')
                ->get()
                ->map(function ($item) {
                    return ['feed_name' => $item->feed->name ?? 'Unknown', 'total_kg' => round($item->total_kg, 2)];
                })
                ->sortByDesc('total_kg')
                ->values();

            $dailyUsage = FeedingRecord::with('feed', 'pen')
                ->orderBy('feeding_date', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($record) {
                    return [
                        'date' => $record->feeding_date->format('Y-m-d'),
                        'feed_name' => $record->feed->name ?? '-',
                        'quantity_kg' => $record->quantity_kg,
                        'pen_name' => $record->pen->name ?? '-',
                    ];
                });

            $purchases = [];
            $monthlyPurchase = [];

            if (class_exists(FeedPurchase::class) && Schema::hasTable('feed_purchases')) {
                $purchases = FeedPurchase::orderBy('date', 'desc')
                    ->limit(50)
                    ->get()
                    ->map(function ($purchase) {
                        return [
                            'date' => $purchase->date->format('Y-m-d'),
                            'supplier' => $purchase->supplier ?? '-',
                            'feed_name' => $purchase->feed_name,
                            'price_per_unit' => $purchase->price_per_unit,
                            'quantity' => $purchase->quantity,
                            'total_price' => round($purchase->quantity * $purchase->price_per_unit, 2),
                        ];
                    });

                $monthlyPurchase = FeedPurchase::selectRaw('DATE_FORMAT(date, "%Y-%m") as month, SUM(quantity) as total_kg')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get()
                    ->map(function ($item) {
                        return ['month' => $item->month, 'total_kg' => round($item->total_kg, 2)];
                    });
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'category_costs' => $categoryCosts,
                    'early_cost' => round($earlyCost, 2),
                    'uncategorized_cost' => round($uncategorizedCost, 2),
                    'total_out_cost' => round($totalOutCost, 2),
                    'hibah_cost' => round($hibahCost, 2),
                    'terjual_cost' => round($terjualCost, 2),
                    'percobaan_cost' => round($percobaanCost, 2),
                    'monthly_usage' => $monthlyUsage,
                    'feed_type_usage' => $feedTypeUsage,
                    'daily_usage' => $dailyUsage,
                    'purchases' => $purchases,
                    'monthly_purchase' => $monthlyPurchase,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Feed analytics error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data analitik pakan: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Get usage data for feed: per month, per type, daily with filters, and costs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function usageData(Request $request): JsonResponse
    {
        try {
            $monthlyUsage = FeedingRecord::selectRaw('DATE_FORMAT(feeding_date, "%Y-%m") as month, SUM(quantity_kg) as total_kg')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return ['month' => $item->month, 'total_kg' => round($item->total_kg, 2)];
                });

            $feedTypeUsage = FeedingRecord::with('feed')
                ->selectRaw('feed_id, SUM(quantity_kg) as total_kg')
                ->groupBy('feed_id')
                ->get()
                ->map(function ($item) {
                    return ['feed_name' => $item->feed->name ?? 'Unknown', 'total_kg' => round($item->total_kg, 2)];
                })
                ->sortByDesc('total_kg')
                ->values();

            $query = FeedingRecord::with('feed', 'pen');
            if ($request->filled('start_date')) {
                $query->where('feeding_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->where('feeding_date', '<=', $request->end_date);
            }
            if ($request->filled('feed_name')) {
                $query->whereHas('feed', function ($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->feed_name . '%');
                });
            }
            if ($request->filled('category')) {
                $query->whereHas('pen', function ($subQuery) use ($request) {
                    $subQuery->where('category', $request->category);
                });
            }
            $dailyUsage = $query->orderBy('feeding_date', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($record) {
                    return [
                        'date' => $record->feeding_date->format('Y-m-d'),
                        'feed_name' => $record->feed->name ?? '-',
                        'quantity_kg' => $record->quantity_kg,
                        'pen_name' => $record->pen->name ?? '-',
                    ];
                });

            $categories = ['Kawin', 'Fattening', 'Melahirkan', 'Menyusui', 'Prasapih', 'Persiapan Breeding', 'Karantina'];
            $categoryCosts = [];
            foreach ($categories as $category) {
                $cost = FeedingRecord::whereHas('pen', function ($query) use ($category) {
                    $query->where('category', $category);
                })
                    ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                    ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                    ->value('total') ?? 0;
                $categoryCosts[$category] = round($cost, 2);
            }

            $earlyCost = FeedingRecord::where('feeding_date', '<', '2024-01-01')
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $uncategorizedCost = FeedingRecord::whereNull('pen_id')
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $totalOutCost = FeedingRecord::join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $hibahCost = FeedingRecord::where('notes', 'like', '%hibah%')
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            $terjualCost = 0;
            $percobaanCost = FeedingRecord::whereHas('pen', function ($query) {
                $query->where('category', 'Fattening Percobaan');
            })
                ->join('feeds', 'feeding_records.feed_id', '=', 'feeds.id')
                ->selectRaw('SUM(feeding_records.quantity_kg * feeds.price_per_kg) as total')
                ->value('total') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_usage' => $monthlyUsage,
                    'feed_type_usage' => $feedTypeUsage,
                    'daily_usage' => $dailyUsage,
                    'category_costs' => $categoryCosts,
                    'early_cost' => round($earlyCost, 2),
                    'uncategorized_cost' => round($uncategorizedCost, 2),
                    'total_out_cost' => round($totalOutCost, 2),
                    'hibah_cost' => round($hibahCost, 2),
                    'terjual_cost' => round($terjualCost, 2),
                    'percobaan_cost' => round($percobaanCost, 2),
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Feed usage data error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Get procurement data for feed: per month (record count), realisasi with filters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function procurementData(Request $request): JsonResponse
    {
        try {
            if (!Schema::hasTable('feed_purchases')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'monthly_purchase_count' => [],
                        'purchases' => [],
                    ],
                ]);
            }

            $monthlyPurchaseCount = FeedPurchase::selectRaw('DATE_FORMAT(date, "%Y-%m") as month, COUNT(*) as total_records')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return ['month' => $item->month, 'total_records' => $item->total_records];
                });

            $query = FeedPurchase::query();
            if ($request->filled('start_date')) {
                $query->where('date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->where('date', '<=', $request->end_date);
            }
            if ($request->filled('feed_name')) {
                $query->where('feed_name', 'like', '%' . $request->feed_name . '%');
            }

            $purchases = $query->orderBy('date', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($purchase) {
                    return [
                        'date' => $purchase->date->format('Y-m-d'),
                        'supplier' => $purchase->supplier ?? '-',
                        'feed_name' => $purchase->feed_name,
                        'price_per_unit' => $purchase->price_per_unit,
                        'quantity' => $purchase->quantity,
                        'unit' => $purchase->unit,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_purchase_count' => $monthlyPurchaseCount,
                    'purchases' => $purchases,
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Feed procurement data error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_purchase_count' => [],
                    'purchases' => [],
                ],
            ]);
        }
    }
}
