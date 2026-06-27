<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use App\Models\Livestock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LogbookController extends Controller
{
    /**
     * Menampilkan daftar logbook dengan filter dan paginasi.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Query utama dengan relasi livestock dan newPen
            $query = Logbook::with(['livestock', 'newPen']);

            // Filter berdasarkan tagging (ear_tag)
            if ($request->filled('tagging')) {
                $tagging = $request->tagging;
                $query->whereHas('livestock', function ($subQuery) use ($tagging) {
                    $subQuery->where('ear_tag', 'like', '%' . $tagging . '%');
                });
            }

            // Filter berdasarkan tanggal mulai
            if ($request->filled('start_date')) {
                $query->whereDate('event_date', '>=', $request->start_date);
            }

            // Filter berdasarkan tanggal akhir
            if ($request->filled('end_date')) {
                $query->whereDate('event_date', '<=', $request->end_date);
            }

            // Filter berdasarkan jenis kejadian (event_type)
            if ($request->filled('kejadian')) {
                $query->where('event_type', $request->kejadian);
            }

            // Paginasi
            $perPage = $request->input('per_page', 20);
            $logs = $query->orderBy('event_date', 'desc')->paginate($perPage);

            // Mapping default penanganan berdasarkan event_type
            $defaultHandlingMap = [
                'Vaksin'          => 'Disuntik vaksin',
                'Pindah Kandang'  => 'Diubah lokasi kandang',
                'Domba Masuk'     => 'Diterima masuk',
                'Ganti Tag'       => 'Diganti ear tag',
                'Sakit'           => 'Diberi obat',
                'Melahirkan'      => 'Ditolong proses kelahiran',
                'Mati'            => 'Dimakamkan',
                'Terjual'         => 'Diserahkan ke pembeli',
                'Lepas Sapih'     => 'Dipisahkan dari induk',
                'Timbang 30 hari' => 'Ditimbang',
                'Timbang 60 hari' => 'Ditimbang',
                'Timbang 90 hari' => 'Ditimbang',
                'Timbang 100 hari'=> 'Ditimbang',
                'Timbang 180 hari'=> 'Ditimbang',
                'Hamil'           => 'Dilakukan pengecekan kebuntingan',
                'Rekam IB'        => 'Diinseminasi buatan',
                'Birahi'          => 'Dicatat masa berahi',
                'Timbang 360 hari'=> 'Ditimbang',
                'Disembelih'      => 'Disembelih',
                'Kawin'           => 'Dikawinkan',
                'Perubahan Status'=> 'Status diubah',
            ];

            // Mapping data untuk frontend
            $data = $logs->map(function ($log) use ($defaultHandlingMap) {
                $handling = $log->handling;
                if (empty($handling) || $handling === '-') {
                    $handling = $defaultHandlingMap[$log->event_type] ?? 'Tidak ada penanganan';
                }

                return [
                    'tanggal_kejadian' => $log->event_date ? $log->event_date->toISOString() : null,
                    'tagging'          => optional($log->livestock)->ear_tag,
                    'jenis_ternak'     => optional($log->livestock)->breed_type,
                    'kandang'          => optional($log->livestock->pen)->name ?? '-',
                    'kelamin'          => optional($log->livestock)->gender,
                    'kejadian'         => $log->event_type,
                    'keterangan'       => $log->description ?? '-',
                    'penanganan'       => $handling,
                    'tag_baru'         => $log->new_tag ?? '-',
                    'kandang_baru'     => optional($log->newPen)->name ?? '-',
                    'kategori_baru'    => $log->new_pen_category ?? '-',
                ];
            });

            return response()->json([
                'success'    => true,
                'data'       => $data,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page'     => $logs->perPage(),
                    'total'        => $logs->total(),
                    'last_page'    => $logs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching logbook data: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data logbook.',
            ], 500);
        }
    }

    /**
     * Menyimpan catatan logbook baru dan melakukan update pada data ternak terkait.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'livestock_id'     => 'required|exists:livestocks,id',
                'event_date'       => 'nullable|date',
                'event_type'       => 'required|string|max:100',
                'description'      => 'nullable|string',
                'handling'         => 'nullable|string',
                'new_tag'          => 'nullable|string|max:50',
                'new_pen_id'       => 'nullable|exists:pens,id',
                'new_pen_category' => 'nullable|string|max:100',
                'officer_name'     => 'nullable|string|max:100',
                'pregnancy_date'   => 'nullable|date',
            ]);

            // Jika event_date tidak diisi, gunakan waktu sekarang (datetime)
            $eventDate = $validated['event_date'] ?? now();
            // Jika event_date hanya berisi tanggal (tanpa waktu), tambahkan waktu sekarang
            if (strlen($eventDate) <= 10) {
                $eventDate = $eventDate . ' ' . now()->format('H:i:s');
            }
            $validated['event_date'] = $eventDate;

            DB::beginTransaction();

            // Buat catatan logbook
            $logbook = Logbook::create($validated);

            // Load relasi untuk response
            $logbook->load(['livestock', 'newPen']);

            // Update data ternak berdasarkan event_type
            $livestock = Livestock::find($validated['livestock_id']);
            if ($livestock) {
                $eventType = $validated['event_type'];
                if (in_array($eventType, ['Mati', 'Terjual', 'Disembelih'])) {
                    // Ubah status menjadi tidak aktif
                    $livestock->status = false;
                    $livestock->date_of_death_or_sold = $validated['event_date'];
                    // Update kondisi sesuai event_type
                    if ($eventType == 'Mati') {
                        $livestock->condition = 'mati';
                    } elseif ($eventType == 'Terjual') {
                        $livestock->condition = 'terjual';
                    } elseif ($eventType == 'Disembelih') {
                        $livestock->condition = 'disembelih';
                    }
                    $livestock->save();
                } elseif ($eventType == 'Ganti Tag' && !empty($validated['new_tag'])) {
                    $livestock->ear_tag = $validated['new_tag'];
                    $livestock->save();
                } elseif ($eventType == 'Pindah Kandang' && !empty($validated['new_pen_id'])) {
                    $livestock->pen_id = $validated['new_pen_id'];
                    $livestock->save();
                }
            }

            DB::commit();

            // Mapping default penanganan untuk response (jika handling kosong)
            $defaultHandlingMap = [
                'Vaksin'          => 'Disuntik vaksin',
                'Pindah Kandang'  => 'Diubah lokasi kandang',
                'Domba Masuk'     => 'Diterima masuk',
                'Ganti Tag'       => 'Diganti ear tag',
                'Sakit'           => 'Diberi obat',
                'Melahirkan'      => 'Ditolong proses kelahiran',
                'Mati'            => 'Dimakamkan',
                'Terjual'         => 'Diserahkan ke pembeli',
                'Lepas Sapih'     => 'Dipisahkan dari induk',
                'Timbang 30 hari' => 'Ditimbang',
                'Timbang 60 hari' => 'Ditimbang',
                'Timbang 90 hari' => 'Ditimbang',
                'Timbang 100 hari'=> 'Ditimbang',
                'Timbang 180 hari'=> 'Ditimbang',
                'Hamil'           => 'Dilakukan pengecekan kebuntingan',
                'Rekam IB'        => 'Diinseminasi buatan',
                'Birahi'          => 'Dicatat masa berahi',
                'Timbang 360 hari'=> 'Ditimbang',
                'Disembelih'      => 'Disembelih',
                'Kawin'           => 'Dikawinkan',
            ];
            $handling = $logbook->handling;
            if (empty($handling) || $handling === '-') {
                $handling = $defaultHandlingMap[$logbook->event_type] ?? 'Tidak ada penanganan';
            }

            $responseData = [
                'tanggal_kejadian' => $logbook->event_date ? $logbook->event_date->toISOString() : null,
                'tagging'          => optional($logbook->livestock)->ear_tag,
                'jenis_ternak'     => optional($logbook->livestock)->breed_type,
                'kandang'          => optional($logbook->livestock->pen)->name ?? '-',
                'kelamin'          => optional($logbook->livestock)->gender,
                'kejadian'         => $logbook->event_type,
                'keterangan'       => $logbook->description ?? '-',
                'penanganan'       => $handling,
                'tag_baru'         => $logbook->new_tag ?? '-',
                'kandang_baru'     => optional($logbook->newPen)->name ?? '-',
                'kategori_baru'    => $logbook->new_pen_category ?? '-',
            ];

            return response()->json([
                'success' => true,
                'message' => 'Catatan logbook berhasil ditambahkan.',
                'data'    => $responseData,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing logbook: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan catatan logbook.',
            ], 500);
        }
    }
}
