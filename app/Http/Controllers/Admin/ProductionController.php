<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Production;
use App\Models\ProductionResult;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function index()
    {
        $productions = Production::with(['order', 'assignedTo', 'order.statusHistory'])->get();

        return response()->json([
            'status' => 'success',
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
                'status' => 'error',
                'message' => 'Production not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
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
                'status' => 'error',
                'message' => 'Cannot confirm production - no production result found'
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
            'status' => 'success',
            'message' => 'Production confirmed successfully'
        ]);
    }
}
