<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\VendorDetail;

class VendorController extends Controller
{
    /* GET ALL VENDORS */
    public function index()
    {       
        $vendors = Vendor::orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'List of vendors',
            'data' => $vendors
        ]);
    }

    /* GET VENDOR BY ID */
    public function show($vendorId)
    {
        $vendor = Vendor::find($vendorId);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $vendor
        ]);
    }

    /* CREATE VENDOR */
    public function store(Request $request)
    {        
        $request->validate([
            'vendor_name' => 'required|string|max:100',
            'vendor_contact' => 'nullable|string|max:100',
            'vendor_address' => 'nullable|string|max:255'
        ]);

        $vendor = Vendor::create([
            'vendor_name' => $request->vendor_name,
            'vendor_code' => Vendor::generateVendorCode(),
            'vendor_contact' => $request->vendor_contact,
            'vendor_address' => $request->vendor_address
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor created successfully',
            'data' => [
                'id' => $vendor->id,
                'vendor_name' => $vendor->vendor_name,
                'vendor_code' => $vendor->vendor_code,
                'vendor_contact' => $vendor->vendor_contact,
                'vendor_address' => $vendor->vendor_address
            ]
        ]);
    }

    /* UPDATE VENDOR */
    public function update(Request $request, $vendorId)
    {        
        $request->validate([
            'vendor_name' => 'required|string|max:100',
            'vendor_contact' => 'nullable|string|max:100',
            'vendor_address' => 'nullable|string|max:255'
        ]);

        $vendor = Vendor::findOrFail($vendorId);

        $vendor->update($request->only([
            'vendor_name',
            'vendor_contact',
            'vendor_address'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Vendor updated successfully',
            'data' => [
                'id' => $vendor->id,
                'vendor_name' => $vendor->vendor_name,
                'vendor_code' => $vendor->vendor_code,
                'vendor_contact' => $vendor->vendor_contact,
                'vendor_address' => $vendor->vendor_address
            ]
        ]);
    }

    public function destroy($vendorId)
    {
        $vendor = Vendor::findOrFail($vendorId);

        // Prevent deletion if vendor has associated records in other tables
        if (
            VendorDetail::where('vendor_id', $vendor->id)->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor is used in other data!'
            ], 400);
        }

        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vendor deleted successfully'
        ]);
    }
}
