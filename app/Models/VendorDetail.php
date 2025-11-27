<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorDetail extends Model
{
    use HasFactory;

    protected $table = 'vendor_details';
    protected $fillable = ['production_detail_id', 'vendor_name'];

    public function productionDetail()
    {
        return $this->belongsTo(ProductionDetail::class, 'production_detail_id', 'id');
    }
}
