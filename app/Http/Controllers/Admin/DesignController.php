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
    /* GET ALL DESIGNS */
    public function index()
    {
        $limit = min(request('limit', 10), 30);

        $designs = Design::with([
            'order',
            'assignedTo',
            'designItems',
            'order.statusHistory'
        ])
        ->whereHas('order.statusHistory', function ($q) {
            $q->where('status_stage', 'designing');
        })
        ->orderBy('created_at', 'desc')
        ->paginate($limit);

        $designs->getCollection()->transform(function ($design) {
            $approvalStatus = true; // Default approval status
            $imageCover = $design->order->order_file; // Default cover image

            // Process design items if any exist
            if ($design->designItems->isNotEmpty()) {
                $approvedItem = $design->designItems
                    ->where('design_status', 'approved')
                    ->sortByDesc('created_at')
                    ->first();

                if ($approvedItem) {
                    $imageCover = $approvedItem->design_file; // Use approved design as cover image
                    $approvalStatus = true;
                } else {
                    // Get the latest design item (any status)
                    $latestItem = $design->designItems
                        ->sortByDesc('created_at')
                        ->first();

                    $imageCover = $latestItem?->design_file;
                    
                    // Check if any design item is still in progress
                    if ($design->designItems->contains('design_status', 'in_progress')) {
                        $approvalStatus = false;
                    } else {
                        $approvalStatus = true;
                    }                    
                }
            }

            return [
                'id' => $design->id,
                'order_id' => $design->order->id,
                'assigned_to' => $design->assigned_to,
                'approval_status' => $approvalStatus,
                'image_cover' => $imageCover,
                'order' => $design->order,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'List of designs',
            'data' => $designs->items(), 
            'meta' => [
                'current_page' => $designs->currentPage(),
                'last_page'    => $designs->lastPage(),
                'total'        => $designs->total(),
                'per_page'     => $designs->perPage(),
            ]
        ]);
    }

    /* GET DESIGN BY ID */
    public function show($designId)
    {
        $design = Design::with(['order', 'assignedTo', 'designItems', 'order.statusHistory'])
            ->find($designId);

        if (!$design) {
            return response()->json([
                'success' => false,
                'message' => 'Design not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Design details',
            'data' => $design
        ]);
    }

    /* UPDATE ITEM STATUS (APPROVED/REVISION) */
    public function updateItemStatus(Request $request, $itemId)
    {
        $request->validate([
            'design_status' => 'required|in:approved,revision'
        ]);

        $item = DesignItem::findOrFail($itemId);

        if(!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Design item not found!'
            ], 400);
        }

        // Prevent multiple approved items for the same design
        if ($request->design_status === 'approved') {
            $alreadyApproved = DesignItem::where('design_id', $item->design_id)
                ->where('design_status', 'approved')
                ->where('id', '!=', $item->id)
                ->exists();

            if ($alreadyApproved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only one design item can be approved'
                ], 400);
            }
        }

        $item->update([
            'design_status' => $request->design_status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Design item updated',
            'data' => $item
        ]);
    }

    /* CONFIRM DESIGN */
    public function confirmDesign($designId)
    {
        $design = Design::with('order')->findOrFail($designId);

        $approvedItem = DesignItem::where('design_id', $design->id)
            ->where('design_status', 'approved')
            ->exists();

        // Prevent confirmation if no approved design exists
        if (!$approvedItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot confirm design - no approved design item found'
            ], 400);
        }

        $alreadyConfirmed = StatusHistory::where('order_id', $design->order_id)
            ->where('status_stage', 'confirmed')
            ->exists();

        // Prevent duplicate confirmation
        if ($alreadyConfirmed) {
            return response()->json([
                'success' => false,
                'message' => 'Design confirmation has been done!'
            ], 400);
        }

        DB::transaction(function () use ($design) {
            // Close the current status stage by setting end_time
            StatusHistory::where('order_id', $design->order_id)
                ->whereNull('end_time')
                ->update([
                    'end_time' => now()
                ]);

            // Create new 'confirmed' status history entry
            StatusHistory::create([
                'order_id' => $design->order_id,
                'status_stage' => 'confirmed',
                'updated_by' => auth()->id(),
                'start_time' => now(),
                'end_time' => null
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Design confirmed successfully'
        ]);
    }
}
