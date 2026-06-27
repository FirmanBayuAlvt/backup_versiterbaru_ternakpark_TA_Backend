<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\LivestockController;
use App\Http\Controllers\API\PenController;
use App\Http\Controllers\API\FeedController;
use App\Http\Controllers\API\PredictionController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ChatbotController;
use App\Http\Controllers\API\ProgramController;
use App\Http\Controllers\API\LogbookController;
use App\Http\Controllers\API\HppController;
use App\Http\Controllers\API\NotifikasiController;
use App\Http\Controllers\API\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes - TernakPark Backend
|--------------------------------------------------------------------------
|
| Berikut adalah semua endpoint API untuk backend TernakPark.
| Dibagi menjadi route publik (tanpa autentikasi) dan route protected (auth:sanctum).
|
*/

// ==================== PUBLIC ROUTES (tanpa autentikasi) ====================

// Health check dan test endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});

Route::get('/test', function () {
    return response()->json(['success' => true, 'message' => 'Backend API accessible']);
});

// Authentication
Route::post('/login', [LoginController::class, 'login']);

// Dummy route login untuk menghindari redirect (diperlukan oleh Laravel)
Route::get('/login', function () {
    return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
})->name('login');

// Public feeds endpoints
Route::get('/feeds/requirements', [FeedController::class, 'requirements']);
Route::get('/feeds/stock/summary', [FeedController::class, 'stockSummary']);
Route::post('/chatbot-public', [ChatbotController::class, 'chat']);

// Dashboard endpoints (tanpa auth untuk development)
Route::prefix('/dashboard')->group(function () {
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/pen-analytics', [DashboardController::class, 'penAnalytics']);
    Route::get('/statistics', [DashboardController::class, 'statistics']);
});

// Public routes untuk breeding kawin-ib (daftar, store, detail)
Route::get('/program/breeding/kawin-ib', [ProgramController::class, 'breedingKawinIb']);
Route::post('/program/breeding/kawin-ib/store', [ProgramController::class, 'breedingKawinIbStore']);
Route::get('/program/breeding/kawin-ib/detail', [ProgramController::class, 'breedingKawinIbDetail']);

// Public routes untuk ringkasan kandang
Route::get('/pens/livestock', [PenController::class, 'getLivestockByPen']);

// Public routes untuk penggunaan pakan
Route::get('/feeds/usage-data', [FeedController::class, 'usageData']);
Route::get('/feeds/procurement-data', [FeedController::class, 'procurementData']);

// ==================== PUBLIC PREDICTION ENDPOINT UNTUK CHATBOT ====================
Route::post('/chatbot-predict', function (Request $request) {
    $days = $request->input('prediction_days', 30);

    // Ambil parameter livestock_id atau ear_tag
    $livestockId = $request->input('livestock_id');
    $earTag = $request->input('ear_tag');

    // Jika tidak ada livestock_id tetapi ada ear_tag, cari ID berdasarkan ear_tag
    if (empty($livestockId) && !empty($earTag)) {
        $livestock = \App\Models\Livestock::where('ear_tag', $earTag)->first();
        if ($livestock) {
            $livestockId = $livestock->id;
        } else {
            return response()->json(['success' => false, 'message' => 'Ternak dengan ear tag ' . $earTag . ' tidak ditemukan']);
        }
    }

    // Jika tetap tidak ada livestock_id, return error
    if (empty($livestockId)) {
        return response()->json(['success' => false, 'message' => 'Parameter livestock_id atau ear_tag harus diisi']);
    }

    $livestock = \App\Models\Livestock::find($livestockId);
    if (!$livestock) {
        return response()->json(['success' => false, 'message' => 'Ternak tidak ditemukan']);
    }

    $feedSilase = $livestock->feedingRecords()
        ->whereHas('feed', function ($query) {
            $query->where('category', 'silase');
        })
        ->sum('quantity_kg');

    $feedConcentrate = $livestock->feedingRecords()
        ->whereHas('feed', function ($query) {
            $query->where('category', 'konsentrat');
        })
        ->sum('quantity_kg');

    $features = [
        'initial_weight'   => (float) $livestock->initial_weight,
        'age_days'         => $livestock->age_days,
        'feed_silase'      => (float) $feedSilase,
        'feed_concentrate' => (float) $feedConcentrate,
        'gender'           => $livestock->gender,
        'pen_category'     => $livestock->pen ? $livestock->pen->category : 'unknown',
    ];

    $mlResult = app(\App\Services\MLService::class)->predict($features);
    if (!$mlResult) {
        return response()->json(['success' => false, 'message' => 'ML service tidak tersedia']);
    }

    $predictedGain = $mlResult['gain'];
    $confidence = $mlResult['confidence'] ?? 0.85;
    $currentWeight = $livestock->current_weight;

    return response()->json([
        'success' => true,
        'data' => [
            'predicted_gain'   => $predictedGain,
            'predicted_weight' => round($currentWeight + $predictedGain, 2),
            'confidence'       => $confidence,
            'current_weight'   => $currentWeight,
        ],
    ]);
});

// ==================== PUBLIC ROUTES TAMBAHAN UNTUK CHATBOT ====================

// Public version of livestocks list (tanpa pagination, 10 data)
Route::get('/livestocks/public', function () {
    $livestocks = \App\Models\Livestock::where('status', true)->limit(10)->get();

    $result = [];
    foreach ($livestocks as $livestock) {
        $result[] = [
            'id'             => $livestock->id,
            'ear_tag'        => $livestock->ear_tag,
            'breed_type'     => $livestock->breed_type,
            'current_weight' => $livestock->current_weight,
            'pen_id'         => $livestock->pen_id,
        ];
    }

    return response()->json($result);
});

// Public version of livestock detail by ear tag
Route::get('/livestock/detail/{ear_tag}', function ($earTag) {
    $livestock = \App\Models\Livestock::with('pen')->where('ear_tag', $earTag)->first();
    if (!$livestock) {
        return response()->json(['success' => false, 'message' => 'Ternak tidak ditemukan']);
    }
    return response()->json(['success' => true, 'data' => $livestock]);
});

// Public version of reports performance (tanpa autentikasi)
Route::get('/reports/performance/public', function () {
    try {
        $livestocks = \App\Models\Livestock::all();
        $totalAdg = 0;
        foreach ($livestocks as $livestock) {
            $totalAdg += $livestock->average_daily_gain;
        }
        $count = $livestocks->count();
        $avgAdg = $count > 0 ? round($totalAdg / $count, 3) : 0;

        $totalFeed = \App\Models\FeedingRecord::sum('quantity_kg');
        $totalWeightGain = 0;
        foreach ($livestocks as $livestock) {
            $totalWeightGain += max(0, $livestock->current_weight - $livestock->initial_weight);
        }
        $fcr = $totalWeightGain > 0 ? round($totalFeed / $totalWeightGain, 2) : 0;

        $dead = \App\Models\Livestock::where('status', false)->count();
        $total = $livestocks->count();
        $mortalityRate = $total > 0 ? round(($dead / $total) * 100, 2) : 0;

        $totalCapacity = \App\Models\Pen::sum('capacity');
        $totalOccupancy = \App\Models\Livestock::where('status', true)->count();
        $occupancyRate = $totalCapacity > 0 ? round(($totalOccupancy / $totalCapacity) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'average_daily_gain'    => $avgAdg,
                'feed_conversion_ratio' => $fcr,
                'mortality_rate'        => $mortalityRate,
                'occupancy_rate'        => $occupancyRate,
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// Public version of breeding data (ringkasan breeding)
Route::get('/program/breeding/public', function () {
    try {
        $totalSemuaTernak = \App\Models\Livestock::count();

        $qtyIndukan = \App\Models\Livestock::where('gender', 'female')
            ->where('status', true)
            ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
            ->count();

        $qtyPejantan = \App\Models\Livestock::where('gender', 'male')
            ->where('status', true)
            ->whereRaw('DATEDIFF(CURDATE(), birth_date) > 365')
            ->count();

        $qtyAnakan = \App\Models\Livestock::where('status', true)
            ->whereRaw('DATEDIFF(CURDATE(), birth_date) < 365')
            ->count();

        $totalBreedingOverall = $qtyIndukan + $qtyPejantan + $qtyAnakan;

        return response()->json([
            'success' => true,
            'data' => [
                'total_overall'      => $totalBreedingOverall,
                'total_semua_ternak' => $totalSemuaTernak,
                'qty_anakan'         => $qtyAnakan,
                'qty_indukan'        => $qtyIndukan,
                'qty_pejantan'       => $qtyPejantan,
            ],
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// ==================== PROTECTED ROUTES (memerlukan token Sanctum) ====================
Route::middleware('auth:sanctum')->group(function () {

    // Autentikasi
    Route::post('/logout', [LoginController::class, 'logout']);

    // Manajemen ternak (livestock)
    Route::apiResource('/livestocks', LivestockController::class);
    Route::post('/livestocks/{livestock}/record-weight', [LivestockController::class, 'recordWeight']);
    Route::get('/livestocks/{livestock}/weight-history', [LivestockController::class, 'weightHistory']);
    Route::post('/livestocks/{livestock}/predict', [LivestockController::class, 'predictGrowth']);
    Route::post('/livestocks/import', [LivestockController::class, 'import']);

    // Manajemen kandang (pen)
    Route::apiResource('/pens', PenController::class);
    Route::get('/pens/{pen}/analytics', [PenController::class, 'analytics']);
    Route::post('/pens/import', [PenController::class, 'import']);

    // Manajemen pakan (feed) – custom routes harus didefinisikan SEBELUM apiResource
    Route::get('/feeds/analytics', [FeedController::class, 'analytics']);
    Route::post('/feeds/record-feeding', [FeedController::class, 'recordFeeding']);
    Route::post('/feeds/update-stock', [FeedController::class, 'updateStock']);
    Route::post('/feeds/import', [FeedController::class, 'import']);
    Route::get('/feeds/requirements', [FeedController::class, 'requirements']);
    Route::get('/feeds/stock/summary', [FeedController::class, 'stockSummary']);
    Route::get('/feeds/usage-data', [FeedController::class, 'usageData']);
    Route::get('/feeds/procurement-data', [FeedController::class, 'procurementData']);
    Route::post('/feeds/feeding-record', [FeedController::class, 'storeFeedingRecord']);
    Route::post('/feeds/purchase-record', [FeedController::class, 'storeFeedPurchase']);
    // API resource untuk feeds (harus setelah custom routes)
    Route::apiResource('/feeds', FeedController::class);

    // Prediksi (endpoint protected)
    Route::prefix('/predictions')->group(function () {
        Route::get('/', [PredictionController::class, 'index']);
        Route::get('/history', [PredictionController::class, 'history']);
        Route::get('/correlation', [PredictionController::class, 'correlation']);
        Route::post('/', [PredictionController::class, 'predict']);
    });

    // Program (Fattening & Breeding)
    Route::prefix('/program')->group(function () {
        // Fattening
        Route::get('/fattening', [ProgramController::class, 'fattening']);
        Route::get('/fattening-detailed', [ProgramController::class, 'fatteningDetailed']);
        Route::get('/fattening-timbang', [ProgramController::class, 'fatteningTimbang']);
        Route::get('/fattening-adg-fcr', [ProgramController::class, 'fatteningAdgFcr']);

        // Breeding umum
        Route::get('/breeding', [ProgramController::class, 'breeding']);
        Route::get('/family', [ProgramController::class, 'getFamily']);

        // Sub-modul breeding (tabel utama)
        Route::get('/breeding/induk', [ProgramController::class, 'breedingInduk']);
        Route::get('/breeding/jantan', [ProgramController::class, 'breedingJantan']);

        // Sub-modul breeding (halaman detail)
        Route::get('/breeding/indukan', [ProgramController::class, 'breedingIndukan']);
        Route::get('/breeding/indukan/detail', [ProgramController::class, 'breedingIndukanDetail']);
        Route::get('/breeding/pejantan', [ProgramController::class, 'breedingPejantan']);
        Route::get('/breeding/anakan', [ProgramController::class, 'breedingAnakan']);
    });

    // Logbook
    Route::get('/logbook', [LogbookController::class, 'index']);
    Route::post('/logbook', [LogbookController::class, 'store']);

    // HPP (Harga Pokok Produksi)
    Route::get('/hpp', [HppController::class, 'index']);
    Route::put('/hpp/{id}', [HppController::class, 'update']);

    // Chatbot (dengan autentikasi)
    Route::post('/chatbot', [ChatbotController::class, 'chat']);

    // Notifikasi (notifications)
    Route::prefix('/notifications')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index']);
        Route::get('/unread-count', [NotifikasiController::class, 'unreadCount']);
        Route::post('/{id}/mark-as-read', [NotifikasiController::class, 'markAsRead']);
        Route::post('/mark-all-as-read', [NotifikasiController::class, 'markAllAsRead']);
    });

    // Laporan (reports)
    Route::prefix('/reports')->group(function () {
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/performance', [ReportController::class, 'performance']);
        Route::get('/growth', [ReportController::class, 'growth']);
    });
});

// ==================== FALLBACK UNTUK ENDPOINT YANG TIDAK DIKENAL ====================
Route::fallback(function () {
    return response()->json(['success' => false, 'message' => 'Endpoint not found'], 404);
});