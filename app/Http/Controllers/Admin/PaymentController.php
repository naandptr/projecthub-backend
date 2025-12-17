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
            'payment_amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,transfer,qris,card',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::with('payment')->findOrFail($orderId);

            $amount = $order->product_price * $order->product_quantity;

            $totalPaid = $order->payment->sum('payment_amount');

            $remaining = $amount - $totalPaid;

            if ($remaining <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already fully paid'
                ], 422);
            }

            if ($request->payment_amount > $remaining) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance'
                ], 422);
            }

            if ($request->payment_amount < $remaining) {
                $paymentType = 'down_payment';
                $paymentStatus = 'half_paid';
            } else {
                $paymentType = 'full_payment';
                $paymentStatus = 'paid';
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_type' => $paymentType,
                'payment_amount' => $request->payment_amount,
                'payment_date' => now(),
                'payment_method' => $request->payment_method,
                'payment_status' => $paymentStatus,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'data' => [
                    'payment' => $payment,
                    'total_paid' => $totalPaid + $request->payment_amount,
                    'remaining' => $remaining - $request->payment_amount,
                ]
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
            'payment_amount' => 'nullable|numeric|min:1',
            'payment_method' => 'nullable|in:cash,transfer,qris,card',
        ]);

        DB::beginTransaction();
        try {
            $payment = Payment::with('order.payment')->findOrFail($paymentId);

            $order = $payment->order;

            $amount = $order->product_price * $order->product_quantity;

            $totalPaidExceptThis = $order->payment
                ->where('id', '!=', $payment->id)
                ->sum('payment_amount');

            $newPaymentAmount = $request->payment_amount ?? $payment->payment_amount;

            $newTotalPaid = $totalPaidExceptThis + $newPaymentAmount;

            if ($newTotalPaid > $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Updated payment causes overpayment'
                ], 422);
            }

            if ($newTotalPaid < $amount) {
                $paymentType = 'down_payment';
                $paymentStatus = 'half_paid';
            } else {
                $paymentType = 'full_payment';
                $paymentStatus = 'paid';
            }

            $payment->update([
                'payment_amount' => $newPaymentAmount,
                'payment_method' => $request->payment_method ?? $payment->payment_method,
                'payment_type' => $paymentType,
                'payment_status' => $paymentStatus,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'data' => [
                    'payment' => $payment,
                    'total_paid' => $newTotalPaid,
                    'remaining' => $amount - $newTotalPaid,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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
