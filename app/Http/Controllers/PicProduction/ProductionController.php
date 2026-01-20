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
     * GET PRODUCTION BY ID
     */
    public function index()
    {
        try {
            $userId = auth()->id();

            $productions = Production::whereHas('order.statusHistory', function ($query) {
                    $query->whereIn('status_stage', ['confirmed', 'in_production', 'ready', 'completed'])
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
                        'order_quantity' => $production->order->product_quantity,
                        'order_total' => $totalPrice,
                        'deadline' => $production->order->order_deadline,
                    ],
                    'order_file' => $production->order->order_file,
                    'order_file_url' => asset('storage/' . $production->order->order_file), 
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
     * GET PRODUCTION DETAILS BY ID
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
                    'start_date' => $detail->vendorDetail->start_date->format('Y-m-d'),
                    'deadline' => $detail->vendorDetail->deadline->format('Y-m-d'),
                ];
            } elseif ($detail->production_type === 'in_house' && $detail->inhouseDetail) {
                $responseData['inhouse_detail'] = [
                    'id' => $detail->inhouseDetail->id,
                    'start_date' => $detail->inhouseDetail->start_date->format('Y-m-d'),
                    'end_date' => $detail->inhouseDetail->end_date->format('Y-m-d'),
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
     * GET PRODUCTION TASK BY ID
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
                    'order_quantity' => $production->order->product_quantity,
                    'order_total' => $totalPrice,
                    'deadline' => $production->order->order_deadline,
                ],
                'order_file' => $production->order->order_file,
                'order_notes' => $production->order->order_notes,
                'status' => [
                    'status_stage' => $latestStatus?->status_stage ?? 'pending',
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

   /**
    * START PRODUCTION 
    */
    public function startProduction(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;

            $production = Production::with('order')->findOrFail($id);
            $order = $production->order;

            // Check if already assigned to another user
            if ($production->assigned_to !== null && $production->assigned_to !== $userId) {
                throw new \Exception('This production task is already assigned to another user');
            }

            // Jika sudah di-assign ke pic, cek apakah sudah ada status in_production
            if ($production->assigned_to === $userId) {
                $hasInProduction = $order->statusHistory()
                    ->where('status_stage', 'in_production')
                    ->exists();

                // Jika belum ada status in_production, buat
                if (!$hasInProduction) {
                    // Close previous status
                    StatusHistory::where('order_id', $order->id)
                        ->whereNull('end_time')
                        ->update(['end_time' => now()]);

                    // Create in_production status
                    StatusHistory::create([
                        'order_id' => $order->id,
                        'status_stage' => 'in_production',
                        'updated_by' => $userId,
                        'start_time' => now(),
                        'end_time' => null
                    ]);
                }

                $latestStatus = $order->statusHistory()->latest('start_time')->first();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'You are already working on this production task',
                    'data' => [
                        'production_id' => $production->id,
                        'order_id' => $order->id,
                        'status' => $latestStatus?->status_stage ?? 'in_production',
                        'assigned_to' => $userId,
                    ],
                ]);
            }

            // Jika belum di-assign, assign sekarang + ubah status
            $production->update(['assigned_to' => $userId]);

            // Close previous status
            $previousStatus = $order->statusHistory()->latest('created_at')->first();
            if ($previousStatus) {
                $previousStatus->update(['end_time' => now()]);
            }

            // Create in_production status
            StatusHistory::create([
                'order_id' => $order->id,
                'status_stage' => 'in_production',
                'updated_by' => $userId,
                'start_time' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production task started successfully',
                'data' => [
                    'production_id' => $production->id,
                    'order_id' => $order->id,
                    'status' => 'in_production',
                    'assigned_to' => $userId,
                ],
            ]);

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
    * STORE PRODUCTION DETAIL
    */
    public function storeDetail(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;

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

            // Get production dengan order
            $production = Production::with('order')->findOrFail($id);
            $order = $production->order;

            // VALIDASI: Harus sudah start production (assigned_to tidak null)
            if ($production->assigned_to === null) {
                throw new \Exception('You must start production first before creating production detail');
            }

            // VALIDASI: Harus user yang di-assign
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have permission to create detail for this production');
            }

            // AUTO UBAH STATUS: Jika masih confirmed, ubah ke in_production
            $latestStatus = $order->statusHistory()->latest('created_at')->first();
            
            if ($latestStatus && $latestStatus->status_stage === 'confirmed') {
                // Close confirmed status
                $latestStatus->update(['end_time' => now()]);

                // Create in_production status
                StatusHistory::create([
                    'order_id' => $order->id,
                    'status_stage' => 'in_production',
                    'updated_by' => $userId,
                    'start_time' => now(),
                ]);
            }

            $productionType = $request->production_type;

            // Validasi spesifik berdasarkan type
            if ($productionType === 'vendor') {
                $detailValidator = Validator::make($request->all(), [
                    'vendor_name' => 'required|string|max:255',
                    'start_date' => 'required|date',
                    'deadline' => 'required|date|after:start_date',
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
                    'start_date' => 'required|date',
                    'end_date' => 'required|date',
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

            // Create production detail
            $detail = ProductionDetail::create([
                'production_id' => $id,
                'production_type' => $productionType,
            ]);

            // Create vendor or inhouse detail
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }
   
   /**
     * UPDATE PRODUCTION DETAIL
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
                    'start_date' => 'required|date',
                    'deadline' => 'required|date',
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
                    'start_date' => 'required|date',
                    'end_date' => 'required|date',
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
     * DELETE PRODUCTION DETAIL
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
     * STORE PRODUCTION RESULT
     */
    public function storeResult(Request $request, $productionId)
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
            $production = Production::findOrFail($productionId);

            $file = $request->file('production_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('production_results', $filename, 'public');

            $result = ProductionResult::create([
                'production_id' => $productionId,
                'production_file' => $path,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Production result uploaded successfully',
                'data' => [
                    'id' => $result->id,
                    'production_id' => $result->production_id,
                    'production_file' => $result->production_file,
                    'file_url' => asset('storage/' . $result->production_file), // ✅ FULL URL
                    'file_name' => basename($result->production_file),
                    'created_at' => $result->created_at,
                ],
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Production not found',
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
     * UPDATE PRODUCTION RESULT
     */
    public function updateResult(Request $request, $resultId)
    {
        // âœ… DEBUG: Log request info
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
                'debug_has_file' => $request->hasFile('production_file'),  // âœ… DEBUG
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
                    'production_id' => $result->production_id,  // âœ… CORRECT KEY
                    'production_file' => $result->production_file,
                    'file_url' => asset('storage/' . $result->production_file),
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
     * GET PRODUCTION RESULT BY ID
     */
    public function getResult($productionId)
    {
        try {

            $result = Production::with('productionResults')->findOrFail($productionId);

            return response()->json([
                'success' => true,
                'message' => 'Production result retrieved successfully',
                'data' => [
                    'id' => $result->productionResults->id,
                    'production_id' => $productionId, 
                    'production_file' => $result->productionResults->production_file,
                    'file_url' => asset('storage/' . $result->productionResults->production_file)
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
}