<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Livestock;
use App\Models\Pen;
use App\Models\Feed;
use App\Models\Prediction;
use App\Services\MLService;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    protected $mlService;

    public function __construct(MLService $mlService)
    {
        $this->mlService = $mlService;
    }

    public function chat(Request $request)
    {
        try {
            $message = strtolower(trim($request->input('message')));
            if (empty($message)) {
                return response()->json(['reply' => 'Silakan ketik pertanyaan Anda.']);
            }

            Log::info('Chatbot request', ['message' => $message]);

            // Intent detection
            if (str_contains($message, 'prediksi') && str_contains($message, 'bobot')) {
                return $this->handlePredictionIntent($message);
            }
            elseif (str_contains($message, 'formulasi') || str_contains($message, 'ransum')) {
                return $this->handleFeedFormulation($message);
            }
            elseif (str_contains($message, 'total ternak')) {
                return $this->getTotalLivestock();
            }
            elseif (str_contains($message, 'total kandang')) {
                return $this->getTotalPens();
            }
            elseif (str_contains($message, 'stok pakan')) {
                return $this->getFeedStock();
            }
            elseif (str_contains($message, 'prediksi terbaru')) {
                return $this->getRecentPredictions();
            }
            else {
                return response()->json(['reply' => 'Maaf, saya belum mengerti. Coba kata kunci: prediksi, formulasi, total ternak, total kandang, stok pakan.']);
            }
        } catch (\Exception $e) {
            Log::error('Chatbot error: ' . $e->getMessage());
            return response()->json(['reply' => 'Terjadi kesalahan server. Coba lagi nanti.'], 500);
        }
    }

    private function handlePredictionIntent($message)
    {
        preg_match('/prediksi\s+(\w+)/i', $message, $matches);
        if (!isset($matches[1])) {
            return response()->json(['reply' => 'Contoh: "prediksi T001" (ganti T001 dengan ear tag ternak).']);
        }
        $earTag = $matches[1];
        $livestock = Livestock::where('ear_tag', $earTag)->first();
        if (!$livestock) {
            return response()->json(['reply' => "Ternak '{$earTag}' tidak ditemukan."]);
        }
        // Dummy prediksi (karena ML service belum tentu ready)
        $gain = round($livestock->initial_weight * 0.08, 2);
        return response()->json(['reply' => "📈 Prediksi untuk {$earTag}: kenaikan bobot sekitar {$gain} kg dalam 30 hari."]);
    }

    private function handleFeedFormulation($message)
    {
        preg_match('/berat\s+(\d+(?:\.\d+)?)/i', $message, $weightMatch);
        $weight = $weightMatch[1] ?? 30;
        $dailyDryMatter = $weight * 0.03;
        return response()->json(['reply' => "📊 Formulasi pakan untuk domba berat {$weight} kg: butuh BK sekitar {$dailyDryMatter} kg/hari. Kombinasikan hijauan dan konsentrat."]);
    }

    private function getTotalLivestock()
    {
        $total = Livestock::count();
        $aktif = Livestock::where('status', true)->count();
        return response()->json(['reply' => "🐏 Total ternak: {$total} ekor (aktif: {$aktif})."]);
    }

    private function getTotalPens()
    {
        $total = Pen::count();
        $aktif = Pen::where('status', 'active')->count();
        return response()->json(['reply' => "🏚️ Total kandang: {$total} (aktif: {$aktif})."]);
    }

    private function getFeedStock()
    {
        $stock = Feed::sum('current_stock');
        return response()->json(['reply' => "🌾 Total stok pakan: " . round($stock, 2) . " kg."]);
    }

    private function getRecentPredictions()
    {
        $preds = Prediction::latest()->take(3)->get();
        if ($preds->isEmpty()) {
            return response()->json(['reply' => 'Belum ada prediksi tersimpan.']);
        }
        $reply = "📜 3 prediksi terbaru:\n";
        foreach ($preds as $p) {
            $reply .= "- Ternak ID {$p->livestock_id}: +{$p->predicted_gain} kg\n";
        }
        return response()->json(['reply' => $reply]);
    }
}