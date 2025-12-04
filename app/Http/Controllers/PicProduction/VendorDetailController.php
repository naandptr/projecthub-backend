<?php

namespace App\Http\Controllers\PicProduction;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\VendorDetail;
use App\Models\ProductionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VendorDetailController extends Controller
{
    /**
     * ============================================
     * STEP 9C: Add Vendor Information
     * POST /api/production/details/{detailId}/vendor
     * ============================================
     * 
     * Only called when production_detail.production_type = 'vendor'
     * Stores vendor information (company name, person name, etc.)
     */
    public function storeVendor(Request $request, $detailId)
    {
        $validator = Validator::make($request->all(), [
            'vendor_name' => 'required|string|max:255',
        ], [
            'vendor_name.required' => 'Vendor name is required',
            'vendor_name.string' => 'Vendor name must be a string',
            'vendor_name.max' => 'Vendor name cannot exceed 255 characters',
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

            // ✅ STEP 2: Verify production_type is 'vendor'
            if ($detail->production_type !== 'vendor') {
                throw new \Exception('This production detail is not marked as vendor type');
            }

            // ✅ STEP 3: Verify user has access to this production
            $production = $detail->production;
            if ($production->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have access to this production');
            }

            // ✅ STEP 4: Check if vendor detail already exists
            $vendorDetail = VendorDetail::where('production_detail_id', $detailId)->first();
            
            if ($vendorDetail) {
                // Update existing vendor
                $vendorDetail->update([
                    'vendor_name' => $request->vendor_name,
                ]);
            } else {
                // Create new vendor detail
                $vendorDetail = VendorDetail::create([
                    'production_detail_id' => $detailId,
                    'vendor_name' => $request->vendor_name,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vendor information saved successfully',
                'data' => $vendorDetail
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('VendorDetailController@storeVendor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ============================================
     * GET Vendor Information
     * GET /api/production/details/{detailId}/vendor
     * ============================================
     * 
     * Retrieve vendor details for a specific production detail
     */
    public function getVendor($detailId)
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

            // ✅ STEP 3: Get vendor detail
            $vendorDetail = VendorDetail::where('production_detail_id', $detailId)->first();
            
            if (!$vendorDetail) {
                return response()->json([
                    'success' => true,
                    'message' => 'No vendor information found for this production detail',
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vendor information retrieved successfully',
                'data' => $vendorDetail
            ]);

        } catch (\Exception $e) {
            \Log::error('VendorDetailController@getVendor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ============================================
     * Update Vendor Information
     * PUT /api/production/details/{detailId}/vendor
     * ============================================
     * 
     * Update existing vendor information
     */
    public function updateVendor(Request $request, $detailId)
    {
        $validator = Validator::make($request->all(), [
            'vendor_name' => 'required|string|max:255',
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

            // ✅ STEP 3: Find and update vendor detail
            $vendorDetail = VendorDetail::where('production_detail_id', $detailId)->first();
            
            if (!$vendorDetail) {
                throw new \Exception("Vendor information not found for this production detail");
            }

            $vendorDetail->update([
                'vendor_name' => $request->vendor_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vendor information updated successfully',
                'data' => $vendorDetail
            ]);

        } catch (\Exception $e) {
            \Log::error('VendorDetailController@updateVendor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ============================================
     * Delete Vendor Information
     * DELETE /api/production/details/{detailId}/vendor
     * ============================================
     * 
     * Remove vendor information
     */
    public function deleteVendor($detailId)
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

            // ✅ STEP 3: Find and delete vendor detail
            $vendorDetail = VendorDetail::where('production_detail_id', $detailId)->first();
            
            if (!$vendorDetail) {
                throw new \Exception("Vendor information not found for this production detail");
            }

            $vendorDetail->delete();

            return response()->json([
                'success' => true,
                'message' => 'Vendor information deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('VendorDetailController@deleteVendor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
