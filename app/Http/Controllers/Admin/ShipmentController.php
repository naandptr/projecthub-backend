<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function store(Request $request, $orderId)
    {
        $request->validate([
            'service_type' => 'required|in:delivery,pickup',
            'courier_service' => 'nullable|string',
            'shipment_date' => 'nullable|date',
            'tracking_number' => 'nullable|string',
            'cost_type' => 'nullable|in:paid_by_customer,paid_by_company',
            'shipment_notes' => 'nullable|string',
        ]);

        $shipment = Shipment::create([
            'order_id' => $orderId,
            'service_type' => $request->service_type,
            'courier_service' => $request->courier_service,
            'shipment_date' => $request->shipment_date,
            'tracking_number' => $request->tracking_number,
            'cost_type' => $request->cost_type,
            'shipment_notes' => $request->shipment_notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => $shipment
        ]);
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
