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
    public function index()
    {
        $limit = min(request('limit', 10), 30);

        $orders = Order::with([
            'design',
            'design.assignedTo',
            'statusHistory'
        ])
        ->orderBy('created_at', 'desc')
        ->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'List of orders',
            'data' => $orders->items(), 
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
            ]
        ]);
    }

    public function show($orderId)
    {
        $order = Order::with([
            'design',
            'production',
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

            if ($request->hasFile('order_file')) {
                $file = $request->file('order_file');

                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $validated['order_file'] = Order::compressAndStoreImage($file);
                } else {
                    $validated['order_file'] = $file->store('orders', 'public');
                }
            }

            $validated['order_number'] = Order::generateOrderNumber();
            $validated['created_by'] = auth()->id();

            $order = Order::create($validated);

            if ($request->assigned_to_design) {
                Design::create([
                    'order_id' => $order->id,
                    'assigned_to' => $request->assigned_to_design,
                ]);
            }

            if ($request->assigned_to_production) {
                Production::create([
                    'order_id' => $order->id,
                    'assigned_to' => $request->assigned_to_production,
                ]);
            } else {
                Production::create([
                    'order_id' => $order->id,
                    'assigned_to' => null,
                ]);
            }

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

        if ($request->hasFile('order_file')) {

            if ($order->order_file && Storage::disk('public')->exists($order->order_file)) {
                Storage::disk('public')->delete($order->order_file);
            }

            $file = $request->file('order_file');

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

        if ($request->filled('assigned_to_design')) {
            $order->design()->updateOrCreate(
                ['order_id' => $order->id],
                ['assigned_to' => $request->assigned_to_design]
            );
        }

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

    public function destroy($orderId)
    {
        $order = Order::findOrFail($orderId);

        $orderDelete = StatusHistory::where('order_id', $order->id)
            ->where('status_stage', 'confirmed')
            ->first();

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

    public function completed()
    {
        $orders = Order::with([
            'shipment',
            'latestStatus'
        ])
        ->whereHas('latestStatus', function ($q) {
            $q->where('status_stage', 'completed');
        })
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
