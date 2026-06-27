<?php

namespace App\Http\Controllers\API;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LivestockRequest;
use App\Http\Requests\RecordWeightRequest;
use App\Http\Resources\LivestockResource;
use App\Http\Resources\WeightRecordResource;
use App\Models\Livestock;
use App\Models\Logbook;
use App\Services\MLService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\LivestockImport;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class LivestockController extends Controller
{
    /**
     * Menampilkan daftar ternak dengan filter dan paginasi.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Livestock::with('pen');

            if ($request->filled('pen_id')) {
                $query->where('pen_id', $request->pen_id);
            }

            if ($request->has('status') && $request->status !== '') {
                if ($request->status === 'active') {
                    $query->where('status', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('status', false);
                }
            } else {
                $query->where('status', true);
            }

            if ($request->filled('breed_type')) {
                $query->where('breed_type', $request->breed_type);
            }

            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->where('ear_tag', 'like', '%' . $searchTerm . '%')
                             ->orWhere('notes', 'like', '%' . $searchTerm . '%');
                });
            }

            $perPage = max(1, (int) $request->input('per_page', 15));
            $livestocks = $query->paginate($perPage);

            $livestocksData = array();
            foreach ($livestocks as $livestock) {
                $latestWeight = $livestock->weightRecords()->latest('record_date')->first();
                $currentWeight = $latestWeight ? (float) $latestWeight->weight_kg : (float) $livestock->initial_weight;

                $ageDays = 0;
                if ($livestock->birth_date) {
                    $ageDays = $livestock->birth_date->diffInDays(now());
                }

                $birthDate = $livestock->birth_date ? $livestock->birth_date->format('Y-m-d') : null;
                $dateIn = $livestock->date_in ? $livestock->date_in->format('Y-m-d') : null;

                $livestocksData[] = array(
                    'id'             => $livestock->id,
                    'ear_tag'        => $livestock->ear_tag,
                    'breed_type'     => $livestock->breed_type,
                    'gender'         => $livestock->gender,
                    'birth_date'     => $birthDate,
                    'initial_weight' => (float) $livestock->initial_weight,
                    'current_weight' => $currentWeight,
                    'health_status'  => $livestock->health_status,
                    'notes'          => $livestock->notes,
                    'status'         => (bool) $livestock->status,
                    'image_url'      => $livestock->image_url,
                    'age_days'       => (int) $ageDays,
                    'condition'      => $livestock->condition,
                    'pen' => $livestock->pen ? array(
                        'id'   => $livestock->pen->id,
                        'name' => $livestock->pen->name,
                    ) : null,
                );
            }

            return response()->json(array(
                'success' => true,
                'data'    => array(
                    'livestocks' => $livestocksData,
                    'pagination' => array(
                        'current_page' => $livestocks->currentPage(),
                        'per_page'     => $livestocks->perPage(),
                        'total'        => $livestocks->total(),
                        'last_page'    => $livestocks->lastPage(),
                    ),
                ),
            ));
        } catch (\Exception $exception) {
            Log::error('Livestock index error: ' . $exception->getMessage(), array(
                'trace' => $exception->getTraceAsString(),
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine(),
            ));
            return response()->json(array(
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $exception->getMessage()
            ), 500);
        }
    }

    /**
     * Menambahkan data ternak baru.
     *
     * @param LivestockRequest $request
     * @return JsonResponse
     */
    public function store(LivestockRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('image')) {
                try {
                    $imageFile = $request->file('image');
                    if (!$imageFile->isValid()) {
                        return response()->json(array(
                            'success' => false,
                            'message' => 'File gambar tidak valid.',
                        ), 400);
                    }

                    $path = $imageFile->store('livestock_images', 'public');
                    if (!$path) {
                        return response()->json(array(
                            'success' => false,
                            'message' => 'Gagal menyimpan file gambar.',
                        ), 500);
                    }

                    $data['image_url'] = url('/storage/' . $path);
                } catch (\Exception $e) {
                    Log::error('Upload image error in store: ' . $e->getMessage());
                    return response()->json(array(
                        'success' => false,
                        'message' => 'Error upload gambar: ' . $e->getMessage(),
                    ), 500);
                }
            }

            $livestock = Livestock::create($data);

            if ($request->filled('logbook_event')) {
                try {
                    Logbook::create(array(
                        'livestock_id' => $livestock->id,
                        'event_date'   => now(),
                        'event_type'   => $request->logbook_event,
                        'description'  => $request->notes ?? 'Ternak baru ditambahkan',
                    ));
                } catch (\Exception $e) {
                    Log::error('Gagal mencatat logbook saat store: ' . $e->getMessage(), array(
                        'livestock_id' => $livestock->id,
                    ));
                }
            }

            return response()->json(array(
                'success' => true,
                'message' => 'Ternak berhasil ditambahkan.',
                'data'    => new LivestockResource($livestock->load('pen')),
            ), 201);
        } catch (ValidationException $validationException) {
            return response()->json(array(
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validationException->errors(),
            ), 422);
        } catch (\Exception $exception) {
            Log::error('Store livestock error: ' . $exception->getMessage(), array('trace' => $exception->getTraceAsString()));
            return response()->json(array(
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $exception->getMessage(),
            ), 500);
        }
    }

    /**
     * Menampilkan detail satu ternak.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $livestock = Livestock::with('pen', 'weightRecords')->find($id);
            if ($livestock === null) {
                return response()->json(array(
                    'success' => false,
                    'message' => 'Data ternak tidak ditemukan.'
                ), 404);
            }

            $latestWeightRecord = $livestock->weightRecords->sortByDesc('record_date')->first();
            if ($latestWeightRecord !== null) {
                $currentWeight = (float) $latestWeightRecord->weight_kg;
                $lastWeightDate = $latestWeightRecord->record_date->toDateString();
            } else {
                $currentWeight = (float) $livestock->initial_weight;
                $lastWeightDate = null;
            }

            $ageDays = 0;
            if ($livestock->birth_date !== null) {
                $ageDays = $livestock->birth_date->diffInDays(now());
            }

            $birthDate = $livestock->birth_date !== null ? $livestock->birth_date->format('Y-m-d') : null;
            $dateIn = $livestock->date_in !== null ? $livestock->date_in->format('Y-m-d') : null;
            $dateOfDeathOrSold = $livestock->date_of_death_or_sold !== null ? $livestock->date_of_death_or_sold->format('Y-m-d') : null;

            $weightRecordsArray = array();
            foreach ($livestock->weightRecords->sortByDesc('record_date') as $record) {
                $weightRecordsArray[] = array(
                    'id'          => $record->id,
                    'record_date' => $record->record_date->format('Y-m-d'),
                    'weight_kg'   => (float) $record->weight_kg,
                    'notes'       => $record->notes,
                );
            }

            $penData = null;
            if ($livestock->pen !== null) {
                $penData = array(
                    'id'       => $livestock->pen->id,
                    'name'     => $livestock->pen->name,
                    'category' => $livestock->pen->category,
                );
            }

            $data = array(
                'id'                     => $livestock->id,
                'ear_tag'                => $livestock->ear_tag,
                'breed_type'             => $livestock->breed_type,
                'gender'                 => $livestock->gender,
                'birth_date'             => $birthDate,
                'age_days'               => $ageDays,
                'initial_weight'         => (float) $livestock->initial_weight,
                'current_weight'         => $currentWeight,
                'health_status'          => $livestock->health_status,
                'notes'                  => $livestock->notes,
                'status'                 => (bool) $livestock->status,
                'image_url'              => $livestock->image_url,
                'condition'              => $livestock->condition,
                'date_in'                => $dateIn,
                'day_on_farm'            => $livestock->day_on_farm,
                'reproductive_age'       => $livestock->reproductive_age,
                'date_of_death_or_sold'  => $dateOfDeathOrSold,
                'father_ear_tag'         => $livestock->father_ear_tag,
                'mother_ear_tag'         => $livestock->mother_ear_tag,
                'last_weight_date'       => $lastWeightDate,
                'pen'                    => $penData,
                'weight_records'         => $weightRecordsArray,
            );

            return response()->json(array(
                'success' => true,
                'data'    => $data,
            ));
        } catch (\Exception $exception) {
            Log::error('Livestock show error: ' . $exception->getMessage(), array(
                'trace' => $exception->getTraceAsString(),
                'id'    => $id,
            ));
            return response()->json(array(
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $exception->getMessage(),
            ), 500);
        }
    }

    /**
     * Memperbarui data ternak yang sudah ada.
     *
     * @param LivestockRequest $request
     * @param Livestock $livestock
     * @return JsonResponse
     */
    public function update(LivestockRequest $request, Livestock $livestock): JsonResponse
    {
        try {
            $data = $request->validated();

            if (array_key_exists('status', $data)) {
                $data['status'] = (bool) $data['status'];
            }

            // Handle upload gambar
            if ($request->hasFile('image')) {
                try {
                    $imageFile = $request->file('image');
                    if (!$imageFile->isValid()) {
                        return response()->json(array(
                            'success' => false,
                            'message' => 'File gambar tidak valid.',
                        ), 400);
                    }

                    if ($livestock->image_url) {
                        $oldPath = str_replace(url('/storage/'), '', $livestock->image_url);
                        $oldPath = ltrim($oldPath, '/');
                        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }

                    $path = $imageFile->store('livestock_images', 'public');
                    if (!$path) {
                        return response()->json(array(
                            'success' => false,
                            'message' => 'Gagal menyimpan file gambar baru.',
                        ), 500);
                    }
                    $data['image_url'] = url('/storage/' . $path);
                } catch (\Exception $e) {
                    Log::error('Upload image error in update: ' . $e->getMessage());
                    return response()->json(array(
                        'success' => false,
                        'message' => 'Error upload gambar: ' . $e->getMessage(),
                    ), 500);
                }
            }

            $oldPenId   = $livestock->pen_id;
            $oldEarTag  = $livestock->ear_tag;
            $oldStatus  = $livestock->status;
            $oldHealthStatus = $livestock->health_status;

            $livestock->update($data);
            $livestock->refresh();
            $livestock->load('pen');

            $newPenId   = $livestock->pen_id;
            $newEarTag  = $livestock->ear_tag;
            $newStatus  = $livestock->status;
            $newPenCategory = $livestock->pen ? $livestock->pen->category : null;
            $newHealthStatus = $livestock->health_status;

            if ($oldHealthStatus !== $newHealthStatus && in_array($newHealthStatus, array('fair', 'poor'))) {
                NotificationHelper::sendToAdmins(
                    'Peringatan Kesehatan',
                    "Ternak {$livestock->ear_tag} memiliki status kesehatan {$newHealthStatus}. Segera periksa.",
                    'warning',
                    array('livestock_id' => $livestock->id)
                );
            }

            $autoEventMap = array(
                'Karantina'  => 'Sakit',
                'Melahirkan' => 'Melahirkan',
                'Kawin'      => 'Kawin',
                'Menyusui'   => 'Menyusui',
                'Prasapih'   => 'Lepas Sapih',
            );

            // Catat perubahan ear tag
            if ($oldEarTag != $newEarTag) {
                try {
                    Logbook::create(array(
                        'livestock_id' => $livestock->id,
                        'event_date'   => now(),
                        'event_type'   => 'Ganti Tag',
                        'description'  => $request->notes ?? 'Perubahan ear tag dari ' . ($oldEarTag ?? 'null') . ' menjadi ' . ($newEarTag ?? 'null'),
                        'new_tag'      => $newEarTag,
                    ));
                } catch (\Exception $e) {
                    Log::error('Gagal mencatat logbook ganti tag: ' . $e->getMessage(), array(
                        'livestock_id' => $livestock->id,
                        'old_ear_tag' => $oldEarTag,
                        'new_ear_tag' => $newEarTag,
                    ));
                }
            }

            // Catat perubahan kandang (PENTING: ini yang sering error)
            if ($oldPenId != $newPenId) {
                try {
                    Logbook::create(array(
                        'livestock_id'     => $livestock->id,
                        'event_date'       => now(),
                        'event_type'       => 'Pindah Kandang',
                        'description'      => $request->notes ?? 'Data ternak diperbarui',
                        'new_pen_id'       => $newPenId,
                        'new_pen_category' => $newPenCategory,
                    ));
                } catch (\Exception $e) {
                    Log::error('Gagal mencatat logbook pindah kandang: ' . $e->getMessage(), array(
                        'livestock_id' => $livestock->id,
                        'old_pen_id' => $oldPenId,
                        'new_pen_id' => $newPenId,
                    ));
                    // Tidak perlu return error, karena data kandang sudah berubah
                }

                // Auto event berdasarkan kategori kandang baru
                if ($newPenCategory && isset($autoEventMap[$newPenCategory])) {
                    $autoEvent = $autoEventMap[$newPenCategory];
                    $userSelectedSame = ($request->filled('logbook_event') && $request->logbook_event === $autoEvent);
                    if (!$userSelectedSame) {
                        try {
                            Logbook::create(array(
                                'livestock_id' => $livestock->id,
                                'event_date'   => now(),
                                'event_type'   => $autoEvent,
                                'description'  => $request->notes ?? 'Ternak dipindahkan ke kandang ' . $newPenCategory,
                            ));
                        } catch (\Exception $e) {
                            Log::error('Gagal mencatat auto event logbook: ' . $e->getMessage());
                        }
                    }
                }
            }

            // Catat perubahan status
            if ($oldStatus != $newStatus) {
                try {
                    $statusText = $newStatus ? 'aktif' : 'tidak aktif';
                    Logbook::create(array(
                        'livestock_id' => $livestock->id,
                        'event_date'   => now(),
                        'event_type'   => 'Perubahan Status',
                        'description'  => 'Status ternak diubah menjadi ' . $statusText,
                    ));
                } catch (\Exception $e) {
                    Log::error('Gagal mencatat logbook perubahan status: ' . $e->getMessage(), array(
                        'livestock_id' => $livestock->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ));
                }

                if ($oldStatus && !$newStatus && !$request->filled('logbook_event')) {
                    $livestock->condition = 'tidak aktif';
                    $livestock->date_of_death_or_sold = now();
                    $livestock->save();
                }
            }

            // Catat event dari dropdown logbook (jika ada)
            if ($request->filled('logbook_event') && $request->logbook_event !== '') {
                $eventType = $request->logbook_event;
                $autoEvent = $newPenCategory ? ($autoEventMap[$newPenCategory] ?? null) : null;
                $alreadyAuto = ($autoEvent && $eventType === $autoEvent);
                if (!$alreadyAuto) {
                    try {
                        Logbook::create(array(
                            'livestock_id' => $livestock->id,
                            'event_date'   => now(),
                            'event_type'   => $eventType,
                            'description'  => $request->notes ?? 'Update data ternak',
                        ));
                    } catch (\Exception $e) {
                        Log::error('Gagal mencatat logbook event dari dropdown: ' . $e->getMessage(), array(
                            'livestock_id' => $livestock->id,
                            'event_type' => $eventType,
                        ));
                    }
                }

                if (in_array($eventType, array('Mati', 'Terjual', 'Disembelih'))) {
                    $livestock->status = false;
                    $livestock->condition = strtolower($eventType);
                    $livestock->date_of_death_or_sold = now();
                    $livestock->save();
                    Log::info('Livestock status updated to inactive due to event', array(
                        'livestock_id' => $livestock->id,
                        'event' => $eventType,
                        'condition' => strtolower($eventType)
                    ));
                }
            }

            return response()->json(array(
                'success' => true,
                'message' => 'Ternak berhasil diperbarui.',
                'data'    => new LivestockResource($livestock->load('pen')),
            ));
        } catch (ValidationException $validationException) {
            return response()->json(array(
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validationException->errors(),
            ), 422);
        } catch (\Exception $exception) {
            Log::error('Update livestock error: ' . $exception->getMessage(), array(
                'trace'        => $exception->getTraceAsString(),
                'livestock_id' => $livestock->id,
            ));
            return response()->json(array(
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: ' . $exception->getMessage(),
            ), 500);
        }
    }

    /**
     * Menghapus ternak secara permanen (hard delete).
     *
     * @param Livestock $livestock
     * @return JsonResponse
     */
    public function destroy(Livestock $livestock): JsonResponse
    {
        try {
            $livestock->weightRecords()->delete();
            $livestock->predictions()->delete();
            $livestock->feedingRecords()->delete();
            $livestock->logbooks()->delete();
            if ($livestock->hppDetail) {
                $livestock->hppDetail()->delete();
            }

            if ($livestock->image_url) {
                $oldPath = str_replace(url('/storage/'), '', $livestock->image_url);
                $oldPath = ltrim($oldPath, '/');
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $livestock->delete();

            return response()->json(array(
                'success' => true,
                'message' => 'Ternak berhasil dihapus secara permanen.',
            ));
        } catch (\Exception $exception) {
            Log::error('Destroy livestock error: ' . $exception->getMessage(), array(
                'trace'        => $exception->getTraceAsString(),
                'livestock_id' => $livestock->id,
            ));
            return response()->json(array(
                'success' => false,
                'message' => 'Gagal menghapus ternak: ' . $exception->getMessage(),
            ), 500);
        }
    }

    /**
     * Mencatat berat badan ternak.
     *
     * @param RecordWeightRequest $request
     * @param Livestock $livestock
     * @return JsonResponse
     */
    public function recordWeight(RecordWeightRequest $request, Livestock $livestock): JsonResponse
    {
        $weightRecord = $livestock->weightRecords()->create($request->validated());

        try {
            Logbook::create(array(
                'livestock_id' => $livestock->id,
                'event_date'   => $request->record_date,
                'event_type'   => 'Pencatatan Berat',
                'description'  => 'Berat: ' . $request->weight_kg . ' kg. ' . ($request->notes ?? ''),
            ));
        } catch (\Exception $e) {
            Log::error('Gagal mencatat logbook untuk pencatatan berat: ' . $e->getMessage(), array(
                'livestock_id' => $livestock->id,
            ));
        }

        return response()->json(array(
            'success' => true,
            'message' => 'Berat badan berhasil dicatat.',
            'data'    => new WeightRecordResource($weightRecord),
        ), 201);
    }

    /**
     * Menampilkan riwayat berat badan ternak.
     *
     * @param Livestock $livestock
     * @param Request $request
     * @return JsonResponse
     */
    public function weightHistory(Livestock $livestock, Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->input('per_page', 15));

        $records = $livestock->weightRecords()
            ->orderBy('record_date', 'desc')
            ->paginate($perPage);

        return response()->json(array(
            'success' => true,
            'data'    => WeightRecordResource::collection($records),
            'pagination' => array(
                'current_page' => $records->currentPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
                'last_page'    => $records->lastPage(),
            ),
        ));
    }

    /**
     * Melakukan prediksi pertumbuhan bobot menggunakan ML Service.
     *
     * @param Request $request
     * @param Livestock $livestock
     * @param MLService $mlService
     * @return JsonResponse
     */
    public function predictGrowth(Request $request, Livestock $livestock, MLService $mlService): JsonResponse
    {
        try {
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

            $features = array(
                'initial_weight'   => (float) $livestock->initial_weight,
                'age_days'         => (int) $livestock->age_days,
                'feed_silase'      => (float) $feedSilase,
                'feed_concentrate' => (float) $feedConcentrate,
                'gender'           => $livestock->gender,
                'pen_category'     => $livestock->pen ? $livestock->pen->category : 'unknown',
            );

            $prediction = $mlService->predict($features);

            if (!$prediction || !isset($prediction['gain'])) {
                return response()->json(array(
                    'success' => false,
                    'message' => 'Layanan prediksi tidak tersedia atau respons tidak valid.',
                ), 503);
            }

            $livestock->predictions()->create(array(
                'prediction_days' => (int) $request->input('prediction_days', 30),
                'predicted_gain'  => (float) $prediction['gain'],
                'confidence'      => (float) ($prediction['confidence'] ?? 0.85),
                'input_features'  => $features,
            ));

            return response()->json(array(
                'success' => true,
                'data'    => array(
                    'predicted_final_weight' => (float) ($prediction['predicted_final_weight'] ?? $livestock->initial_weight + $prediction['gain']),
                    'gain'                   => (float) $prediction['gain'],
                    'confidence'             => (float) ($prediction['confidence'] ?? 0.85),
                ),
            ));
        } catch (\Exception $exception) {
            Log::error('Prediction error: ' . $exception->getMessage(), array(
                'trace'        => $exception->getTraceAsString(),
                'livestock_id' => $livestock->id,
            ));
            return response()->json(array(
                'success' => false,
                'message' => 'Terjadi kesalahan saat prediksi: ' . $exception->getMessage(),
            ), 500);
        }
    }

    /**
     * Mengimpor data ternak dari file Excel atau CSV.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv'
            ]);

            $file = $request->file('file');

            Log::info('Livestock import - Memulai proses impor', [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);

            $import = new LivestockImport();
            Excel::import($import, $file);

            $imported = $import->getRowCount();

            Log::info('Livestock import - Selesai', ['imported' => $imported]);

            if ($imported === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data ternak yang berhasil diimpor. Periksa format file dan data. Lihat log untuk detail lebih lanjut.'
                ], 422);
            }

            return response()->json([
                'success'  => true,
                'message'  => 'Data ternak berhasil diimpor',
                'imported' => $imported
            ]);
        } catch (ExcelValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
            foreach ($failures as $failure) {
                $errorMessages[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
            }
            Log::error('Livestock import - Validasi Excel gagal', ['errors' => $errorMessages]);
            return response()->json([
                'success' => false,
                'message' => 'Validasi data gagal. Periksa kembali data Anda.',
                'errors'  => $errorMessages
            ], 422);
        } catch (\Exception $e) {
            Log::error('Import livestock error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor: ' . $e->getMessage()
            ], 500);
        }
    }
}