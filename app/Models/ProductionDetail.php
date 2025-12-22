<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductionDetail extends Model
{
    use HasFactory;

    protected $table = 'production_details';

    /**
     * ✅ Allow mass assignment for these fields
     */
    protected $fillable = [
        'production_id',      // Foreign key to productions
        'production_type',    // Either 'in_house' or 'vendor'
    ];

    /**
     * Get the production that owns this production detail
     * 
     * Relationship: One production detail belongs to ONE production
     * When production is deleted: production_detail is deleted
     * 
     * @return BelongsTo
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }

    public function inHouseDetail(): HasOne
    {
        return $this->hasOne(InHouseDetail::class, 'production_detail_id', 'id');
    }

    public function vendorDetail(): HasOne
    {
        return $this->hasOne(VendorDetail::class, 'production_detail_id', 'id');
    }
}
