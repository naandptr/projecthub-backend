<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use App\Models\Spk;

class SpkController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
        ]);

        $pic = User::whereHas('role', function ($q) {
                $q->where('role_name', 'production_pic');
            })
            ->where('user_status', 'Active')
            ->first();

        if (!$pic) {
            return response()->json([
                'message' => "No active production PIC!"
            ], 400);
        }

        $validatedOrders = [];

        foreach ($request->order_ids as $orderId) {

            $order = Order::with(['design.designItem', 'statusHistory'])->find($orderId);

            if (!$order) continue;

            $approvedItem = $order->design?->designItem?->firstWhere('design_status', 'approved');

            if (!$approvedItem) {
                return response()->json([
                    'message' => "Order ID $orderId does not have an approved item design yet."
                ], 400);
            }

            $confirmedStatus = $order->statusHistory
                ->where('status_stage', 'confirmed')
                ->sortByDesc('created_at')
                ->first();

            if (!$confirmedStatus) {
                return response()->json([
                    'message' => "Order ID $orderId does not have confirmed status yet."
                ], 400);
            }

            $validatedOrders[] = $orderId;
        }

        $spk = Spk::create([
            'spk_number' => Spk::generateSpkNumber(),
            'spk_date'   => now(),
            'assigned_to'=> $pic->id
        ]);

        $spk->orders()->attach($validatedOrders);

        return response()->json([
            'message' => 'SPK created successfully',
            'data' => $spk->load('orders')
        ]);
    }
}
