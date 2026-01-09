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
        $productions = Production::with([
            'order',
            'assignedTo',
            'order.statusHistory'
        ])
        ->whereHas('order.statusHistory', function ($q) {
            $q->where('status_stage', 'confirmed');
        })
        ->get();

        $data = $productions->map(function ($production) {
            return [
                'id' => $production->id,
                'order_id' => $production->order->id,
                'order_number' => $production->order->order_number,
                'cust_name' => $production->order->cust_name,
                'product_name' => $production->order->product_name,
                'product_quantity' => $production->order->product_quantity,
                'product_price' => number_format($production->order->product_price, 0, ',', '.'),
                'order_deadline' => $production->order->order_deadline,
                'order_file_url' => asset('storage/' . $production->order->order_file),
                'order_file_name' => basename($production->order->order_file),
                'current_status' => $production->order->latestStatus?->status_stage ?? 'pending',
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'List of tasks',
            'data' => $data
        ]);
    }

    /**
     * Get production tasks with status filter (default: confirmed)
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
                        $q->select('id', 'order_number', 'cust_name', 'cust_phone', 'cust_address', 
                                  'order_date', 'order_deadline', 'product_name', 'product_quantity', 
                                  'product_price', 'order_file', 'order_notes');
                    },
                    'order.statusHistory' => function ($query) {
                        $query->with('updatedBy:id,username')
                              ->orderBy('created_at', 'desc');
                    },
                    'productionDetails' => function ($q) {
                        $q->select('id', 'production_id', 'production_type')
                          ->with(['productionResults', 'vendorDetail', 'inhouseDetail']);
                    }
                ])
                ->select('id', 'order_id', 'assigned_to', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedProductions = $productions->map(function ($production) {
                return $this->formatProductionData($production);
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
                    $q->select('id', 'order_number', 'cust_name', 'cust_phone', 'cust_address', 
                              'order_date', 'order_deadline', 'product_name', 'product_quantity', 
                              'product_price', 'order_file', 'order_notes');
                },
                'order.statusHistory' => function ($query) {
                    $query->with('updatedBy:id,username')
                          ->orderBy('created_at', 'desc');
                },
                'productionDetails' => function ($q) {
                    $q->select('id', 'production_id', 'production_type')
                      ->with(['productionResults', 'vendorDetail', 'inhouseDetail']);
                }
            ])
            ->select('id', 'order_id', 'assigned_to', 'created_at', 'updated_at')
            ->findOrFail($id);

            $formattedProduction = $this->formatProductionData($production, true);

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
     * REVISI: Start production - Assign dan return full task data
     * 
     * Flow:
     * 1. If tidak di-assign → assign ke current user + create status history
     * 2. If sudah di-assign ke current user → return data (continue)
     * 3. If sudah di-assign ke user lain → throw error
     */
    public function startProduction(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;
            $production = Production::findOrFail($id);
            $order = Order::findOrFail($production->order_id);

            // Check if already assigned to another user
            if ($production->assigned_to !== null && $production->assigned_to !== $userId) {
                throw new \Exception('This production task is already assigned to another user');
            }

            // If not assigned, assign and create status history
            if ($production->assigned_to !== $userId) {
                $production->update(['assigned_to' => $userId]);

                // End previous status
                $previousStatus = $order->statusHistory()->latest('created_at')->first();
                if ($previousStatus) {
                    $previousStatus->update(['end_time' => now()]);
                }

                // Create new status
                StatusHistory::create([
                    'order_id' => $order->id,
                    'status_stage' => 'in_production',
                    'updated_by' => $userId,
                    'start_time' => now(),
                ]);
            }

            // Load fresh data
            $production = $production->fresh([
                'order',
                'order.statusHistory.updatedBy',
                'productionDetails.productionResults',
                'productionDetails.vendorDetail',
                'productionDetails.inhouseDetail'
            ]);

            // Format response
            $formattedProduction = $this->formatProductionData($production, true);
            $formattedProduction['assigned_to'] = $production->assigned_to;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'You are now working on this production task',
                'data' => $formattedProduction,
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
     * Store production detail (vendor or in_house)
     * 
     * REVISI: Unified API untuk vendor dan in_house
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
                'data' => [
                    'id' => $detail->id,
                    'production_id' => $detail->production_id,
                    'production_type' => $detail->production_type,
                    'created_at' => $detail->created_at,
                ],
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
     * REVISI: Delete production detail dan semua relasi
     */
    public function deleteDetail($detailId)
    {
        DB::beginTransaction();
        try {
            $detail = ProductionDetail::with([
                'productionResults',
                'vendorDetail',
                'inhouseDetail'
            ])->findOrFail($detailId);

            // Delete all production results
            foreach ($detail->productionResults as $result) {
                if ($result->production_file && Storage::disk('public')->exists($result->production_file)) {
                    Storage::disk('public')->delete($result->production_file);
                }
                $result->delete();
            }

            // Delete vendor detail if exists
            if ($detail->vendorDetail) {
                $detail->vendorDetail->delete();
            }

            // Delete inhouse detail if exists
            if ($detail->inhouseDetail) {
                $detail->inhouseDetail->delete();
            }

            // Delete detail itself
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
     * Store vendor or inhouse detail info (UNIFIED API)
     */
    public function storeDetailInfo(Request $request, $detailId)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;
            $detail = ProductionDetail::findOrFail($detailId);

            // Verify user has access
            $production = $detail->production;
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have access to this production');
            }

            if ($detail->production_type === 'vendor') {
                $validator = Validator::make($request->all(), [
                    'vendor_name' => 'required|string|max:255',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                $vendorDetail = VendorDetail::updateOrCreate(
                    ['production_detail_id' => $detailId],
                    ['vendor_name' => $request->vendor_name]
                );

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Vendor information saved successfully',
                    'data' => [
                        'detail_id' => $detail->id,
                        'production_type' => $detail->production_type,
                        'vendor_detail' => [
                            'id' => $vendorDetail->id,
                            'vendor_name' => $vendorDetail->vendor_name,
                        ],
                    ],
                ], 201);

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

                $inhouseDetail = InhouseDetail::updateOrCreate(
                    ['production_detail_id' => $detailId],
                    [
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'production_budget' => $request->production_budget,
                    ]
                );

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Inhouse production details saved successfully',
                    'data' => [
                        'detail_id' => $detail->id,
                        'production_type' => $detail->production_type,
                        'inhouse_detail' => [
                            'id' => $inhouseDetail->id,
                            'start_date' => $inhouseDetail->start_date,
                            'end_date' => $inhouseDetail->end_date,
                            'production_budget' => $inhouseDetail->production_budget,
                        ],
                    ],
                ], 201);

            } else {
                throw new \Exception('Invalid production type');
            }

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
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get vendor or inhouse detail info (UNIFIED API)
     */
    public function getDetailInfo($detailId)
    {
        try {
            $userId = auth()->user()->id;
            $detail = ProductionDetail::with(['vendorDetail', 'inhouseDetail'])->findOrFail($detailId);

            // Verify user has access
            $production = $detail->production;
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have access to this production');
            }

            $responseData = [
                'detail_id' => $detail->id,
                'production_type' => $detail->production_type,
            ];

            if ($detail->production_type === 'vendor' && $detail->vendorDetail) {
                $responseData['vendor_detail'] = [
                    'id' => $detail->vendorDetail->id,
                    'vendor_name' => $detail->vendorDetail->vendor_name,
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
                'message' => 'Production detail information retrieved successfully',
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
                'message' => $e->getMessage(),
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
