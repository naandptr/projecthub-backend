<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\StatusHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShipmentController extends Controller
{
    public function show($orderId)
    {
        $shipment = Shipment::with([
            'order',
        ])->find($orderId);

        if (!$shipment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Shipment not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Shipment details',
            'data' => $shipment
        ]);
    }

    public function store(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        $request->validate([
            'service_type' => 'required|in:delivery,pickup',
            'courier_service' => 'nullable|string',
            'shipment_date' => 'nullable|date',
            'tracking_number' => 'nullable|string',
            'cost_type' => 'nullable|in:paid_by_customer,paid_by_company',
            'shipment_notes' => 'nullable|string',
        ]);

        $latestStatus = $order->statusHistory()
            ->latest('created_at')
            ->first();

        if ($latestStatus && $latestStatus->status_stage === 'ready') {
            $shipment = Shipment::create([
                'order_id' => $orderId,
                'service_type' => $request->service_type,
                'courier_service' => $request->courier_service,
                'shipment_date' => $request->shipment_date,
                'tracking_number' => $request->tracking_number,
                'cost_type' => $request->cost_type,
                'shipment_notes' => $request->shipment_notes,
            ]);

            StatusHistory::where('order_id', $order->id)
                ->whereNull('end_time')
                ->update([
                    'end_time' => now()
                ]);

            StatusHistory::create([
                'order_id' => $order->id,
                'status_stage' => 'completed',
                'updated_by' => Auth::id(),
                'start_time' => now(),
                'end_time' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shipment created successfully',
                'data' => $shipment
            ]);
        }

        return response()->json([
            'success' => false, 
            'message' => 'Order is not yet in ready stage!'], 400);
    }

    public function update(Request $request, $shipmentId)
    {
        $shipment = Shipment::findOrFail($shipmentId);

        $shipment->update($request->only([
            'service_type',
            'courier_service',
            'shipment_date',
            'tracking_number',
            'cost_type',
            'shipment_notes',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Shipment updated succesfully',
            'data' => $shipment
        ]);
    }

    public function destroy($shipmentId)
    {
        Shipment::findOrFail($shipmentId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shipment deleted successfully',
        ]);
    }
}
