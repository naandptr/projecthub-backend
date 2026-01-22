<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    /* GET ALL PRODUCTIONS */
    public function index()
    {
        $limit = min(request('limit', 10), 30);

        $productions = Production::with(['order', 'order.statusHistory'])
            ->whereHas('order.statusHistory', function ($q) {
                $q->where('status_stage', 'in_production');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        $productions->getCollection()->transform(function ($production) {
            $approvedDesign = $production->order->design->designItems->firstWhere('design_status', 'approved');
            $existedResult = $production->productionResult;
            
            return [
                'id' => $production->id,
                'order_id' => $production->order->id,
                'result_status' => $existedResult ? true : false,
                'image_cover' => $existedResult?->production_file ?? $approvedDesign?->design_file ?? $production->order->order_file,
                'order' => [
                    'id' => $production->order->id,
                    'order_number' => $production->order->order_number,
                    'cust_name' => $production->order->cust_name,
                    'order_date' => $production->order->order_date,
                    'order_deadline' => $production->order->order_deadline,
                    'product_name' => $production->order->product_name,
                    'product_quantity' => $production->order->product_quantity,
                    'product_price' => $production->order->product_price,
                    'status_history' => $production->order->statusHistory,
                ]
            ];
        });
        
        return response()->json([
            'success' => true,
            'message' => 'List of productions',
            'data' => $productions->items(),
            'meta' => [
                'current_page' => $productions->currentPage(),
                'last_page'    => $productions->lastPage(),
                'total'        => $productions->total(),
                'per_page'     => $productions->perPage(),
            ]
        ]);
    }

    /* GET PRODUCTION BY ID */
    public function show($productionId)
    {
        $production = Production::with(['order', 
            'assignedTo', 
            'productionDetails', 
            'productionDetails.inHouseDetail', 
            'productionDetails.vendorDetail', 
            'productionResult', 
            'order.statusHistory'])
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

    /* CONFIRMED PRODUCTION */
    public function confirmProduction($productionId)
    {
        $production = Production::with('order')->findOrFail($productionId);
        
        $existedResult = $production->productionResult()->exists();

        // Prevent confirmation if no production result exists
        if (!$existedResult) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot confirm production - no production result found'
            ], 400);
        }

        $alreadyConfirmed = StatusHistory::where('order_id', $production->order_id)
            ->where('status_stage', 'ready')
            ->exists();

        // Prevent duplicate confirmation
        if ($alreadyConfirmed) {
            return response()->json([
                'success' => false,
                'message' => 'Production confirmation has been done!'
            ], 400);
        }

        DB::transaction(function () use ($production) {
            // Close current status stage by setting end_time
            StatusHistory::where('order_id', $production->order_id)
                ->where('status_stage', 'in_production')  // Constraint
                ->whereNull('end_time')
                ->update([
                    'end_time' => now()
                ]);

            // Create new 'ready' status (production complete, awaiting shipment)
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
