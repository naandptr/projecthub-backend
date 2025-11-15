<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';
    protected $fillable = ['order_id', 'payment_type', 'payment_amount', 'payment_date', 'payment_method', 'payment_status'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
