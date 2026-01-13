<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDetail extends Model
{
    use HasFactory;

    protected $table = 'vendor_details';

    /**
     * ✅ UPDATED: Tambahkan start_date dan deadline
     */
    protected $fillable = [
        'production_detail_id',
        'vendor_name',
        'start_date',
        'deadline',
    ];

    /**
     * ✅ TAMBAHKAN: Cast kolom date
     */
    protected $casts = [
        'start_date' => 'date',
        'deadline' => 'date',
    ];

    /**
     * Vendor detail belongs to production detail
     */
    public function productionDetail(): BelongsTo
    {
        return $this->belongsTo(ProductionDetail::class, 'production_detail_id', 'id');
    }
}