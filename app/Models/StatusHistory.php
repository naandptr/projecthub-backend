<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    use HasFactory;

    protected $table = 'status_history';
    protected $fillable = ['order_id', 'status_stage', 'updated_by', 'start_time', 'end_time'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
