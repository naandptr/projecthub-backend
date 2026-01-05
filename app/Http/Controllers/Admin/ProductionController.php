<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Production;
use App\Models\ProductionResult;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function index()
    {
        $productions = Production::with(['order', 'assignedTo', 'order.statusHistory'])
            ->whereHas('order.statusHistory', function ($q) {
                $q->where('status_stage', 'confirmed');
            })
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'List of productions',
            'data' => $productions
        ]);
    }

    public function show($productionId)
    {
        $production = Production::with(['order', 'assignedTo', 'productionDetails', 'productionDetails.inHouseDetail', 'productionDetails.vendorDetail', 'productionResult', 'order.statusHistory'])
            ->find($productionId);

        if (!$production) {
            return response()->json([
                'success' => false,
                'message' => 'Production not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Production details',
            'data' => $production
        ]);
    }

    public function confirmProduction($productionId)
    {
        $production = Production::with('order')->findOrFail($productionId);
        
        $existedResult = $production->productionResult()->exists();

        if (!$existedResult) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot confirm production - no production result found'
            ], 400);
        }

        $alreadyConfirmed = StatusHistory::where('order_id', $production->order_id)
            ->where('status_stage', 'ready')
            ->exists();

        if ($alreadyConfirmed) {
            return response()->json([
                'success' => false,
                'message' => 'Production confirmation has been done!'
            ], 400);
        }

        DB::transaction(function () use ($production) {
            StatusHistory::where('order_id', $production->order_id)
                ->whereNull('end_time')
                ->update([
                    'end_time' => now()
                ]);

            StatusHistory::create([
                'order_id' => $production->order_id,
                'status_stage' => 'ready',
                'updated_by' => Auth::id(),
                'start_time' => now(),
                'end_time' => null
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Production confirmed successfully'
        ]);
    }
}
