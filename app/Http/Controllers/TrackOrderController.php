<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Order;

class TrackOrderController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->has('order_number') || !$request->order_number) {
            return response()->json([
                'success' => false,
                'message' => 'Order number is required',
            ], 400);
        }

        $keyword = preg_replace('/[^0-9A-Za-z]/', '', $request->order_number);

        $order = Order::with([
            'payment',
            'latestPayment',
            'latestStatus',
            'shipment'
        ])
        ->whereRaw("
            REPLACE(order_number, '-', '') = ?
        ", [$keyword])
        ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order progress detail',
            'data' => [
                'order_number'      => $order->order_number,
                'cust_name'         => $order->cust_name,
                'product_name'      => $order->product_name,
                'product_quantity'  => $order->product_quantity,
                'amount'            => $order->product_price * $order->product_quantity,
                'order_notes'       => $order->order_notes,
                'invoice_url'       => $order->invoice_url,
                'status_stage'      => $order->latestStatus->status_stage ?? 'pending',
                'payment_status'    => optional($order->latestPayment)->payment_status,
                'shipment_date'     => optional($order->shipment)->shipment_date,
            ]
        ]);
    }
}
