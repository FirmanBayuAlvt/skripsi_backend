<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Livestock;
use App\Models\Pen;
use App\Models\Feed;
use App\Models\WeightRecord;
use App\Models\FeedingRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(): JsonResponse
    {
        try {
            $totalLivestock = Livestock::count();
            $totalActive = Livestock::where('status', true)->count();
            $totalPens = Pen::count();
            $totalFeedTypes = Feed::count();
            $totalFeedStock = Feed::sum('current_stock');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_livestocks' => $totalLivestock,
                    'total_active_livestocks' => $totalActive,
                    'total_pens' => $totalPens,
                    'total_feed_types' => $totalFeedTypes,
                    'total_feed_stock_kg' => round($totalFeedStock, 2),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function performance(): JsonResponse
    {
        try {
            // Rata-rata ADG per bulan dari weight records
            $monthlyAdg = WeightRecord::select(
                    DB::raw('DATE_FORMAT(record_date, "%Y-%m") as month'),
                    DB::raw('AVG(weight_kg - LAG(weight_kg) OVER (PARTITION BY livestock_id ORDER BY record_date)) as avg_adg')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $chartLabels = $monthlyAdg->pluck('month')->toArray();
            $chartValues = $monthlyAdg->map(fn($item) => round($item->avg_adg, 3))->toArray();

            // Rata-rata ADG keseluruhan
            $livestocks = Livestock::all();
            $totalAdg = $livestocks->sum(fn($l) => $l->average_daily_gain);
            $count = $livestocks->count();
            $avgAdg = $count > 0 ? round($totalAdg / $count, 3) : 0;

            // FCR
            $totalFeed = FeedingRecord::sum('quantity_kg');
            $totalWeightGain = $livestocks->sum(fn($l) => max(0, $l->current_weight - $l->initial_weight));
            $fcr = $totalWeightGain > 0 ? round($totalFeed / $totalWeightGain, 2) : 0;

            // Mortalitas
            $dead = Livestock::where('status', false)->count();
            $total = Livestock::count();
            $mortalityRate = $total > 0 ? round(($dead / $total) * 100, 2) : 0;

            // Okupansi
            $totalCapacity = Pen::sum('capacity');
            $totalOccupancy = Livestock::where('status', true)->count();
            $occupancyRate = $totalCapacity > 0 ? round(($totalOccupancy / $totalCapacity) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'average_daily_gain' => $avgAdg,
                    'feed_conversion_ratio' => $fcr,
                    'mortality_rate' => $mortalityRate,
                    'occupancy_rate' => $occupancyRate,
                    'chart_labels' => $chartLabels,
                    'chart_adg_values' => $chartValues,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function growth(): JsonResponse
    {
        try {
            // 4 minggu terakhir rata-rata berat
            $weeklyGrowth = WeightRecord::select(
                    DB::raw('WEEK(record_date) as week'),
                    DB::raw('AVG(weight_kg) as avg_weight')
                )
                ->groupBy('week')
                ->orderBy('week', 'desc')
                ->limit(4)
                ->get()
                ->reverse()
                ->values();

            $labels = $weeklyGrowth->map(fn($item) => "Minggu {$item->week}")->toArray();
            $data = $weeklyGrowth->pluck('avg_weight')->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'data' => $data,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
