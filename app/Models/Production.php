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
        return $this->hasMany(ProductionDetail::class, 'production_id', 'id');
    }

    public function productionResult(): HasOne
    {
        return $this->hasOne(ProductionResult::class, 'production_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
}
