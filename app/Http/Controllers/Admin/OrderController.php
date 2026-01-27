<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Design;
use App\Models\Production;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    /* GET ALL ORDERS */
    public function index()
    {        
        $orders = Order::with([            
            'statusHistory'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'List of orders',
            'data' => $orders, 
        ]);
    }

    /* GET ORDER BY ID */
    public function show($orderId)
    {
        $order = Order::with([            
            'design.assignedTo',
            'production.assignedTo',
            'statusHistory'
        ])->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order details',
            'data' => $order
        ]);
    }

    /* CREATE ORDER */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cust_name' => 'required|string',
            'cust_phone' => 'required|string',
            'cust_address' => 'required|string',
            'order_date' => 'required|date',
            'order_deadline' => 'required|date',
            'product_name' => 'required|string',
            'product_quantity' => 'required|integer',
            'product_price' => 'required|integer',
            'order_notes' => 'nullable|string',
            'order_file' => 'nullable|file|max:1000',
            'invoice_url' => 'nullable|string',

            'assigned_to_design' => 'nullable|exists:users,id',
            'assigned_to_production' => 'nullable|exists:users,id',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Handle file upload if present
            if ($request->hasFile('order_file')) {
                $file = $request->file('order_file');
                // Compress image files
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $validated['order_file'] = Order::compressAndStoreImage($file);
                } else {
                    $validated['order_file'] = $file->store('orders', 'public');
                }
            }

            // Generate unique order number and set creator
            $validated['order_number'] = Order::generateOrderNumber();
            $validated['created_by'] = auth()->id();

            $order = Order::create($validated);

            // Create design assignment (with or without assigned user)
            if ($request->assigned_to_design) {
                Design::create([
                    'order_id' => $order->id,
                    'assigned_to' => $request->assigned_to_design,
                ]);
            } else {
                // Create unassigned design record
                Design::create([
                    'order_id' => $order->id,
                    'assigned_to' => null,
                ]);
            }

            // Create production assignment (with or without assigned user)
            if ($request->assigned_to_production) {
                Production::create([
                    'order_id' => $order->id,
                    'assigned_to' => $request->assigned_to_production,
                ]);
            } else {
                // Create unassigned production record
                Production::create([
                    'order_id' => $order->id,
                    'assigned_to' => null,
                ]);
            }

            // Initialize order status history as 'pending'
            StatusHistory::create([
                'order_id' => $order->id,
                'status_stage' => 'pending',
                'updated_by' => auth()->id(),
                'start_time' => now(),
                'end_time' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['design', 'production', 'statusHistory']),
            ]);
        });
    }

    /* UPDATE ORDER */
    public function update(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        $request->validate([
            'cust_name' => 'string',
            'cust_phone' => 'string',
            'cust_address' => 'string',
            'order_date' => 'date',
            'order_deadline' => 'date',
            'product_name' => 'string',
            'product_quantity' => 'integer',
            'product_price' => 'integer',
            'order_notes' => 'string|nullable',
            'order_file' => 'nullable|file|max:1000',
            'invoice_url' => 'nullable|string',

            'assigned_to_design' => 'nullable|exists:users,id',
            'assigned_to_production' => 'nullable|exists:users,id',
        ]);

        // Handle file replacement if new file is uploaded
        if ($request->hasFile('order_file')) {
            // Delete old file if it exists
            if ($order->order_file && Storage::disk('public')->exists($order->order_file)) {
                Storage::disk('public')->delete($order->order_file);
            }

            $file = $request->file('order_file');

            // Compress and store image files
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $order->order_file = Order::compressAndStoreImage($file);
            } else {
                $order->order_file = $file->store('orders', 'public');
            }
        }

        $order->update($request->only([
            'cust_name',
            'cust_phone',
            'cust_address',
            'order_date',
            'order_deadline',
            'product_name',
            'product_quantity',
            'product_price',
            'order_notes',
            'invoice_url',
        ]));

        // Update or create design assignment if provided
        if ($request->filled('assigned_to_design')) {
            $order->design()->updateOrCreate(
                ['order_id' => $order->id],
                ['assigned_to' => $request->assigned_to_design]
            );
        }

        // Update or create production assignment if provided
        if ($request->filled('assigned_to_production')) {
            $order->production()->updateOrCreate(
                ['order_id' => $order->id],
                ['assigned_to' => $request->assigned_to_production]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->load(['design.assignedTo', 'production.assignedTo']),
        ]);
    }

    /* DELETE ORDER */
    public function destroy($orderId)
    {
        $order = Order::findOrFail($orderId);

        $orderDelete = StatusHistory::where('order_id', $order->id)
            ->where('status_stage', 'confirmed')
            ->first();

        // Only allow deletion if order hasn't been confirmed
        if (!$orderDelete) {
            if ($order->order_file && Storage::disk('public')->exists($order->order_file)) {
            Storage::disk('public')->delete($order->order_file);
            }

            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);
        }

        return response()->json([
                'success' => false,
                'message' => 'Cannot delete confirmed design!'
        ], 400);
    }

    /* GET ALL COMPLETED ORDER */
    public function completed()
    {        
        $orders = Order::with([
            'shipment',
            'latestStatus'
        ])
            ->whereHas('latestStatus', function ($q) {
                $q->where('status_stage', 'completed');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No orders completed yet!'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'List of completed orders',
            'data' => $orders
        ]);
    }
}
