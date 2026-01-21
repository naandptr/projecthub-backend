<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Production extends Model
{
    use HasFactory;

    protected $table = 'productions';

    protected $fillable = [
        'order_id',
        'assigned_to',
    ];

    public function productionDetails(): HasMany
    {
        return $this->hasMany(ProductionDetail::class, 'production_id', 'id');
    }

    public function productionResult()
    {
        return $this->hasOne(ProductionResult::class, 'production_id', 'id');
    }    
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }    
    
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
}
