<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /**
     * Set low stock threshold for feed stock monitoring.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setStockThreshold(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'threshold' => 'required|integer|min:1|max:1000',
            ]);

            $threshold = (int) $validated['threshold'];
            Cache::put('low_stock_threshold', $threshold, now()->addDays(30));

            return response()->json([
                'success'   => true,
                'message'   => 'Threshold stok berhasil diubah.',
                'threshold' => $threshold,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Set stock threshold error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
            ], 500);
        }
    }
}