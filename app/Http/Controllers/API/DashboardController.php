<?php

namespace App\Http\Controllers\API;

use App\Models\Livestock;
use App\Models\Pen;
use App\Models\Feed;
use App\Models\Prediction;
use App\Models\WeightRecord;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get overview data for dashboard.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function overview(): JsonResponse
    {
        $totalLivestock = Livestock::where('status', true)->count();
        $totalPens = Pen::where('status', 'active')->count();
        $totalCapacity = Pen::sum('capacity');
        $totalOccupancy = Livestock::where('status', true)->count();
        $occupancyRate = $totalCapacity > 0 ? round(($totalOccupancy / $totalCapacity) * 100, 2) : 0;
        $totalFeedTypes = Feed::where('is_active', true)->count();
        $totalFeedStock = Feed::sum('current_stock');

        $allFeeds = Feed::all();
        $lowStockFeeds = 0;
        foreach ($allFeeds as $feed) {
            if ($feed->is_stock_low) {
                $lowStockFeeds++;
            }
        }

        $totalFeedValue = Feed::select(DB::raw('SUM(current_stock * price_per_kg) as total'))->value('total') ?? 0;

        $livestocksWithWeights = Livestock::where('status', true)->with('weightRecords')->get();
        $totalGain = 0;
        $countGain = 0;
        foreach ($livestocksWithWeights as $livestock) {
            $adg = $livestock->average_daily_gain;
            if ($adg !== null && $adg !== 0) {
                $totalGain += $adg;
                $countGain++;
            }
        }
        $avgDailyGain = $countGain > 0 ? round($totalGain / $countGain, 3) : 0;

        $recentPredictions = Prediction::with('livestock')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($prediction) {
                $livestock = $prediction->livestock;
                return [
                    'id' => $prediction->id,
                    'livestock_ear_tag' => $livestock ? $livestock->ear_tag : null,
                    'predicted_gain' => $prediction->predicted_gain,
                    'confidence' => $prediction->confidence,
                    'created_at' => $prediction->created_at->diffForHumans(),
                ];
            });

        $breedBreakdown = Livestock::select('breed_type', DB::raw('count(*) as total'))
            ->groupBy('breed_type')
            ->pluck('total', 'breed_type')
            ->toArray();

        $genderBreakdown = Livestock::select('gender', DB::raw('count(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();

        $programBreakdown = Livestock::join('pens', 'livestocks.pen_id', '=', 'pens.id')
            ->select('pens.category', DB::raw('count(livestocks.id) as total'))
            ->groupBy('pens.category')
            ->pluck('total', 'pens.category')
            ->toArray();

        $topLivestocks = Livestock::where('status', true)
            ->with('pen')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($livestock) {
                return [
                    'id' => $livestock->id,
                    'ear_tag' => $livestock->ear_tag,
                    'breed_type' => $livestock->breed_type,
                    'gender' => $livestock->gender,
                    'pen' => $livestock->pen ? $livestock->pen->name : null,
                    'current_weight' => $livestock->current_weight,
                    'age_days' => $livestock->age_days,
                ];
            })
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_livestock' => $totalLivestock,
                    'total_pens' => $totalPens,
                    'total_feed_types' => $totalFeedTypes,
                    'total_feed_stock_kg' => round($totalFeedStock, 2),
                    'total_feed_value' => round($totalFeedValue, 2),
                    'low_stock_feeds' => $lowStockFeeds,
                    'average_daily_gain' => $avgDailyGain,
                    'occupancy_rate' => $occupancyRate,
                    'breed_breakdown' => $breedBreakdown,
                    'gender_breakdown' => $genderBreakdown,
                    'program_breakdown' => $programBreakdown,
                ],
                'alerts' => $this->getAlerts(),
                'recent_predictions' => $recentPredictions,
                'recent_activity' => $this->getRecentActivity(),
                'top_livestocks' => $topLivestocks,
            ]
        ]);
    }

    /**
     * Get pen analytics data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function penAnalytics(): JsonResponse
    {
        $pens = Pen::withCount(['livestocks as occupancy' => function ($query) {
            $query->where('status', true);
        }])->get();

        $chartData = [
            'labels' => $pens->pluck('name')->toArray(),
            'data' => $pens->pluck('occupancy')->toArray(),
            'capacity' => $pens->pluck('capacity')->toArray(),
        ];

        return response()->json([
            'success' => true,
            'data' => $chartData
        ]);
    }

    /**
     * Get complete statistics for the dashboard.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $totalLivestock = Livestock::count();
        $livestockInPen = Livestock::where('status', true)->count();
        $livestockSold = Livestock::where('status', false)->whereNotNull('date_of_death_or_sold')->count();
        $livestockSlaughtered = Livestock::where('condition', 'disembelih')->count();
        $livestockDead = Livestock::where('condition', 'mati')->count();
        $livestockPartner = 0;

        // Hitung total berat menggunakan collection (karena current_weight adalah accessor)
        $allLivestocks = Livestock::all();
        $totalWeight = 0;
        foreach ($allLivestocks as $livestock) {
            $totalWeight += $livestock->current_weight;
        }

        $mortalityPercentage = $totalLivestock > 0 ? round(($livestockDead / $totalLivestock) * 100, 2) : 0;

        $qtyFattening = Livestock::whereHas('pen', function ($query) {
            $query->where('category', 'Fattening');
        })->count();

        $qtyBreeding = Livestock::whereHas('pen', function ($query) {
            $query->where('category', 'Breeding');
        })->count();

        // Subkategori berdasarkan umur menggunakan DATEDIFF dari birth_date
        $subcategoryStats = Livestock::selectRaw('
            pens.category as kategori_kandang,
            gender,
            CASE
                WHEN DATEDIFF(CURDATE(), birth_date) <= 30 THEN "Cempe (0-1 Bulan)"
                WHEN DATEDIFF(CURDATE(), birth_date) <= 90 THEN "Cempe Prasapih (1-3 Bulan)"
                WHEN DATEDIFF(CURDATE(), birth_date) <= 180 THEN "Lepas Sapih (3-6 Bulan)"
                WHEN DATEDIFF(CURDATE(), birth_date) <= 365 THEN "Dara (6-12 Bulan)"
                ELSE "Dewasa (>12 Bulan)"
            END as sub_kategori,
            COUNT(*) as qty
        ')
        ->join('pens', 'livestocks.pen_id', '=', 'pens.id')
        ->groupBy('pens.category', 'gender', 'sub_kategori')
        ->get();

        $breedStats = Livestock::selectRaw('breed_type, COUNT(*) as qty')
            ->groupBy('breed_type')
            ->pluck('qty', 'breed_type');

        $genderStats = [
            'male' => Livestock::where('gender', 'male')->count(),
            'female' => Livestock::where('gender', 'female')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total_livestock' => $totalLivestock,
                'livestock_in_pen' => $livestockInPen,
                'livestock_sold' => $livestockSold,
                'livestock_slaughtered' => $livestockSlaughtered,
                'livestock_dead' => $livestockDead,
                'livestock_partner' => $livestockPartner,
                'total_weight' => round($totalWeight, 2),
                'mortality_percentage' => $mortalityPercentage,
                'qty_fattening' => $qtyFattening,
                'qty_breeding' => $qtyBreeding,
                'subcategory_stats' => $subcategoryStats,
                'breed_stats' => $breedStats,
                'gender_stats' => $genderStats,
            ]
        ]);
    }

    /**
     * Get alerts for low stock and high occupancy.
     *
     * @return array
     */
    protected function getAlerts(): array
    {
        $alerts = [];
        $feeds = Feed::all();
        foreach ($feeds as $feed) {
            if ($feed->is_stock_low) {
                $alerts[] = [
                    'severity' => 'warning',
                    'message' => "Stok {$feed->name} tersisa {$feed->current_stock} kg",
                    'suggestion' => 'Segera lakukan restok.',
                ];
            }
        }

        $pens = Pen::withCount(['livestocks as occupancy' => function ($query) {
            $query->where('status', true);
        }])->get();

        foreach ($pens as $pen) {
            $occupancy = $pen->occupancy;
            $capacity = $pen->capacity;
            if ($capacity > 0 && ($occupancy / $capacity) >= 0.9) {
                $alerts[] = [
                    'severity' => 'info',
                    'message' => "Kandang {$pen->name} hampir penuh ({$occupancy}/{$capacity})",
                    'suggestion' => 'Pertimbangkan untuk menambah kandang baru atau memindahkan ternak.',
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get recent activities (livestock added, weight recorded).
     *
     * @return array
     */
    protected function getRecentActivity(): array
    {
        $recentLivestocks = Livestock::latest()->limit(5)->get()->map(function ($livestock) {
            return [
                'type' => 'livestock_added',
                'description' => "Ternak {$livestock->ear_tag} ditambahkan",
                'time' => $livestock->created_at->diffForHumans(),
                'icon' => 'plus',
                'color' => 'green',
            ];
        });

        $recentWeights = WeightRecord::with('livestock')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($weightRecord) {
                $livestock = $weightRecord->livestock;
                return [
                    'type' => 'weight_recorded',
                    'description' => "Berat " . ($livestock ? $livestock->ear_tag : 'Unknown') . ": {$weightRecord->weight_kg} kg",
                    'time' => $weightRecord->created_at->diffForHumans(),
                    'icon' => 'weight',
                    'color' => 'blue',
                ];
            });

        $activities = $recentLivestocks->concat($recentWeights)
            ->sortByDesc('time')
            ->values()
            ->take(10)
            ->toArray();

        return $activities;
    }
}
