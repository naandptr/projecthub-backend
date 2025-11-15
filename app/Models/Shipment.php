<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $table = 'shipments';
    protected $fillable = ['order_id', 'service_type', 'courier_service', 'shipment_date', 'tracking_number', 'cost_type', 'shipment_notes'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
