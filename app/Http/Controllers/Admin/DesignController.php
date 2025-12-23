<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $designs = Design::with(['order', 'assignedTo', 'order.statusHistory'])
            ->whereHas('order.statusHistory', function ($q) {
                $q->where('status_stage', 'designing');
            })
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $designs
        ]);
    }

    public function show($designId)
    {
        $design = Design::with(['order', 'assignedTo', 'designItems', 'order.statusHistory'])
            ->find($designId);

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
            'design_status' => 'required|in:approved,revision'
        ]);

        $item = DesignItem::findOrFail($itemId);

        if(!$item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Design item not found!'
            ], 400);
        }

        if ($request->design_status === 'approved') {
            $alreadyApproved = DesignItem::where('design_id', $item->design_id)
                ->where('design_status', 'approved')
                ->where('id', '!=', $item->id)
                ->exists();

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

    public function confirmDesign($designId)
    {
        $design = Design::with('order')->findOrFail($designId);

        $approvedItem = DesignItem::where('design_id', $design->id)
            ->where('design_status', 'approved')
            ->exists();

        if (!$approvedItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot confirm design - no approved design item found'
            ], 400);
        }

        $alreadyConfirmed = StatusHistory::where('order_id', $design->order_id)
            ->where('status_stage', 'confirmed')
            ->exists();

        if ($alreadyConfirmed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Design confirmation has been done!'
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
                'updated_by' => auth()->id(),
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
