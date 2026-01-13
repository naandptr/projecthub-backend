<?php

namespace App\Http\Controllers\PicProduction;

use App\Models\Order;
use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\ProductionResult;
use App\Models\StatusHistory;
use App\Models\VendorDetail;
use App\Models\InhouseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class ProductionController extends Controller
{
    /**
     * Get all production tasks assigned to current user
     */
     public function index()
    {
        try {
            $userId = auth()->id();

            // Get productions dengan status confirmed SAJA
            $productions = Production::whereHas('order.statusHistory', function ($query) {
                    $query->where('status_stage', 'confirmed')
                        ->whereRaw('id = (SELECT MAX(id) FROM status_history WHERE order_id = orders.id)');
                })
                ->with([
                    'order' => function ($q) {
                        $q->select('id', 'order_number', 'cust_name', 'cust_phone', 'cust_address', 'order_date', 'order_deadline', 'product_name', 'product_quantity', 'product_price', 'order_file', 'order_notes');
                    },
                    'order.statusHistory' => function ($query) {
                        $query->with('updatedBy:id,username')
                            ->orderBy('created_at', 'desc');
                    },
                    'productionDetails' => function ($q) {
                        $q->with(['vendorDetail', 'inhouseDetail']);
                    },
                    'productionResults'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

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
                    'assigned_to' => $production->assigned_to,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Production tasks with status confirmed retrieved successfully',
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
 * GET PRODUCTION DETAIL
 * GET: {{base_url}}/production/{id}/details
 */
public function getDetail($id)
{
    try {
        $detail = ProductionDetail::where('production_id', $id)
            ->with(['vendorDetail', 'inhouseDetail'])
            ->firstOrFail();

        $responseData = [
            'id' => $detail->id,
            'production_id' => $detail->production_id,
            'production_type' => $detail->production_type,
        ];

        if ($detail->production_type === 'vendor' && $detail->vendorDetail) {
            $responseData['vendor_detail'] = [
                'id' => $detail->vendorDetail->id,
                'vendor_name' => $detail->vendorDetail->vendor_name,
                'start_date' => $detail->vendorDetail->start_date,
                'deadline' => $detail->vendorDetail->deadline,
            ];
        } elseif ($detail->production_type === 'in_house' && $detail->inhouseDetail) {
            $responseData['inhouse_detail'] = [
                'id' => $detail->inhouseDetail->id,
                'start_date' => $detail->inhouseDetail->start_date,
                'end_date' => $detail->inhouseDetail->end_date,
                'production_budget' => $detail->inhouseDetail->production_budget,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Production detail retrieved successfully',
            'data' => $responseData,
        ], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Production detail not found',
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving production detail',
            'error' => $e->getMessage(),
        ], 500);
    }
}



    /**
     * Get production tasks with filter status confirmed
     */
    public function indexWithFilter(Request $request)
    {
        try {
            $userId = auth()->id();
            $status = $request->query('status', 'confirmed');

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
                                    'production_id' => $result->production_id,
                                    'production_file' => $result->production_file,
                                    'created_at' => $result->created_at,
                                ];
                            }),
                        ];
                    })->values(),
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
                    $q->with(['vendorDetail', 'inhouseDetail']);
                },
                'productionResults'
            ])
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
                'details' => $production->productionDetails->map(function ($detail) use ($production) {
                    $detailData = [
                        'id' => $detail->id,
                        'production_type' => $detail->production_type,
                    ];

                    if ($detail->production_type === 'vendor' && $detail->vendorDetail) {
                        $detailData['vendor_detail'] = [
                            'id' => $detail->vendorDetail->id,
                            'vendor_name' => $detail->vendorDetail->vendor_name,
                            'start_date' => $detail->vendorDetail->start_date,
                            'deadline' => $detail->vendorDetail->deadline,
                        ];
                    } elseif ($detail->production_type === 'in_house' && $detail->inhouseDetail) {
                        $detailData['inhouse_detail'] = [
                            'id' => $detail->inhouseDetail->id,
                            'start_date' => $detail->inhouseDetail->start_date,
                            'end_date' => $detail->inhouseDetail->end_date,
                            'production_budget' => $detail->inhouseDetail->production_budget,
                        ];
                    }

                    $detailData['results'] = $production->productionResults->map(function ($result) {
                        return [
                            'id' => $result->id,
                            'production_file' => $result->production_file,
                            'file_url' => Storage::url($result->production_file),
                            'created_at' => $result->created_at,
                        ];
                    });

                    return $detailData;
                }),
                'assigned_to' => $production->assigned_to,
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

   public function startProduction(Request $request, $id)
{
    DB::beginTransaction();
    try {
        $userId = auth()->user()->id;

        $production = Production::findOrFail($id);
        $order      = Order::findOrFail($production->order_id);

        // ✅ CEK STATUS TERAKHIR HARUS CONFIRMED
        $latestStatusHistory = $order->statusHistory()
            ->latest('created_at')
            ->first();

        if (!$latestStatusHistory || $latestStatusHistory->status_stage !== 'confirmed') {
            throw new \Exception('Production can only be started if order status is CONFIRMED');
        }

        // ✅ CEK SUDAH DI-ASSIGN USER LAIN ATAU BELUM
        if ($production->assigned_to !== null && $production->assigned_to !== $userId) {
            throw new \Exception('This production task is already assigned to another user');
        }

        // ✅ JIKA BELUM PERNAH DI-ASSIGN KE USER INI → ASSIGN & UPDATE STATUS
        if ($production->assigned_to !== $userId) {
            $production->update(['assigned_to' => $userId]);

            // Tutup status CONFIRMED (end_time)
            if ($latestStatusHistory) {
                $latestStatusHistory->update(['end_time' => now()]);
            }

            // Buat status baru: IN_PRODUCTION
            StatusHistory::create([
                'order_id'     => $order->id,
                'status_stage' => 'in_production',
                'updated_by'   => $userId,
                'start_time'   => now(),
            ]);
        }

        // Refresh data production + relasi
        $production = $production->fresh([
            'order',
            'productionDetails.vendorDetail',
            'productionDetails.inhouseDetail',
            'productionResults',
            'order.statusHistory.updatedBy',
        ]);

        $latestStatus = $production->order->statusHistory->first();
        $totalPrice   = $production->order->product_quantity * $production->order->product_price;

        $formattedProduction = [
            'id'         => $production->id,
            'order_id'   => $production->order_id,
            'order_number' => $production->order->order_number,
            'task_information' => [
                'customer_name'  => $production->order->cust_name,
                'phone_number'   => $production->order->cust_phone,
                'address'        => $production->order->cust_address,
                'order_date'     => $production->order->order_date,
                'product_name'   => $production->order->product_name,
                'order_quantity' => $production->order->product_quantity.' pcs',
                'product_price'  => 'Rp '.number_format($production->order->product_price, 0, ',', '.'),
                'order_total'    => 'Rp '.number_format($totalPrice, 0, ',', '.'),
                'deadline'       => $production->order->order_deadline,
            ],
            'order_file'  => $production->order->order_file,
            'order_notes' => $production->order->order_notes,
            'status' => [
                'stage'      => $latestStatus?->status_stage ?? 'pending',
                'started_at' => $latestStatus?->start_time,
                'updated_by' => $latestStatus?->updatedBy?->username ?? '-',
            ],
            'status_timeline' => $production->order->statusHistory->map(function ($status) {
                return [
                    'id'               => $status->id,
                    'stage'            => $status->status_stage,
                    'start_time'       => $status->start_time,
                    'end_time'         => $status->end_time,
                    'duration_minutes' => $status->end_time && $status->start_time
                        ? $status->end_time->diffInMinutes($status->start_time)
                        : null,
                    'updated_by'       => $status->updatedBy?->username,
                    'created_at'       => $status->created_at,
                ];
            }),
            'details_count' => $production->productionDetails->count(),
            'details' => $production->productionDetails->map(function ($detail) use ($production) {
                $detailData = [
                    'id'             => $detail->id,
                    'production_type'=> $detail->production_type,
                    'results_count'  => $production->productionResults->count(),
                ];

                if ($detail->production_type === 'vendor' && $detail->vendorDetail) {
                    $detailData['vendor_detail'] = [
                        'id'          => $detail->vendorDetail->id,
                        'vendor_name' => $detail->vendorDetail->vendor_name,
                        'start_date'  => $detail->vendorDetail->start_date,
                        'deadline'    => $detail->vendorDetail->deadline,
                    ];
                } elseif ($detail->production_type === 'in_house' && $detail->inhouseDetail) {
                    $detailData['inhouse_detail'] = [
                        'id'               => $detail->inhouseDetail->id,
                        'start_date'       => $detail->inhouseDetail->start_date,
                        'end_date'         => $detail->inhouseDetail->end_date,
                        'production_budget'=> $detail->inhouseDetail->production_budget,
                    ];
                }

                $detailData['results'] = $production->productionResults->map(function ($result) {
                    return [
                        'id'              => $result->id,
                        'production_file' => $result->production_file,
                        'file_url'        => Storage::url($result->production_file),
                        'created_at'      => $result->created_at,
                    ];
                });

                return $detailData;
            }),
            'assigned_to' => $production->assigned_to,
        ];

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'You are now working on this production task',
            'data'    => $formattedProduction,
        ], 200);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Production or Order not found',
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * REVISI #3: Store production detail - UNIFIED API (1x REQUEST)
     * Input semua data sekaligus: production_type, vendor_name, start_date, deadline, budget
     */
    public function storeDetail(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Validasi dasar
            $baseValidator = Validator::make(array_merge(['id' => $id], $request->all()), [
                'id' => 'required|exists:productions,id',
                'production_type' => 'required|in:in_house,vendor',
            ]);

            if ($baseValidator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $baseValidator->errors(),
                ], 422);
            }

            $productionType = $request->production_type;

            // Validasi spesifik berdasarkan type
            if ($productionType === 'vendor') {
                $detailValidator = Validator::make($request->all(), [
                    'vendor_name' => 'required|string|max:255',
                    'start_date' => 'required|date|date_format:Y-m-d',
                    'deadline' => 'required|date|date_format:Y-m-d|after:start_date',
                ]);

                if ($detailValidator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed for vendor detail',
                        'errors' => $detailValidator->errors(),
                    ], 422);
                }
            } elseif ($productionType === 'in_house') {
                $detailValidator = Validator::make($request->all(), [
                    'start_date' => 'required|date|date_format:Y-m-d',
                    'end_date' => 'required|date|date_format:Y-m-d|after:start_date',
                    'production_budget' => 'required|numeric|min:0',
                ]);

                if ($detailValidator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed for inhouse detail',
                        'errors' => $detailValidator->errors(),
                    ], 422);
                }
            }

            // Step 1: Create production detail
            $detail = ProductionDetail::create([
                'production_id' => $id,
                'production_type' => $productionType,
            ]);

            // Step 2: Create vendor or inhouse detail
            if ($productionType === 'vendor') {
                $vendorDetail = VendorDetail::create([
                    'production_detail_id' => $detail->id,
                    'vendor_name' => $request->vendor_name,
                    'start_date' => $request->start_date,
                    'deadline' => $request->deadline,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Production detail with vendor information created successfully',
                    'data' => [
                        'id' => $detail->id,
                        'production_id' => $detail->production_id,
                        'production_type' => $detail->production_type,
                        'vendor_detail' => [
                            'id' => $vendorDetail->id,
                            'vendor_name' => $vendorDetail->vendor_name,
                            'start_date' => $vendorDetail->start_date,
                            'deadline' => $vendorDetail->deadline,
                        ],
                        'created_at' => $detail->created_at,
                    ],
                ], 201);

            } else { // in_house
                $inhouseDetail = InhouseDetail::create([
                    'production_detail_id' => $detail->id,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'production_budget' => $request->production_budget,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Production detail with inhouse information created successfully',
                    'data' => [
                        'id' => $detail->id,
                        'production_id' => $detail->production_id,
                        'production_type' => $detail->production_type,
                        'inhouse_detail' => [
                            'id' => $inhouseDetail->id,
                            'start_date' => $inhouseDetail->start_date,
                            'end_date' => $inhouseDetail->end_date,
                            'production_budget' => $inhouseDetail->production_budget,
                        ],
                        'created_at' => $detail->created_at,
                    ],
                ], 201);
            }

        } catch (\Exception $e) {
            DB::rollBack();
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
        DB::beginTransaction();
        try {
            $detail = ProductionDetail::with(['vendorDetail', 'inhouseDetail'])->findOrFail($detailId);

            // Validasi spesifik berdasarkan type
            if ($detail->production_type === 'vendor') {
                $validator = Validator::make($request->all(), [
                    'vendor_name' => 'required|string|max:255',
                    'start_date' => 'required|date|date_format:Y-m-d',
                    'deadline' => 'required|date|date_format:Y-m-d|after:start_date',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                if ($detail->vendorDetail) {
                    $detail->vendorDetail->update([
                        'vendor_name' => $request->vendor_name,
                        'start_date' => $request->start_date,
                        'deadline' => $request->deadline,
                    ]);
                }

            } elseif ($detail->production_type === 'in_house') {
                $validator = Validator::make($request->all(), [
                    'start_date' => 'required|date|date_format:Y-m-d',
                    'end_date' => 'required|date|date_format:Y-m-d|after:start_date',
                    'production_budget' => 'required|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                if ($detail->inhouseDetail) {
                    $detail->inhouseDetail->update([
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'production_budget' => $request->production_budget,
                    ]);
                }
            }

            DB::commit();

            $detail = $detail->fresh(['vendorDetail', 'inhouseDetail']);

            $responseData = [
                'id' => $detail->id,
                'production_id' => $detail->production_id,
                'production_type' => $detail->production_type,
            ];

            if ($detail->production_type === 'vendor' && $detail->vendorDetail) {
                $responseData['vendor_detail'] = [
                    'id' => $detail->vendorDetail->id,
                    'vendor_name' => $detail->vendorDetail->vendor_name,
                    'start_date' => $detail->vendorDetail->start_date,
                    'deadline' => $detail->vendorDetail->deadline,
                ];
            } elseif ($detail->production_type === 'in_house' && $detail->inhouseDetail) {
                $responseData['inhouse_detail'] = [
                    'id' => $detail->inhouseDetail->id,
                    'start_date' => $detail->inhouseDetail->start_date,
                    'end_date' => $detail->inhouseDetail->end_date,
                    'production_budget' => $detail->inhouseDetail->production_budget,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Production detail updated successfully',
                'data' => $responseData,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Production detail not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating production detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete production detail
     */
    public function deleteDetail($detailId)
    {
        DB::beginTransaction();
        try {
            $detail = ProductionDetail::with(['vendorDetail', 'inhouseDetail'])->findOrFail($detailId);

            if ($detail->vendorDetail) {
                $detail->vendorDetail->delete();
            }

            if ($detail->inhouseDetail) {
                $detail->inhouseDetail->delete();
            }

            $detail->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production detail deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Production detail not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting production detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Store production result (upload file)
     */
    public function storeResult(Request $request, $detailId)
    {
        $validator = Validator::make($request->all(), [
            'production_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Step 1: Get production detail to extract production_id
            $detail = ProductionDetail::findOrFail($detailId);
            
            // Step 2: Extract production_id dari detail
            $productionId = $detail->production_id;
            
            // Step 3: Upload file
            $file = $request->file('production_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('production_results', $filename, 'public');

            // Step 4: Create result dengan production_id
            $result = ProductionResult::create([
                'production_id' => $productionId,  // ✅ GUNAKAN production_id
                'production_file' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Production result uploaded successfully',
                'data' => [
                    'id' => $result->id,
                    'production_id' => $result->production_id,  // ✅ RESPONSE production_id
                    'production_file' => $result->production_file,
                    'file_url' => Storage::url($result->production_file),
                    'created_at' => $result->created_at,
                ],
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Production detail not found',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading production result',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update production result (replace file)
     * ✅ SESUAI DENGAN CODE KAMU + DEBUG LOGGING
     */
    public function updateResult(Request $request, $resultId)
    {
        // ✅ DEBUG: Log request info
        \Log::info('updateResult DEBUG', [
            'resultId' => $resultId,
            'hasFile_production_file' => $request->hasFile('production_file'),
            'file_exists' => $request->file('production_file') !== null,
            'all_request_keys' => array_keys($request->all()),
            'all_files_keys' => array_keys($request->files->all()),
        ]);

        $validator = Validator::make($request->all(), [
            'production_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            \Log::error('updateResult Validation Failed', [
                'errors' => $validator->errors()->toArray(),
                'hasFile' => $request->hasFile('production_file'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'debug_has_file' => $request->hasFile('production_file'),  // ✅ DEBUG
            ], 422);
        }

        DB::beginTransaction();
        try {
            $result = ProductionResult::findOrFail($resultId);

            \Log::info('updateResult: Found result', ['id' => $result->id, 'current_file' => $result->production_file]);

            // Delete old file
            if ($result->production_file && Storage::disk('public')->exists($result->production_file)) {
                Storage::disk('public')->delete($result->production_file);
                \Log::info('updateResult: Old file deleted');
            }

            // Upload new file
            $file = $request->file('production_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('production_results', $filename, 'public');

            \Log::info('updateResult: New file uploaded', ['path' => $path]);

            // Update database
            $result->update(['production_file' => $path]);

            \Log::info('updateResult: Database updated');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production result updated successfully',
                'data' => [
                    'id' => $result->id,
                    'production_id' => $result->production_id,  // ✅ CORRECT KEY
                    'production_file' => $result->production_file,
                    'file_url' => Storage::url($result->production_file),
                    'updated_at' => $result->updated_at,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            \Log::error('updateResult: Production result not found', ['resultId' => $resultId]);

            return response()->json([
                'success' => false,
                'message' => 'Production result not found',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('updateResult: Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating production result',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete production result
     */
    public function deleteResult($resultId)
    {
        DB::beginTransaction();
        try {
            $result = ProductionResult::findOrFail($resultId);

            // Delete file
            if ($result->production_file && Storage::disk('public')->exists($result->production_file)) {
                Storage::disk('public')->delete($result->production_file);
            }

            $result->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production result deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Production result not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting production result',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single production result
     */
    public function getResult($resultId)
    {
        try {
            $result = ProductionResult::findOrFail($resultId);

            return response()->json([
                'success' => true,
                'message' => 'Production result retrieved successfully',
                'data' => [
                    'id' => $result->id,
                    'production_id' => $result->production_id,  // ✅ CORRECT KEY
                    'production_file' => $result->production_file,
                    'file_url' => Storage::url($result->production_file),
                    'created_at' => $result->created_at,
                    'updated_at' => $result->updated_at,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Production result not found',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production result',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete production
     */
    public function completeProduction(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $production = Production::with(['productionDetails.productionResults'])->findOrFail($id);
            $order = Order::findOrFail($production->order_id);

            if ($production->productionDetails->count() === 0) {
                throw new \Exception('Production must have at least one production detail');
            }

            $hasAllResults = true;
            foreach ($production->productionDetails as $detail) {
                if ($detail->productionResults->count() === 0) {
                    $hasAllResults = false;
                    break;
                }
            }

            if (!$hasAllResults) {
                throw new \Exception('All production details must have at least one result file');
            }

            $latestStatus = $order->statusHistory()->orderBy('created_at', 'desc')->first();

            if ($latestStatus) {
                $latestStatus->update(['end_time' => now()]);
            }

            $completedStatus = StatusHistory::create([
                'order_id' => $order->id,
                'status_stage' => 'completed',
                'start_time' => now(),
                'end_time' => now(),
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            $production = $production->fresh(['order', 'productionDetails.productionResults']);

            $totalPrice = $production->order->product_quantity * $production->order->product_price;

            return response()->json([
                'success' => true,
                'message' => 'Production completed successfully',
                'data' => [
                    'production_id' => $production->id,
                    'order_id' => $order->id,
                    'order_number' => $production->order->order_number,
                    'status' => $completedStatus->status_stage,
                    'completed_at' => $completedStatus->start_time,
                    'task_information' => [
                        'customer_name' => $production->order->cust_name,
                        'product_name' => $production->order->product_name,
                        'product_quantity' => $production->order->product_quantity . ' pcs',
                        'order_total' => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
                    ],
                    'details_completed' => $production->productionDetails->count(),
                    'total_results' => $production->productionDetails->sum(function ($detail) {
                        return $detail->productionResults->count();
                    }),
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Production not found',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

}