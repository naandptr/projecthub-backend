<?php

namespace App\Http\Controllers\PicProduction;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\InhouseDetail;
use App\Models\ProductionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InhouseDetailController extends Controller
{
    /**
     * ============================================
     * CRUD: Inhouse Production Details
     * For production_detail where production_type='in_house'
     * ============================================
     */

    /**
     * STORE - Create Inhouse Detail
     * POST /api/production/details/{detailId}/inhouse
     * 
     * Only called when production_detail.production_type = 'in_house'
     */
    public function store(Request $request, $detailId)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after:start_date',
            'production_budget' => 'required|numeric|min:0',
        ], [
            'start_date.required' => 'Start date is required',
            'start_date.date_format' => 'Start date must be YYYY-MM-DD format',
            'end_date.required' => 'End date is required',
            'end_date.date_format' => 'End date must be YYYY-MM-DD format',
            'end_date.after' => 'End date must be after start date',
            'production_budget.required' => 'Production budget is required',
            'production_budget.numeric' => 'Production budget must be a number',
            'production_budget.min' => 'Production budget cannot be negative',
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
            $detailId = (int) $detailId;

            // ✅ STEP 1: Find production detail
            $detail = ProductionDetail::find($detailId);
            if (!$detail) {
                throw new \Exception("Production detail ID {$detailId} not found");
            }

            // ✅ STEP 2: Verify production_type is 'in_house'
            if ($detail->production_type !== 'in_house') {
                throw new \Exception('This production detail is not marked as in_house type');
            }

            // ✅ STEP 3: Verify user has access to this production
            $production = $detail->production;
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have access to this production');
            }

            // ✅ STEP 4: Check if inhouse detail already exists
            $inhouseDetail = InhouseDetail::where('production_detail_id', $detailId)->first();
            
            if ($inhouseDetail) {
                // Update existing inhouse detail
                $inhouseDetail->update([
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'production_budget' => $request->production_budget,
                ]);
            } else {
                // Create new inhouse detail
                $inhouseDetail = InhouseDetail::create([
                    'production_detail_id' => $detailId,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'production_budget' => $request->production_budget,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inhouse production details saved successfully',
                'data' => $inhouseDetail
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('InhouseDetailController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * SHOW - Get Inhouse Detail
     * GET /api/production/details/{detailId}/inhouse
     */
    public function show($detailId)
    {
        try {
            $userId = auth()->user()->id;
            $detailId = (int) $detailId;

            // ✅ STEP 1: Find production detail
            $detail = ProductionDetail::find($detailId);
            if (!$detail) {
                throw new \Exception("Production detail ID {$detailId} not found");
            }

            // ✅ STEP 2: Verify user has access
            $production = $detail->production;
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have access to this production');
            }

            // ✅ STEP 3: Get inhouse detail
            $inhouseDetail = InhouseDetail::where('production_detail_id', $detailId)->first();
            
            if (!$inhouseDetail) {
                return response()->json([
                    'success' => true,
                    'message' => 'No inhouse details found for this production detail',
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Inhouse details retrieved successfully',
                'data' => $inhouseDetail
            ]);

        } catch (\Exception $e) {
            \Log::error('InhouseDetailController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * UPDATE - Modify Inhouse Detail
     * PUT /api/production/details/{detailId}/inhouse
     */
    public function update(Request $request, $detailId)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after:start_date',
            'production_budget' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = auth()->user()->id;
            $detailId = (int) $detailId;

            // ✅ STEP 1: Find production detail
            $detail = ProductionDetail::find($detailId);
            if (!$detail) {
                throw new \Exception("Production detail ID {$detailId} not found");
            }

            // ✅ STEP 2: Verify user has access
            $production = $detail->production;
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have access to this production');
            }

            // ✅ STEP 3: Find and update inhouse detail
            $inhouseDetail = InhouseDetail::where('production_detail_id', $detailId)->first();
            
            if (!$inhouseDetail) {
                throw new \Exception("Inhouse details not found for this production detail");
            }

            $inhouseDetail->update([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'production_budget' => $request->production_budget,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inhouse details updated successfully',
                'data' => $inhouseDetail
            ]);

        } catch (\Exception $e) {
            \Log::error('InhouseDetailController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DESTROY - Delete Inhouse Detail
     * DELETE /api/production/details/{detailId}/inhouse
     */
    public function destroy($detailId)
    {
        try {
            $userId = auth()->user()->id;
            $detailId = (int) $detailId;

            // ✅ STEP 1: Find production detail
            $detail = ProductionDetail::find($detailId);
            if (!$detail) {
                throw new \Exception("Production detail ID {$detailId} not found");
            }

            // ✅ STEP 2: Verify user has access
            $production = $detail->production;
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have access to this production');
            }

            // ✅ STEP 3: Find and delete inhouse detail
            $inhouseDetail = InhouseDetail::where('production_detail_id', $detailId)->first();
            
            if (!$inhouseDetail) {
                throw new \Exception("Inhouse details not found for this production detail");
            }

            $inhouseDetail->delete();

            return response()->json([
                'success' => true,
                'message' => 'Inhouse details deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('InhouseDetailController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
