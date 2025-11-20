<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpkOrder extends Model
{
    use HasFactory;

    protected $table = 'spk_orders';
    protected $fillable = ['spk_id', 'order_id'];

    public function spk()
    {
        return $this->belongsTo(Spk::class, 'spk_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
