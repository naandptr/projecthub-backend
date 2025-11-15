<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spk extends Model
{
    use HasFactory;

    protected $table = 'spk';
    protected $fillable = ['order_id', 'spk_number', 'spk_date', 'assigned_to'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
