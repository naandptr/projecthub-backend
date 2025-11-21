<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignItem;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DesignController extends Controller
{
    public function index()
    {
        $designs = Design::with(['order', 'assignedTo'])->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List of designs',
            'data' => $designs
        ]);
    }

    public function show($id)
    {
        $design = Design::with(['order', 'assignedTo', 'designItem'])
            ->find($id);

        if (!$design) {
            return response()->json([
                'status' => 'error',
                'message' => 'Design not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Design details',
            'data' => $design
        ]);
    }

    public function updateItemStatus(Request $request, $itemId)
    {
        $request->validate([
            'design_status' => 'required|in:approved,rejected'
        ]);

        $item = DesignItem::findOrFail($itemId);

        if ($request->design_status === 'approved') {
            $alreadyApproved = DesignItem::where('design_id', $item->design_id)
                ->where('design_status', 'approved')
                ->first();

            if ($alreadyApproved) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only one design item can be approved'
                ], 400);
            }
        }

        $item->update([
            'design_status' => $request->design_status
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Design item updated',
            'data' => $item
        ]);
    }

    public function confirmDesign($id)
    {
        $design = Design::with('order')->findOrFail($id);

        $approvedItem = DesignItem::where('design_id', $design->id)
            ->where('design_status', 'approved')
            ->first();

        if (!$approvedItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot confirm design - no approved design item found'
            ], 400);
        }

        DB::transaction(function () use ($design) {
            StatusHistory::where('order_id', $design->order_id)
                ->whereNull('end_time')
                ->update([
                    'end_time' => now()
                ]);

            StatusHistory::create([
                'order_id' => $design->order_id,
                'status_stage' => 'confirmed',
                'updated_by' => Auth::id(),
                'start_time' => now(),
                'end_time' => null
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Design confirmed successfully'
        ]);
    }
}
