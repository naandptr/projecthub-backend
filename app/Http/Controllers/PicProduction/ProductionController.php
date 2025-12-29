<?php

namespace App\Http\Controllers\PicProduction;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\ProductionResult;
use App\Models\StatusHistory;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductionController extends Controller
{
    /**
     * REVISI #1: Get all production tasks dengan status history
     * 
     * Response format sesuai prototype Hayaa Advertising:
     * - Task Information (customer, phone, date, product, quantity, total, deadline)
     * - Order File (design reference)
     * - Order Notes (special instructions)
     * - Status (current status dengan timeline)
     * - Production Details (in_house / vendor)
     */
    public function index()
    {
        try {
            $userId = auth()->id();

            $productions = Production::where('assigned_to', $userId)
                ->with([
                    'order' => function ($q) {
                        $q->select('id', 'order_number', 'cust_name', 'cust_phone', 'cust_address', 'order_date', 'order_deadline', 'product_name', 'product_quantity', 'product_price', 'order_file', 'order_notes');
                    },
                    'order.statusHistory' => function ($query) {
                        $query->with('updatedBy:id,username')
                            ->orderBy('created_at', 'desc');
                    },
                    'productionDetails' => function ($q) {
                        $q->select('id', 'production_id', 'production_type')
                            ->with('productionResults');
                    }
                ])
                ->select('id', 'order_id', 'assigned_to', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc')
                ->get();

            // Format data sesuai prototype
            $formattedProductions = $productions->map(function ($production) {
                $latestStatus = $production->order->statusHistory->first();
                $totalPrice = $production->order->product_quantity * $production->order->product_price;

                return [
                    'id' => $production->id,
                    'order_id' => $production->order_id,
                    'order_number' => $production->order->order_number,
                    'task_information' => [
                        'customer_name' => $production->order->cust_name,
                        'phone_number' => $production->order->cust_phone,
                        'order_date' => $production->order->order_date,
                        'product_name' => $production->order->product_name,
                        'order_quantity' => $production->order->product_quantity . ' pcs',
                        'order_total' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                        'deadline' => $production->order->order_deadline,
                    ],
                    'order_file' => $production->order->order_file,
                    'order_notes' => $production->order->order_notes,
                    'status' => [
                        'stage' => $latestStatus?->status_stage ?? 'pending',
                        'started_at' => $latestStatus?->start_time,
                        'updated_by' => $latestStatus?->updatedBy?->username ?? '-',
                    ],
                    'details_count' => $production->productionDetails->count(),
                    'details' => $production->productionDetails->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'production_type' => $detail->production_type,
                            'results' => $detail->productionResults->map(function ($result) {
                                return [
                                    'id' => $result->id,
                                    'production_file' => $result->production_file,
                                    'created_at' => $result->created_at,
                                ];
                            }),
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Production tasks retrieved successfully',
                'data' => $formattedProductions,
                'total' => count($formattedProductions),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production tasks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * REVISI #2: Get production tasks dengan filter status confirmed
     * 
     * Filter hanya orders dengan status latest = 'confirmed'
     */
    public function indexWithFilter(Request $request)
    {
        try {
            $userId = auth()->id();
            $status = $request->query('status', 'confirmed');

            // REVISI #2: Filter by latest status ONLY
            $productions = Production::where('assigned_to', $userId)
                ->whereHas('order.statusHistory', function ($q) use ($status) {
                    $q->where('status_stage', $status)
                        ->whereRaw('id = (SELECT MAX(id) FROM status_history WHERE order_id = orders.id)');
                }, '=', 1)
                ->with([
                    'order' => function ($q) {
                        $q->select('id', 'order_number', 'cust_name', 'cust_phone', 'cust_address', 'order_date', 'order_deadline', 'product_name', 'product_quantity', 'product_price', 'order_file', 'order_notes');
                    },
                    'order.statusHistory' => function ($query) {
                        $query->with('updatedBy:id,username')
                            ->orderBy('created_at', 'desc');
                    },
                    'productionDetails' => function ($q) {
                        $q->select('id', 'production_id', 'production_type')
                            ->with('productionResults');
                    }
                ])
                ->select('id', 'order_id', 'assigned_to', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc')
                ->get();

            // Format data sesuai prototype
            $formattedProductions = $productions->map(function ($production) {
                $latestStatus = $production->order->statusHistory->first();
                $totalPrice = $production->order->product_quantity * $production->order->product_price;

                return [
                    'id' => $production->id,
                    'order_id' => $production->order_id,
                    'order_number' => $production->order->order_number,
                    'task_information' => [
                        'customer_name' => $production->order->cust_name,
                        'phone_number' => $production->order->cust_phone,
                        'order_date' => $production->order->order_date,
                        'product_name' => $production->order->product_name,
                        'order_quantity' => $production->order->product_quantity . ' pcs',
                        'order_total' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                        'deadline' => $production->order->order_deadline,
                    ],
                    'order_file' => $production->order->order_file,
                    'order_notes' => $production->order->order_notes,
                    'status' => [
                        'stage' => $latestStatus?->status_stage ?? 'pending',
                        'started_at' => $latestStatus?->start_time,
                        'updated_by' => $latestStatus?->updatedBy?->username ?? '-',
                    ],
                    'details_count' => $production->productionDetails->count(),
                    'details' => $production->productionDetails->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'production_type' => $detail->production_type,
                            'results' => $detail->productionResults->map(function ($result) {
                                return [
                                    'id' => $result->id,
                                    'production_file' => $result->production_file,
                                    'created_at' => $result->created_at,
                                ];
                            }),
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => "Production tasks with status '{$status}' retrieved successfully",
                'data' => $formattedProductions,
                'total' => count($formattedProductions),
                'filter' => $status,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving filtered production tasks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single production task by ID
     * Response include complete status timeline
     */
    public function show($id)
    {
        try {
            $production = Production::with([
                'order' => function ($q) {
                    $q->select('id', 'order_number', 'cust_name', 'cust_phone', 'cust_address', 'order_date', 'order_deadline', 'product_name', 'product_quantity', 'product_price', 'order_file', 'order_notes');
                },
                'order.statusHistory' => function ($query) {
                    $query->with('updatedBy:id,username')
                        ->orderBy('created_at', 'desc');
                },
                'productionDetails' => function ($q) {
                    $q->select('id', 'production_id', 'production_type')
                        ->with('productionResults');
                }
            ])
            ->select('id', 'order_id', 'assigned_to', 'created_at', 'updated_at')
            ->findOrFail($id);

            $latestStatus = $production->order->statusHistory->first();
            $totalPrice = $production->order->product_quantity * $production->order->product_price;

            $formattedProduction = [
                'id' => $production->id,
                'order_id' => $production->order_id,
                'order_number' => $production->order->order_number,
                'task_information' => [
                    'customer_name' => $production->order->cust_name,
                    'phone_number' => $production->order->cust_phone,
                    'address' => $production->order->cust_address,
                    'order_date' => $production->order->order_date,
                    'product_name' => $production->order->product_name,
                    'order_quantity' => $production->order->product_quantity . ' pcs',
                    'order_total' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    'deadline' => $production->order->order_deadline,
                ],
                'order_file' => $production->order->order_file,
                'order_notes' => $production->order->order_notes,
                'status' => [
                    'stage' => $latestStatus?->status_stage ?? 'pending',
                    'started_at' => $latestStatus?->start_time,
                    'updated_by' => $latestStatus?->updatedBy?->username ?? '-',
                ],
                'status_timeline' => $production->order->statusHistory->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'stage' => $status->status_stage,
                        'start_time' => $status->start_time,
                        'end_time' => $status->end_time,
                        'duration_minutes' => $status->end_time && $status->start_time 
                            ? $status->end_time->diffInMinutes($status->start_time)
                            : null,
                        'updated_by' => $status->updatedBy?->username,
                        'created_at' => $status->created_at,
                    ];
                }),
                'details_count' => $production->productionDetails->count(),
                'details' => $production->productionDetails->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'production_type' => $detail->production_type,
                        'results' => $detail->productionResults->map(function ($result) {
                            return [
                                'id' => $result->id,
                                'production_file' => $result->production_file,
                                'created_at' => $result->created_at,
                            ];
                        }),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Production task retrieved successfully',
                'data' => $formattedProduction,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Production task not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production task',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * REVISI #3, #4, #5: Start production
     * 
     * - REVISI #3: Validate order status = 'confirmed' only
     * - REVISI #4: Update previous status end_time
     * - REVISI #5: Prevent multiple 'in_production' status
     */
    public function startProduction(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:productions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Production not found',
                'errors' => $validator->errors(),
            ], 404);
        }

        try {
            DB::beginTransaction();

            $production = Production::with('order.statusHistory')->findOrFail($id);
            $order = $production->order;

            // REVISI #3: Check latest status is 'confirmed'
            $latestStatus = $order->statusHistory()
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestStatus || $latestStatus->status_stage !== 'confirmed') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Production can only be started from confirmed status',
                    'current_status' => $latestStatus?->status_stage ?? 'no_status',
                ], 422);
            }

            // REVISI #5: Check no existing 'in_production' status
            $existingInProduction = $order->statusHistory()
                ->where('status_stage', 'in_production')
                ->exists();

            if ($existingInProduction) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Production is already in progress. Cannot start again.',
                ], 422);
            }

            // REVISI #4: Update previous status end_time
            $latestStatus->update([
                'end_time' => now(),
            ]);

            // Create new 'in_production' status
            $newStatus = StatusHistory::create([
                'order_id' => $order->id,
                'status_stage' => 'in_production',
                'start_time' => now(),
                'end_time' => null,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production started successfully',
                'data' => [
                    'production_id' => $production->id,
                    'status' => $newStatus->status_stage,
                    'started_at' => $newStatus->start_time,
                    'previous_status' => [
                        'stage' => $latestStatus->status_stage,
                        'ended_at' => $latestStatus->end_time,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error starting production',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store production detail
     */
    public function storeDetail(Request $request, $id)
    {
        $validator = Validator::make(array_merge(['id' => $id], $request->all()), [
            'id' => 'required|exists:productions,id',
            'production_type' => 'required|in:in_house,vendor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $detail = ProductionDetail::create([
                'production_id' => $id,
                'production_type' => $request->production_type,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Production detail created successfully',
                'data' => $detail,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating production detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update production detail
     */
    public function updateDetail(Request $request, $detailId)
    {
        $validator = Validator::make(array_merge(['id' => $detailId], $request->all()), [
            'id' => 'required|exists:production_details,id',
            'production_type' => 'sometimes|in:in_house,vendor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $detail = ProductionDetail::findOrFail($detailId);
            $detail->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Production detail updated successfully',
                'data' => $detail,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating production detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete production
     */
    public function completeProduction(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $production = Production::with('order.statusHistory')->findOrFail($id);
            $order = $production->order;

            // Get latest status and update end_time
            $latestStatus = $order->statusHistory()
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestStatus) {
                $latestStatus->update([
                    'end_time' => now(),
                ]);
            }

            // Create 'completed' status
            $completedStatus = StatusHistory::create([
                'order_id' => $order->id,
                'status_stage' => 'completed',
                'start_time' => now(),
                'end_time' => null,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production completed successfully',
                'data' => [
                    'production_id' => $production->id,
                    'status' => $completedStatus->status_stage,
                    'completed_at' => $completedStatus->start_time,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error completing production',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm ready production
     */
    public function confirmReady(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $production = Production::with('order.statusHistory')->findOrFail($id);
            $order = $production->order;

            // Get latest status and update end_time
            $latestStatus = $order->statusHistory()
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestStatus) {
                $latestStatus->update([
                    'end_time' => now(),
                ]);
            }

            // Create 'ready' status
            $readyStatus = StatusHistory::create([
                'order_id' => $order->id,
                'status_stage' => 'ready',
                'start_time' => now(),
                'end_time' => null,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production marked as ready',
                'data' => [
                    'production_id' => $production->id,
                    'status' => $readyStatus->status_stage,
                    'ready_at' => $readyStatus->start_time,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error confirming ready status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
