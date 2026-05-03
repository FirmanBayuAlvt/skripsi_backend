<?php

namespace App\Http\Controllers\API;

use App\Models\Pen;
use App\Models\Livestock;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\PenRequest;
use App\Http\Resources\PenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PenController extends Controller
{
    /**
     * Display a listing of pens with optional filters and pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Pen::query();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }
            if ($request->filled('abk')) {
                $query->where('abk', $request->abk);
            }

            $pens = $query->paginate($request->input('per_page', 15));

            // Hitung statistik
            $totalPens = Pen::count();
            $totalCapacity = Pen::sum('capacity');
            $totalOccupancy = Livestock::where('status', true)->count();

            // Hitung kandang tersedia (active dan masih ada kapasitas)
            $activePens = Pen::where('status', 'active')->get();
            $availablePensCount = 0;
            foreach ($activePens as $pen) {
                if ($pen->current_occupancy < $pen->capacity) {
                    $availablePensCount++;
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'pens'       => PenResource::collection($pens),
                    'stats'      => [
                        'total_pens'        => $totalPens,
                        'total_capacity'    => $totalCapacity,
                        'total_occupancy'   => $totalOccupancy,
                        'available_pens'    => $availablePensCount,
                    ],
                    'pagination' => [
                        'current_page' => $pens->currentPage(),
                        'per_page'     => $pens->perPage(),
                        'total'        => $pens->total(),
                        'last_page'    => $pens->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Pen index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created pen.
     *
     * @param  \App\Http\Requests\PenRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PenRequest $request): JsonResponse
    {
        try {
            $pen = Pen::create($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Kandang berhasil ditambahkan.',
                'data'    => new PenResource($pen),
            ], 201);
        } catch (\Exception $exception) {
            Log::error('Pen store error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan kandang: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified pen.
     *
     * @param  \App\Models\Pen  $pen
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Pen $pen): JsonResponse
    {
        try {
            $pen->load('livestocks');
            return response()->json([
                'success' => true,
                'data'    => new PenResource($pen),
            ]);
        } catch (\Exception $exception) {
            Log::error('Pen show error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail kandang.',
            ], 500);
        }
    }

    /**
     * Update the specified pen.
     *
     * @param  \App\Http\Requests\PenRequest  $request
     * @param  \App\Models\Pen                $pen
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PenRequest $request, Pen $pen): JsonResponse
    {
        try {
            $pen->update($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Kandang berhasil diperbarui.',
                'data'    => new PenResource($pen),
            ]);
        } catch (\Exception $exception) {
            Log::error('Pen update error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kandang.',
            ], 500);
        }
    }

    /**
     * Remove the specified pen (soft deactivate).
     *
     * @param  \App\Models\Pen  $pen
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Pen $pen): JsonResponse
    {
        try {
            $activeLivestockCount = $pen->livestocks()->where('status', true)->count();
            if ($activeLivestockCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kandang masih berisi ternak aktif, tidak dapat dinonaktifkan.',
                ], 422);
            }

            $pen->update(['status' => 'inactive']);
            return response()->json([
                'success' => true,
                'message' => 'Kandang berhasil dinonaktifkan.',
            ]);
        } catch (\Exception $exception) {
            Log::error('Pen destroy error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menonaktifkan kandang.',
            ], 500);
        }
    }

    /**
     * Import pens from an Excel file.
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

            if (!class_exists('Maatwebsite\Excel\Facades\Excel')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fitur import tidak tersedia. Silakan instal maatwebsite/excel.',
                ], 500);
            }

            if (!class_exists('App\Imports\PenImport')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas import PenImport tidak ditemukan. Silakan buat terlebih dahulu.',
                ], 500);
            }

            $import = new \App\Imports\PenImport();
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));
            return response()->json([
                'success' => true,
                'message' => 'Data kandang berhasil diimpor',
                'imported' => $import->getRowCount(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $exception) {
            Log::error('Pen import error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Get livestock by a specific pen or summary of all pens (jumlah ternak dan total bobot per kandang).
     * Endpoint: GET /api/pens/livestock?pen_id=optional
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLivestockByPen(Request $request): JsonResponse
    {
        try {
            $penId = $request->query('pen_id');

            // Jika ada pen_id, kirim detail ternak di kandang tersebut
            if ($penId) {
                $pen = Pen::with('livestocks')->find($penId);
                if (!$pen) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kandang tidak ditemukan.',
                    ], 404);
                }

                $livestocks = $pen->livestocks->map(function ($livestock) {
                    return [
                        'ear_tag'        => $livestock->ear_tag,
                        'gender'         => $livestock->gender === 'male' ? 'Jantan' : 'Betina',
                        'breed_type'     => $livestock->breed_type,
                        'current_weight' => $livestock->current_weight,
                        'condition'      => $livestock->condition,
                        'age_days'       => $livestock->age_days,
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data'    => [
                        'pen'        => [
                            'id'       => $pen->id,
                            'name'     => $pen->name,
                            'category' => $pen->category,
                            'abk'      => $pen->abk,
                            'capacity' => $pen->capacity,
                        ],
                        'livestocks' => $livestocks,
                    ],
                ]);
            }

            // Jika tidak ada pen_id, kembalikan ringkasan semua kandang
            $pens = Pen::with('livestocks')->get();
            $summary = [];

            foreach ($pens as $pen) {
                $livestockCount = $pen->livestocks->count();
                $totalWeight = $pen->livestocks->sum('current_weight');
                $occupancyPercentage = $pen->capacity > 0 ? round(($livestockCount / $pen->capacity) * 100, 2) : 0;

                $summary[] = [
                    'id'               => $pen->id,
                    'name'             => $pen->name,
                    'abk'              => $pen->abk ?? '-',
                    'category'         => $pen->category,
                    'livestock_count'  => $livestockCount,
                    'total_weight'     => round($totalWeight, 2),
                    'capacity'         => $pen->capacity,
                    'occupancy_percent'=> $occupancyPercentage,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('getLivestockByPen error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get analytics for a specific pen.
     *
     * @param  \App\Models\Pen  $pen
     * @return \Illuminate\Http\JsonResponse
     */
    public function analytics(Pen $pen): JsonResponse
    {
        try {
            $livestocks = $pen->livestocks()
                ->with('weightRecords')
                ->where('status', true)
                ->get();

            $totalWeight = 0;
            $maleCount = 0;
            $femaleCount = 0;
            $ageSum = 0;
            $count = $livestocks->count();

            foreach ($livestocks as $livestock) {
                $totalWeight += $livestock->current_weight;
                $ageSum += $livestock->age_days;
                if ($livestock->gender === 'male') {
                    $maleCount++;
                } else {
                    $femaleCount++;
                }
            }

            $averageWeight  = $count > 0 ? $totalWeight / $count : 0;
            $averageAge     = $count > 0 ? $ageSum / $count : 0;

            $feedRequirements = [
                'daily_kg'   => round($totalWeight * 0.03, 2),
                'weekly_kg'  => round($totalWeight * 0.03 * 7, 2),
                'monthly_kg' => round($totalWeight * 0.03 * 30, 2),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'pen'                => new PenResource($pen),
                    'livestock_stats'    => [
                        'total'            => $count,
                        'average_weight'   => round($averageWeight, 2),
                        'average_age_days' => round($averageAge, 0),
                        'total_weight'     => round($totalWeight, 2),
                        'by_gender'        => [
                            'male'   => $maleCount,
                            'female' => $femaleCount,
                        ],
                    ],
                    'feed_requirements' => $feedRequirements,
                    'performance'       => [
                        'occupancy_rate' => $pen->capacity > 0 ? round(($count / $pen->capacity) * 100, 2) : 0,
                    ],
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Pen analytics error: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat analitik kandang.',
            ], 500);
        }
    }
}
