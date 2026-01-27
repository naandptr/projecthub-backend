<?php

namespace App\Http\Controllers\PicProduction;

use App\Http\Controllers\Controller;
use App\Models\Vendor;

class VendorController extends Controller
{
    /* GET ALL VENDORS */
    public function index()
    {
        $vendors = Vendor::select('id', 'vendor_name', 'vendor_code')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'List of vendors',
            'data' => $vendors,
        ]);
    }
}
