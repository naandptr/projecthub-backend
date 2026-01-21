<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class Design extends Model
{
    use HasFactory; 

    protected $table = 'designs';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'assigned_to',
    ];    

    public static function compressAndStoreImage(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver());

        $image = $manager->read($file->getPathname());

        $image->scaleDown(width: 1280);

        $filename = 'designs/' . uniqid() . '.jpg';

        Storage::disk('public')->put(
            $filename,
            (string) $image->toJpeg(60)
        );

        return $filename;
    }
    
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }    
    
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }    
    
    public function designItems()
    {
        return $this->hasMany(DesignItem::class, 'design_id', 'id');
    }
}
