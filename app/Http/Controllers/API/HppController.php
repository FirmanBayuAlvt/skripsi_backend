<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HppDetail;
use Illuminate\Http\JsonResponse;

class HppController extends Controller
{
    public function index(): JsonResponse
    {
        $hpps = HppDetail::with('livestock')->get();

        $totalHppJantan = $hpps->filter(fn($h) => $h->livestock && $h->livestock->gender == 'male')
            ->sum(fn($h) => $h->purchase_cost + $h->feed_cost + $h->operational_cost);
        $totalHppBetina = $hpps->filter(fn($h) => $h->livestock && $h->livestock->gender == 'female')
            ->sum(fn($h) => $h->purchase_cost + $h->feed_cost + $h->operational_cost);

        // Hitung detail sesuai permintaan (contoh)
        $qtyJantanBreeding = $hpps->filter(fn($h) => $h->livestock && $h->livestock->gender == 'male' && $h->livestock->pen?->category == 'Breeding')->count();
        $totalHppJantanBreeding = $hpps->filter(fn($h) => $h->livestock && $h->livestock->gender == 'male' && $h->livestock->pen?->category == 'Breeding')
            ->sum(fn($h) => $h->purchase_cost + $h->feed_cost + $h->operational_cost);
        $qtyJantanFattening = $hpps->filter(fn($h) => $h->livestock && $h->livestock->gender == 'male' && $h->livestock->pen?->category == 'Fattening')->count();
        $totalHppJantanFattening = $hpps->filter(fn($h) => $h->livestock && $h->livestock->gender == 'male' && $h->livestock->pen?->category == 'Fattening')
            ->sum(fn($h) => $h->purchase_cost + $h->feed_cost + $h->operational_cost);
        $qtyBetina = $hpps->filter(fn($h) => $h->livestock && $h->livestock->gender == 'female')->count();
        $totalHppBetinaDetail = $totalHppBetina;

        $detail = $hpps->map(function ($h) {
            return [
                'tagging' => $h->livestock?->ear_tag,
                'hpp_pembelian' => $h->purchase_cost,
                'pakan' => $h->feed_cost,
                'operasional' => $h->operational_cost,
                'total' => $h->purchase_cost + $h->feed_cost + $h->operational_cost,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total_hpp_jantan' => $totalHppJantan,
                'total_hpp_betina' => $totalHppBetina,
                'qty_jantan_breeding' => $qtyJantanBreeding,
                'total_hpp_jantan_breeding' => $totalHppJantanBreeding,
                'qty_jantan_fattening' => $qtyJantanFattening,
                'total_hpp_jantan_fattening' => $totalHppJantanFattening,
                'qty_betina' => $qtyBetina,
                'total_hpp_betina_detail' => $totalHppBetinaDetail,
                'detail' => $detail,
            ]
        ]);
    }
}
