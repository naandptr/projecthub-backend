<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    use HasFactory;

    protected $table = 'designs';
    protected $fillable = ['order_id', 'assigned_to'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function designer()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function designItem()
    {
        return $this->hasMany(designItem::class, 'design_id', 'id');
    }
}
