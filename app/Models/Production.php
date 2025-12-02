<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Production extends Model
{
    protected $table = 'productions';
    
    // ✅ ADD THIS - Allow mass assignment
    protected $fillable = [
        'order_id',
        'assigned_to',
    ];
    
    protected $guarded = [];

    public function productionDetails(): HasMany
    {
        return $this->hasMany(ProductionDetail::class);
    }

    // ✅ ADD THIS
    public function productionResult(): HasOne
    {
        return $this->hasOne(ProductionResult::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
