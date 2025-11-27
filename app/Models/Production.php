<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    use HasFactory;

    protected $table = 'productions';
    protected $fillable = ['order_id', 'assigned_to'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    public function productionDetail()
    {
        return $this->hasOne(ProductionDetail::class, 'production_id', 'id');
    }

    public function productionResult()
    {
        return $this->hasOne(ProductionResult::class, 'production_id', 'id');
    }
}
