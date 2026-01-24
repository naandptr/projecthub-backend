<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $table = 'vendors';
    protected $fillable = ['vendor_name', 'vendor_code', 'vendor_contact', 'vendor_address'];

    public static function generateVendorCode()
    {
        $prefix = "VE";

        $lastVendor = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $next = $lastVendor
            ? intval(substr($lastVendor->vendor_code, -3)) + 1
            : 1;

        $counter = str_pad($next, 4, '0', STR_PAD_LEFT);

        return $prefix . "-" . $counter;
    }

    public function vendorDetails()
    {
        return $this->hasMany(VendorDetail::class, 'vendor_id', 'id');
    }
}
