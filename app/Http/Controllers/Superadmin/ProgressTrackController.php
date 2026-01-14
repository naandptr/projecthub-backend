<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Order;

class ProgressTrackController extends Controller
{
    // public function index()
    // {
    //     $orders = Order::with([
    //         'design.assignedTo',
    //         'production.assignedTo',
    //         'statusHistory'
    //     ])->get();

    //     $data = $orders->map(function ($order) {

    //         $estimatedDuration = Carbon::parse($order->order_date)
    //             ->diffInDays(Carbon::parse($order->order_deadline));

    //         $designStatus = $order->statusHistory
    //             ->where('status_stage', 'designing')
    //             ->sortByDesc('start_time')
    //             ->first();

    //         $designDuration = $designStatus && $designStatus->end_time
    //             ? Carbon::parse($designStatus->start_time)
    //                 ->diffInHours(Carbon::parse($designStatus->end_time))
    //             : null;

    //         $productionStatus = $order->statusHistory
    //             ->where('status_stage', 'in_production')
    //             ->sortByDesc('start_time')
    //             ->first();

    //         $productionDuration = $productionStatus && $productionStatus->end_time
    //             ? Carbon::parse($productionStatus->start_time)
    //                 ->diffInHours(Carbon::parse($productionStatus->end_time))
    //             : null;

    //         return [
    //             'order_id' => $order->id,

    //             'order' => [
    //                 'product_name' => $order->product_name,
    //                 'order_number' => $order->order_number,
    //                 'order_date'   => $order->order_date,
    //                 'deadline'     => $order->order_deadline,
    //                 'estimated_time_days' => $estimatedDuration,
    //             ],

    //             'design' => [
    //                 'pic' => $order->design?->assignedTo?->full_name,
    //                 'start_time' => $designStatus?->start_time,
    //                 'end_time'   => $designStatus?->end_time,
    //                 'duration_hours' => $designDuration,
    //             ],

    //             'production' => [
    //                 'pic' => $order->production?->assignedTo?->full_name,
    //                 'start_time' => $productionStatus?->start_time,
    //                 'end_time'   => $productionStatus?->end_time,
    //                 'duration_hours' => $productionDuration,
    //             ],
    //         ];
    //     });

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Order progress list',
    //         'data' => $data
    //     ]);
    // }

    public function index()
    {
        $limit = min(request('limit', 10), 30);

        $orders = Order::with([
            'design.assignedTo',
            'production.assignedTo',
            'statusHistory'
        ])->orderBy('created_at', 'desc')
        ->paginate($limit);

        $orders->getCollection()->transform(function ($order) {
            $estimatedDuration = Carbon::parse($order->order_date)
                ->diffInDays(Carbon::parse($order->order_deadline));

            $designStatus = $order->statusHistory
                ->where('status_stage', 'designing')
                ->sortByDesc('start_time')
                ->first();

            $designDuration = $designStatus && $designStatus->end_time
                ? Carbon::parse($designStatus->start_time)
                    ->diffInHours(Carbon::parse($designStatus->end_time))
                : null;

            $productionStatus = $order->statusHistory
                ->where('status_stage', 'in_production')
                ->sortByDesc('start_time')
                ->first();

            $productionDuration = $productionStatus && $productionStatus->end_time
                ? Carbon::parse($productionStatus->start_time)
                    ->diffInHours(Carbon::parse($productionStatus->end_time))
                : null;

            return [
                'order_id' => $order->id,

                'order' => [
                    'product_name' => $order->product_name,
                    'order_number' => $order->order_number,
                    'order_date'   => $order->order_date,
                    'deadline'     => $order->order_deadline,
                    'estimated_time_days' => $estimatedDuration,
                ],

                'design' => [
                    'pic' => $order->design?->assignedTo?->full_name,
                    'start_time' => $designStatus?->start_time,
                    'end_time'   => $designStatus?->end_time,
                    'duration_hours' => $designDuration,
                ],

                'production' => [
                    'pic' => $order->production?->assignedTo?->full_name,
                    'start_time' => $productionStatus?->start_time,
                    'end_time'   => $productionStatus?->end_time,
                    'duration_hours' => $productionDuration,
                ],
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Order progress list',
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
            ]
        ]);
    }
}