<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $fillable = ['order_number', 'created_by', 'cust_name', 'cust_phone', 'cust_address', 'order_date', 'order_deadline', 'product_name', 'product_quantity', 'product_price', 'order_file', 'order_notes'];
    public $timestamps = true;
    
    public static function generateOrderNumber()
    {
        $prefix = "HY-";
        $date = now()->format('dmy'); 

        $lastOrder = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $next = $lastOrder
            ? intval(substr($lastOrder->order_number, -5)) + 1
            : 1;

        $counter = str_pad($next, 5, '0', STR_PAD_LEFT);

        return $prefix . $date . "-" . $counter;
    }

    public static function compressAndStoreImage($file)
    {
        $image = Image::make($file)
            ->orientate()
            ->resize(1600, 1600, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode('jpg', 60);

        $filename = 'orders/' . uniqid() . '.jpg';

        Storage::disk('public')->put($filename, $image);

        return $filename;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function design()
    {
        return $this->hasOne(Design::class, 'order_id', 'id');
    }

    public function spks()
    {
        return $this->belongsToMany(Spk::class, 'spk_orders', 'order_id', 'spk_id');
    }

    public function spkOrder()
    {
        return $this->hasOne(Spk::class, 'order_id', 'id');
    }

    public function production()
    {
        return $this->hasOne(Production::class, 'order_id', 'id');
    }

    public function payment()
    {
        return $this->hasMany(Payment::class, 'order_id', 'id');
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class, 'order_id', 'id');
    }

    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class, 'order_id', 'id');
    }

    public function notification()
    {
        return $this->hasMany(Notification::class, 'order_id', 'id');
    }
}
