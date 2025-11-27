<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InHouseDetail extends Model
{
    use HasFactory;

    protected $table = 'inhouse_details';
    protected $fillable = ['production_detail_id', 'start_date', 'end_date', 'production_budget'];

    public function productionDetail()
    {
        return $this->belongsTo(ProductionDetail::class, 'production_detail_id', 'id');
    }
}
