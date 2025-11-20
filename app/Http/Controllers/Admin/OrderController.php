<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Design;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with([
            'design',
            'design.assignedTo',
            'statusHistory'
        ])->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List of orders',
            'data' => $orders
        ]);
    }

    public function show($id)
    {
        $order = Order::with([
            'design',
            'design.assignedTo',
            'statusHistory'
        ])->find($id);

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
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
        ]);

        return DB::transaction(function () use ($validated, $request) {

            if ($request->hasFile('order_file')) {
                $validated['order_file'] = $request->file('order_file')->store('orders', 'public');
            }

            $validated['order_number'] = Order::generateOrderNumber();
            $validated['created_by'] = auth()->id();

            $order = Order::create($validated);

            $assignedTo = User::whereHas('role', function ($q) {
                $q->where('role_name', 'designer_pic');
            })
            ->where('user_status', 'active')
            ->first();

            if (!$assignedTo) {
                throw new \Exception("No active designers!");
            }

            Design::create([
                'order_id' => $order->id,
                'assigned_to' => $assignedTo->id,
            ]);

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
                'data' => $order->load(['design', 'statusHistory']),
            ]);
        });
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

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
        ]);

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
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Order updated successfully',
            'data' => $order
        ]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);

        if ($order->order_file && Storage::disk('public')->exists($order->order_file)) {
            Storage::disk('public')->delete($order->order_file);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Order deleted successfully'
        ]);
    }
}
