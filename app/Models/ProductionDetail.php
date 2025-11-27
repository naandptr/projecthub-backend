<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionDetail extends Model
{
    use HasFactory;

    protected $table = 'production_details';
    protected $fillable = ['production_id', 'production_type'];

    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }

    public function vendorDetail()
    {
        return $this->hasOne(VendorDetail::class, 'production_detail_id', 'id');
    }

    public function inhouseDetail()
    {
        return $this->hasOne(InHouseDetail::class, 'production_detail_id', 'id');
    }
}
