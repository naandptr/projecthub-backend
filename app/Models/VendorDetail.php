<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorDetail extends Model
{
    use HasFactory;

    protected $table = 'vendor_details';
    protected $fillable = ['production_id', 'vendor_name'];

    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }
}
