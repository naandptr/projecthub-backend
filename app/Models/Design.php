<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Design extends Model
{
    protected $table = 'designs';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'assigned_to',
    ];

    // Relasi ke Order
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    // Relasi ke User (Designer yang assign)
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
    public function designItem()
    {
        return $this->hasMany(DesignItem::class, 'design_id', 'id');
    }
}
