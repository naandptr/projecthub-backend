<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index()
    {
        $orders = Order::with(['payment', 'shipment'])
            ->orderBy('id', 'desc')
            ->get();

        $data = $orders->map(function($o) {
            return [
                'order_id' => $o->id,
                'order_number' => $o->order_number,
                'customer' => $o->cust_name,
                'amount' => $o->product_price * $o->product_quantity,
                'payments' => $o->payment,
                'shipment' => $o->shipment,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show($orderId)
    {
        $payment = Payment::with([
            'order',
        ])->find($orderId);

        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payment details',
            'data' => $payment
        ]);
    }

    public function store(Request $request, $orderId)
    {
        $request->validate([
            'payment_type' => 'required|in:down_payment,full_payment',
            'payment_amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,transfer,qris,card',
        ]);

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'order_id' => $orderId,
                'payment_type' => $request->payment_type,
                'payment_amount' => $request->payment_amount,
                'payment_date' => now(),
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_type == 'full_payment'
                    ? 'paid'
                    : 'half_paid',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $paymentId)
    {
        $request->validate([
            'payment_amount' => 'nullable|numeric',
            'payment_method' => 'nullable|in:cash,transfer,qris,card',
            'payment_status' => 'nullable|in:half_paid,paid',
        ]);

        $payment = Payment::findOrFail($paymentId);

        $payment->update($request->only(['payment_amount', 'payment_method', 'payment_status']));

        return response()->json([
            'success' => true,
            'message' => 'Payment updated successfully',
            'data' => $payment
        ]);
    }

    public function destroy($paymentId)
    {
        Payment::findOrFail($paymentId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }
}
