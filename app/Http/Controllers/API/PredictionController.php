<?php

namespace App\Http\Controllers\API;

use App\Models\Livestock;
use App\Models\Prediction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\PredictionRequest;
use App\Http\Resources\PredictionResource;
use App\Services\MLService;
use Illuminate\Http\JsonResponse;

class PredictionController extends Controller
{
    /**
     * Instance dari MLService untuk melakukan prediksi.
     *
     * @var \App\Services\MLService
     */
    protected $mlService;

    /**
     * Constructor, menginjeksikan MLService.
     *
     * @param \App\Services\MLService $mlService
     */
    public function __construct(MLService $mlService)
    {
        $this->mlService = $mlService;
    }

    /**
     * Menampilkan daftar prediksi dengan paginasi.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Prediction::with('livestock');

        if ($request->filled('livestock_id')) {
            $query->where('livestock_id', $request->livestock_id);
        }

        $predictions = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => [
                'predictions' => PredictionResource::collection($predictions),
                'pagination'  => [
                    'current_page' => $predictions->currentPage(),
                    'per_page'     => $predictions->perPage(),
                    'total'        => $predictions->total(),
                    'last_page'    => $predictions->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Mengambil riwayat prediksi terbaru (tanpa paginasi).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $limit = $request->input('per_page', 5);
        $predictions = Prediction::with('livestock')
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'predictions' => PredictionResource::collection($predictions),
            ],
        ]);
    }

    /**
     * Menjalankan prediksi pertumbuhan bobot ternak.
     *
     * @param \App\Http\Requests\PredictionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function predict(PredictionRequest $request): JsonResponse
    {
        // Ambil data ternak beserta relasi yang diperlukan
        $livestock = Livestock::with(['pen', 'feedingRecords.feed'])
            ->findOrFail($request->livestock_id);

        // Hitung total konsumsi pakan per kategori yang dibutuhkan oleh model ML
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

        // Susun fitur input untuk ML service
        $features = [
            'initial_weight'   => $livestock->initial_weight,
            'age_days'         => $livestock->age_days,
            'feed_silase'      => $feedSilase,
            'feed_concentrate' => $feedConcentrate,
            'gender'           => $livestock->gender,
            'pen_category'     => $livestock->pen ? $livestock->pen->category : 'unknown',
        ];

        // Panggil ML service
        $mlResult = $this->mlService->predict($features);

        // Jika ML service tidak merespon, kembalikan error 503
        if (!$mlResult) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan prediksi tidak tersedia. Pastikan ML Service berjalan.',
            ], 503);
        }

        // Simpan hasil prediksi ke database
        $prediction = Prediction::create([
            'livestock_id'     => $livestock->id,
            'prediction_days'  => $request->prediction_days,
            'predicted_gain'   => $mlResult['gain'],
            'confidence'       => $mlResult['confidence'] ?? 0.85,
            'interval_lower'   => $mlResult['interval']['lower'] ?? ($mlResult['gain'] * 0.9),
            'interval_upper'   => $mlResult['interval']['upper'] ?? ($mlResult['gain'] * 1.1),
            'recommendations'  => $this->generateRecommendations($mlResult, $livestock),
            'input_features'   => $features,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prediksi berhasil dijalankan.',
            'data'    => new PredictionResource($prediction->load('livestock')),
        ]);
    }

    /**
     * Mengembalikan data korelasi (dummy, bisa dikembangkan sesuai kebutuhan).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function correlation(): JsonResponse
    {
        // Data dummy untuk keperluan demonstrasi
        return response()->json([
            'success' => true,
            'data'    => [
                'feed_weight_correlation' => 0.85,
                'factors' => [
                    'Jenis Pakan'      => 0.78,
                    'Kondisi Kandang'  => 0.65,
                    'Kesehatan'        => 0.72,
                    'Umur'             => 0.68,
                ],
                'analysis_period' => '6_bulan_terakhir',
            ],
        ]);
    }

    /**
     * Menghasilkan rekomendasi berdasarkan hasil prediksi ML.
     *
     * @param array $mlResult Hasil dari ML service (harus mengandung key 'gain' dan opsional 'confidence')
     * @param \App\Models\Livestock $livestock
     * @return array
     */
    protected function generateRecommendations(array $mlResult, Livestock $livestock): array
    {
        $recommendations = [];

        // Ambil nilai gain dan confidence dengan default aman
        $gain = $mlResult['gain'] ?? 0;
        $confidence = $mlResult['confidence'] ?? 0;

        // Rekomendasi berdasarkan nilai gain
        if ($gain < 0.1) {
            $recommendations[] = 'Pertumbuhan lambat. Pertimbangkan untuk meningkatkan kualitas pakan.';
        } elseif ($gain > 0.25) {
            $recommendations[] = 'Pertumbuhan sangat baik. Pertahankan manajemen pakan.';
        }

        // Rekomendasi jika confidence rendah
        if ($confidence < 0.7) {
            $recommendations[] = 'Tingkat kepercayaan prediksi rendah. Periksa kelengkapan data (riwayat berat dan pakan).';
        }

        // Jika tidak ada rekomendasi spesifik
        if (empty($recommendations)) {
            $recommendations[] = 'Tidak ada rekomendasi khusus.';
        }

        return $recommendations;
    }
}