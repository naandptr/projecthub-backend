<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use HasFactory;

    protected $table = 'productions';
    protected $fillable = ['order_id', 'production_type', 'production_status'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function vendorDetail()
    {
        return $this->hasOne(VendorDetail::class, 'production_id', 'id');
    }

    public function inhouseDetail()
    {
        return $this->hasOne(InHouseDetail::class, 'production_id', 'id');
    }

    public function productionResult()
    {
        return $this->hasOne(productionResult::class, 'production_id', 'id');
    }
}
