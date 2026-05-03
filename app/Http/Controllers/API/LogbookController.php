<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogbookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Logbook::with('livestock.pen');

        if ($request->tagging) {
            $query->whereHas('livestock', fn($q) => $q->where('ear_tag', 'like', "%{$request->tagging}%"));
        }
        if ($request->start_date) {
            $query->where('event_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('event_date', '<=', $request->end_date);
        }
        if ($request->kejadian) {
            $query->where('event_type', $request->kejadian);
        }

        $logs = $query->orderBy('event_date', 'desc')->paginate(20);

        // Transform data jika diperlukan
        $data = $logs->map(function ($log) {
            return [
                'tanggal_kejadian' => $log->event_date,
                'tagging' => $log->livestock?->ear_tag,
                'jenis_ternak' => $log->livestock?->breed_type,
                'kandang' => $log->livestock?->pen?->name,
                'kelamin' => $log->livestock?->gender,
                'kejadian' => $log->event_type,
                'keterangan' => $log->description,
                'penanganan' => $log->handling,
                'tag_baru' => $log->new_tag,
                'kandang_baru' => $log->new_pen?->name,
                'kategori_kandang_baru' => $log->new_pen_category,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ]
        ]);
    }
}
