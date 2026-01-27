<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /* GET ALL ORDER */
    public function index()
    {        
        $orders = Order::with(['payment', 'shipment'])
            ->latest()
            ->get();

        $orders = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer' => $order->cust_name,
                'product_name' => $order->product_name,
                'amount' => $order->product_price * $order->product_quantity,
                'payments' => $order->payment,
                'shipment' => $order->shipment,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /* GET PAYMENT BY ID */
    public function show($orderId)
    {
        $payment = Payment::with([
            'order',
        ])->find($orderId);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment details',
            'data' => $payment
        ]);
    }

    /* CREATE PAYMENT */
    public function store(Request $request, $orderId)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,transfer,qris,card',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::with('payment')->findOrFail($orderId);

            $amount = $order->product_price * $order->product_quantity; // Calculate total order amount

            $totalPaid = $order->payment->sum('payment_amount'); // Calculate total amount already paid

            $remaining = $amount - $totalPaid; // Calculate remaining balance

            // Prevent payment if order is already fully paid
            if ($remaining <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already fully paid'
                ], 422);
            }

            // Prevent overpayment
            if ($request->payment_amount > $remaining) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds remaining balance'
                ], 422);
            }

            // Determine payment type and status based on amount
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

    /* UPDATE PAYMENT */
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

            $amount = $order->product_price * $order->product_quantity; // Calculate total order amount

            // Calculate total paid excluding the current payment being updated
            $totalPaidExceptThis = $order->payment 
                ->where('id', '!=', $payment->id)
                ->sum('payment_amount');

            // Use new amount if provided, otherwise keep existing amount
            $newPaymentAmount = $request->payment_amount ?? $payment->payment_amount; 

            // Calculate new total paid with updated payment amount
            $newTotalPaid = $totalPaidExceptThis + $newPaymentAmount;

            // Prevent update if it would cause overpayment
            if ($newTotalPaid > $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Updated payment causes overpayment'
                ], 422);
            }

            // Determine payment type and status based on new total
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

    /* DELETE PAYMENT */
    public function destroy($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        // Prevent deletion if order has shipment record (order is completed/shipped)
        if (
            Shipment::where('order_id', $payment->order_id)->exists() 
        ) {
            return response()->json([
                'success' => false,
                'message' => 'The order is at the completed stage!'
            ], 400);
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully'
        ]);
    }
}
