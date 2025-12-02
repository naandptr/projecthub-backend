<?php

namespace App\Http\Controllers\PicProduction;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\ProductionResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductionController extends Controller
{
    /**
     * ============================================
     * STEP 7A: GET All Production Tasks
     * GET /api/production/tasks
     * ============================================
     */
    public function index()
    {
        try {
            $userId = auth()->user()->id;

            $productions = Production::with([
                'order:id,order_number,cust_name,product_name,product_quantity,product_price,order_deadline',
                'productionDetails',
                'productionResult'
            ])
            ->where('assigned_to', $userId)
            ->latest('created_at')
            ->get();

            if ($productions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No production tasks assigned',
                    'data' => []
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Production tasks retrieved successfully',
                'data' => $productions->map(function ($production) {
                    return [
                        'id' => $production->id,
                        'order_id' => $production->order_id,
                        'order_number' => optional($production->order)->order_number ?? '-',
                        'customer_name' => optional($production->order)->cust_name ?? '-',
                        'product_name' => optional($production->order)->product_name ?? '-',
                        'quantity' => optional($production->order)->product_quantity ?? 0,
                        'price' => optional($production->order)->product_price ?? 0,
                        'deadline' => optional($production->order)->order_deadline ?? '-',
                        'details_count' => $production->productionDetails->count() ?? 0,
                        'has_result' => $production->productionResult ? true : false,
                        'created_at' => $production->created_at,
                        'updated_at' => $production->updated_at,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            \Log::error('ProductionController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================
     * STEP 7B: GET Detail Satu Production Task
     * GET /api/production/tasks/{id}
     * ============================================
     */
    public function show($id)
    {
        try {
            $userId = auth()->user()->id;

            $production = Production::with([
                'order',
                'productionDetails',
                'productionResult'
            ])
            ->where('id', $id)
            ->where('assigned_to', $userId)
            ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $production
            ]);

        } catch (\Exception $e) {
            \Log::error('ProductionController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Production not found or you do not have access'
            ], 404);
        }
    }

    /**
     * ============================================
     * STEP 8: START Production (Mulai Produksi)
     * POST /api/production/{id}/start
     * ============================================
     *
     * Creates StatusHistory entry with stage='in_production'
     */
    public function startProduction($id)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;

            $production = Production::where('id', $id)
                ->where('assigned_to', $userId)
                ->firstOrFail();

            // Try to create status history - gracefully continue if fails
            try {
                \App\Models\StatusHistory::create([
                    'order_id' => $production->order_id,
                    'status_stage' => 'in_production',
                    'updated_by' => $userId,
                    'start_time' => now()
                ]);
            } catch (\Exception $statusError) {
                \Log::warning('StatusHistory creation failed: ' . $statusError->getMessage());
                // Continue even if status history fails
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production started successfully',
                'data' => [
                    'production_id' => $production->id,
                    'order_id' => $production->order_id,
                    'started_at' => now()
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ProductionController@startProduction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error starting production',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================
     * STEP 9: Add Production Detail (Inhouse/Vendor)
     * POST /api/production/{id}/details
     * ============================================
     *
     * Can be called multiple times to add:
     * - Inhouse production
     * - Multiple vendor productions
     */
    public function storeDetail(Request $request, $productionId)
    {
        $validator = Validator::make($request->all(), [
            'production_type' => 'required|in:in_house,vendor',
        ], [
            'production_type.required' => 'Production type is required',
            'production_type.in' => 'Production type must be in_house or vendor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;

            // Verify production exists and belongs to user
            $production = Production::where('id', $productionId)
                ->where('assigned_to', $userId)
                ->firstOrFail();

            $detail = ProductionDetail::create([
                'production_id' => $productionId,
                'production_type' => $request->production_type,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production detail added successfully',
                'data' => $detail
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ProductionController@storeDetail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error adding production detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================
     * STEP 9B: Update Production Detail
     * PUT /api/production/details/{detailId}
     * ============================================
     */
    public function updateDetail(Request $request, $detailId)
    {
        $validator = Validator::make($request->all(), [
            'production_type' => 'sometimes|required|in:in_house,vendor',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->user()->id;

            // Step 1: Find detail first
            $detail = ProductionDetail::find($detailId);

            if (!$detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Production detail not found'
                ], 404);
            }

            // Step 2: Verify production exists
            $production = Production::findOrFail($detail->production_id);

            // Step 3: Check ownership
            if ($production->assigned_to !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this production detail'
                ], 403);
            }

            // Step 4: Update if provided
            if ($request->has('production_type')) {
                $detail->update(['production_type' => $request->production_type]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Production detail updated successfully',
                'data' => $detail
            ]);

        } catch (\Exception $e) {
            \Log::error('ProductionController@updateDetail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating production detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================
     * STEP 10: Complete Production (Upload Hasil)
     * POST /api/production/{id}/complete
     * ============================================
     *
     * Uploads production result file and creates:
     * - ProductionResult record (with file path)
     * - StatusHistory entry (stage='ready')
     *
     * Follows DesignItem pattern for file upload
     */
    public function completeProduction(Request $request, $productionId)
{
    // ✅ VALIDATION
    $request->validate([
        'file' => 'required|file|mimes:jpg,jpeg,png,pdf,zip|max:10240',
    ], [
        'file.required' => 'Production evidence file is required',
        'file.mimes' => 'File must be jpg, jpeg, png, pdf, or zip',
        'file.max' => 'File size must not exceed 10MB',
    ]);

    DB::beginTransaction();
    $path = null; // ✅ Initialize before try

    try {
        $userId = auth()->user()->id;
        $productionId = (int) $productionId;

        // ✅ STEP 1: Find production
        $production = Production::find($productionId);
        if (!$production) {
            throw new \Exception("Production ID {$productionId} not found");
        }

        // ✅ STEP 2: Verify authorization
        if ($production->assigned_to !== $userId) {
            throw new \Exception('Forbidden - You do not have permission to complete this production');
        }

        // ✅ STEP 3: Upload file
        $file = $request->file('file');
        $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('production', $filename, 'public');

        if (!$path) {
            throw new \Exception('Failed to upload file to storage');
        }

        // ✅ STEP 4: Create ProductionResult record
        $productionResult = ProductionResult::create([
            'production_id' => $productionId,
            'production_file' => $path,
        ]);

        if (!$productionResult) {
            throw new \Exception('Failed to create production result record');
        }

        // ✅ STEP 5: Create StatusHistory (graceful fail - optional)
        try {
            \App\Models\StatusHistory::create([
                'order_id' => $production->order_id,
                'status_stage' => 'ready',
                'updated_by' => $userId,
                'start_time' => now(),
                'end_time' => now()
            ]);
        } catch (\Throwable $e) {
            \Log::warning('StatusHistory failed (continuing): ' . $e->getMessage());
            // Continue even if this fails
        }

        // ✅ STEP 6: Commit
        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Production completed successfully',
            'data' => [
                'result_id' => $productionResult->id,
                'production_id' => $production->id,
                'order_id' => $production->order_id,
                'file_url' => asset('storage/' . $path),
                'file_name' => $filename,
                'status' => 'ready',
                'completed_at' => now()->format('d M Y H:i:s'),
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        // Cleanup file
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        \Log::error('ProductionController@completeProduction: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * ============================================
     * STEP 11: Kembali ke Admin (Confirm Ready)
     * PUT /api/production/{id}/confirm-ready
     * ============================================
     *
     * Confirms production is ready for admin review
     * (Status already updated in STEP 10)
     */
    public function confirmReady($id)
    {
        try {
            $userId = auth()->user()->id;

            $production = Production::where('id', $id)
                ->where('assigned_to', $userId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Production confirmed ready for Admin check',
                'data' => [
                    'production_id' => $production->id,
                    'order_id' => $production->order_id,
                    'status' => 'ready'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('ProductionController@confirmReady: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error confirming production',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}