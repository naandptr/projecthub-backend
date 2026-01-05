<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Order;

class TrackOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with([
            'payment',
            'latestPayment',
            'latestStatus'
        ])->get();

        $data = $orders->map(function($order) {
            return [
                'order_number' => $order->order_number,
                'customer' => $order->cust_name,
                'product_name' => $order->product_name,
                'product_quantity' =>$order->product_quantity,
                'order_notes' =>$order->order_notes,
                'order_status' =>$order->latestStatus->status_stage,
                'payment_status' => $order->latestPayment->payment_status ?? null,
                'shipment_date' => $order->shipment->shipment_date ?? null,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Order progress list',
            'data' => $data
        ]);
    }
}
