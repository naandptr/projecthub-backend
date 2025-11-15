<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InHouseDetail extends Model
{
    use HasFactory;

    protected $table = 'inhouse_details';
    protected $fillable = ['production_id', 'start_date', 'end_date', 'production_budget'];

    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }
}
