<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HppDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HppController extends Controller
{
    /**
     * Get HPP (Harga Pokok Produksi) data for all livestock.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Ambil semua data HPP dengan relasi livestock dan pen (melalui livestock)
            $hpps = HppDetail::with('livestock.pen')->get();

            // Fungsi helper untuk menghitung total HPP dari suatu koleksi HppDetail
            $sumHpp = function ($collection) {
                return $collection->sum(function ($hpp) {
                    return $hpp->purchase_cost + $hpp->feed_cost + $hpp->operational_cost;
                });
            };

            // Total HPP Jantan
            $totalHppJantan = $sumHpp(
                $hpps->filter(function ($hpp) {
                    return $hpp->livestock && $hpp->livestock->gender === 'male';
                })
            );

            // Total HPP Betina
            $totalHppBetina = $sumHpp(
                $hpps->filter(function ($hpp) {
                    return $hpp->livestock && $hpp->livestock->gender === 'female';
                })
            );

            // Jantan Breeding
            $jantanBreeding = $hpps->filter(function ($hpp) {
                return $hpp->livestock &&
                       $hpp->livestock->gender === 'male' &&
                       $hpp->livestock->pen &&
                       $hpp->livestock->pen->category === 'Breeding';
            });
            $qtyJantanBreeding = $jantanBreeding->count();
            $totalHppJantanBreeding = $sumHpp($jantanBreeding);

            // Jantan Fattening
            $jantanFattening = $hpps->filter(function ($hpp) {
                return $hpp->livestock &&
                       $hpp->livestock->gender === 'male' &&
                       $hpp->livestock->pen &&
                       $hpp->livestock->pen->category === 'Fattening';
            });
            $qtyJantanFattening = $jantanFattening->count();
            $totalHppJantanFattening = $sumHpp($jantanFattening);

            // Betina (semua)
            $betina = $hpps->filter(function ($hpp) {
                return $hpp->livestock && $hpp->livestock->gender === 'female';
            });
            $qtyBetina = $betina->count();
            $totalHppBetinaDetail = $sumHpp($betina);

            // Detail per ternak (konversi ke array tanpa indeks asosiatif dari collection)
            // Sertakan id dari hpp_detail agar frontend bisa mengedit
            $detail = $hpps->map(function ($hpp) {
                return [
                    'id'             => $hpp->id,
                    'tagging'        => $hpp->livestock?->ear_tag,
                    'hpp_pembelian'  => $hpp->purchase_cost,
                    'pakan'          => $hpp->feed_cost,
                    'operasional'    => $hpp->operational_cost,
                    'total'          => $hpp->purchase_cost + $hpp->feed_cost + $hpp->operational_cost,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_hpp_jantan'            => $totalHppJantan,
                    'total_hpp_betina'            => $totalHppBetina,
                    'qty_jantan_breeding'         => $qtyJantanBreeding,
                    'total_hpp_jantan_breeding'   => $totalHppJantanBreeding,
                    'qty_jantan_fattening'        => $qtyJantanFattening,
                    'total_hpp_jantan_fattening'  => $totalHppJantanFattening,
                    'qty_betina'                  => $qtyBetina,
                    'total_hpp_betina_detail'     => $totalHppBetinaDetail,
                    'detail'                      => $detail,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('HPP index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data HPP'
            ], 500);
        }
    }

    /**
     * Update HPP data for a specific livestock.
     * Updates purchase_cost, feed_cost, and operational_cost.
     * Also syncs purchase_cost with livestock.purchase_price if changed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  (HppDetail ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $hpp = HppDetail::findOrFail($id);

            $validated = $request->validate([
                'purchase_cost'    => 'nullable|numeric|min:0',
                'feed_cost'        => 'nullable|numeric|min:0',
                'operational_cost' => 'nullable|numeric|min:0',
            ]);

            // Update HppDetail
            $hpp->update($validated);

            // Jika purchase_cost diubah, sinkronkan ke livestock.purchase_price
            if (array_key_exists('purchase_cost', $validated) && $hpp->livestock) {
                $hpp->livestock->update(['purchase_price' => $validated['purchase_cost']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data HPP berhasil diperbarui',
                'data'    => $hpp->fresh(['livestock']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data HPP tidak ditemukan'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('HPP update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data HPP: ' . $e->getMessage()
            ], 500);
        }
    }
}
