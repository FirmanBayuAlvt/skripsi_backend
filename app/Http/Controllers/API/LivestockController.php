<?php

namespace App\Http\Controllers\API;

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

class LivestockController extends Controller
{
    /**
     * Daftar ternak dengan filter dan paginasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Livestock::with('pen');

            if ($request->filled('pen_id')) {
                $query->where('pen_id', $request->pen_id);
            }
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', (bool) $request->status);
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

            return response()->json([
                'success' => true,
                'data'    => [
                    'livestocks' => LivestockResource::collection($livestocks),
                    'pagination' => [
                        'current_page' => $livestocks->currentPage(),
                        'per_page'     => $livestocks->perPage(),
                        'total'        => $livestocks->total(),
                        'last_page'    => $livestocks->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Livestock index error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Tambah ternak baru.
     *
     * @param  \App\Http\Requests\LivestockRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(LivestockRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                if (!$imageFile->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File gambar tidak valid.',
                    ], 400);
                }

                $path = $imageFile->store('livestock_images', 'public');
                if (!$path) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal menyimpan file gambar.',
                    ], 500);
                }

                $data['image_url'] = url('/storage/' . $path);
            }

            $livestock = Livestock::create($data);

            if ($request->filled('logbook_event')) {
                Logbook::create([
                    'livestock_id' => $livestock->id,
                    'event_date'   => now(),
                    'event_type'   => $request->logbook_event,
                    'description'  => $request->notes ?? 'Ternak baru ditambahkan',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ternak berhasil ditambahkan.',
                'data'    => new LivestockResource($livestock->load('pen')),
            ], 201);
        } catch (ValidationException $validationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validationException->errors(),
            ], 422);
        } catch (\Exception $exception) {
            Log::error('Store livestock error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Detail satu ternak.
     *
     * @param  \App\Models\Livestock  $livestock
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Livestock $livestock): JsonResponse
    {
        $livestock->load('pen', 'weightRecords');
        return response()->json([
            'success' => true,
            'data'    => new LivestockResource($livestock),
        ]);
    }

    /**
     * Update data ternak.
     *
     * @param  \App\Http\Requests\LivestockRequest  $request
     * @param  \App\Models\Livestock  $livestock
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(LivestockRequest $request, Livestock $livestock): JsonResponse
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                if (!$imageFile->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File gambar tidak valid.',
                    ], 400);
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal menyimpan file gambar baru.',
                    ], 500);
                }

                $data['image_url'] = url('/storage/' . $path);
            }

            $livestock->update($data);

            // Catat perubahan status atau kandang
            if ($livestock->wasChanged('status') || $livestock->wasChanged('pen_id')) {
                $eventType = '';
                if ($livestock->wasChanged('pen_id')) {
                    $eventType = 'Pindah Kandang';
                } elseif ($livestock->wasChanged('status')) {
                    $eventType = $livestock->status ? 'Status diubah menjadi aktif' : 'Status diubah menjadi tidak aktif';
                }

                if (!empty($eventType)) {
                    Logbook::create([
                        'livestock_id' => $livestock->id,
                        'event_date'   => now(),
                        'event_type'   => $eventType,
                        'description'  => $request->notes ?? 'Data ternak diperbarui',
                        'new_pen_id'   => $livestock->pen_id,
                    ]);
                }
            }

            if ($request->filled('logbook_event') && $request->logbook_event !== '') {
                Logbook::create([
                    'livestock_id' => $livestock->id,
                    'event_date'   => now(),
                    'event_type'   => $request->logbook_event,
                    'description'  => $request->notes ?? 'Update data ternak',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ternak berhasil diperbarui.',
                'data'    => new LivestockResource($livestock->load('pen')),
            ]);
        } catch (ValidationException $validationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validationException->errors(),
            ], 422);
        } catch (\Exception $exception) {
            Log::error('Update livestock error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Nonaktifkan ternak (soft delete secara logika).
     *
     * @param  \App\Models\Livestock  $livestock
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Livestock $livestock): JsonResponse
    {
        $livestock->update(['status' => false]);

        Logbook::create([
            'livestock_id' => $livestock->id,
            'event_date'   => now(),
            'event_type'   => 'Ternak dinonaktifkan',
            'description'  => 'Status diubah menjadi tidak aktif',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ternak berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Catat berat badan ternak.
     *
     * @param  \App\Http\Requests\RecordWeightRequest  $request
     * @param  \App\Models\Livestock  $livestock
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordWeight(RecordWeightRequest $request, Livestock $livestock): JsonResponse
    {
        $weightRecord = $livestock->weightRecords()->create($request->validated());

        Logbook::create([
            'livestock_id' => $livestock->id,
            'event_date'   => $request->record_date,
            'event_type'   => 'Pencatatan Berat',
            'description'  => 'Berat: ' . $request->weight_kg . ' kg. ' . ($request->notes ?? ''),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berat badan berhasil dicatat.',
            'data'    => new WeightRecordResource($weightRecord),
        ], 201);
    }

    /**
     * Riwayat berat badan ternak.
     *
     * @param  \App\Models\Livestock  $livestock
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function weightHistory(Livestock $livestock, Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->input('per_page', 15));

        $records = $livestock->weightRecords()
            ->orderBy('record_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => WeightRecordResource::collection($records),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
                'last_page'    => $records->lastPage(),
            ],
        ]);
    }

    /**
     * Prediksi pertumbuhan menggunakan ML Service.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Livestock  $livestock
     * @param  \App\Services\MLService  $mlService
     * @return \Illuminate\Http\JsonResponse
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

            $features = [
                'initial_weight'   => (float) $livestock->initial_weight,
                'age_days'         => (int) $livestock->age_days,
                'feed_silase'      => (float) $feedSilase,
                'feed_concentrate' => (float) $feedConcentrate,
                'gender'           => $livestock->gender,
                'pen_category'     => $livestock->pen ? $livestock->pen->category : 'unknown',
            ];

            $prediction = $mlService->predict($features);

            if (!$prediction || !isset($prediction['gain'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Layanan prediksi tidak tersedia atau respons tidak valid.',
                ], 503);
            }

            $livestock->predictions()->create([
                'prediction_days' => (int) $request->input('prediction_days', 30),
                'predicted_gain'  => (float) $prediction['gain'],
                'confidence'      => (float) ($prediction['confidence'] ?? 0.85),
                'input_features'  => $features,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'predicted_final_weight' => (float) ($prediction['predicted_final_weight'] ?? $livestock->initial_weight + $prediction['gain']),
                    'gain'                   => (float) $prediction['gain'],
                    'confidence'             => (float) ($prediction['confidence'] ?? 0.85),
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Prediction error: ' . $exception->getMessage(), [
                'trace'        => $exception->getTraceAsString(),
                'livestock_id' => $livestock->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat prediksi: ' . $exception->getMessage(),
            ], 500);
        }
    }
}
