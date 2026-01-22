<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class Production extends Model
{
    use HasFactory;

    protected $table = 'productions';

    protected $fillable = [
        'order_id',
        'assigned_to',
    ];

    public static function compressAndStoreImage(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver());

        $image = $manager->read($file->getPathname());

        $image->scaleDown(width: 1280);

        $filename = 'production_results/' . uniqid() . '.jpg';

        Storage::disk('public')->put(
            $filename,
            (string) $image->toJpeg(60)
        );

        return $filename;
    }

    public function productionDetails(): HasMany
    {
        return $this->hasMany(ProductionDetail::class, 'production_id', 'id');
    }

    public function productionResult()
    {
        return $this->hasOne(ProductionResult::class, 'production_id', 'id');
    }    
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }    
    
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
}
