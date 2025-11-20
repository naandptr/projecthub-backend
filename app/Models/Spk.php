<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Spk extends Model
{
    use HasFactory;

    protected $table = 'spk';
    protected $fillable = ['spk_number', 'spk_date', 'assigned_to'];

    public static function generateSpkNumber()
    {
        $prefix = "SPK-";
        $date = now()->format('dmY');

        $last = self::whereDate('created_at', today())
                    ->orderBy('id', 'desc')
                    ->first();

        $next = $last ? intval(substr($last->spk_number, -5)) + 1 : 1;
        $counter = str_pad($next, 5, '0', STR_PAD_LEFT);

        return $prefix . $date . "-" . $counter;
    }

    public function production()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'spk_orders', 'spk_id', 'order_id');
    }

    public function spkOrder()
    {
        return $this->hasMany(SpkOrder::class, 'spk_id', 'id');
    }
}
