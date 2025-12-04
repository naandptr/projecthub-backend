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

    /**
     * ✅ NEW: Get the vendor detail for this production detail (if type='vendor')
     * 
     * Relationship: One production detail has ONE vendor detail
     * Only relevant when production_type = 'vendor'
     * When production_detail is deleted: vendor_detail is set to NULL
     * 
     * @return HasOne
     */
    public function vendorDetail(): HasOne
    {
        return $this->hasOne(VendorDetail::class, 'production_detail_id', 'id');
    }
    
    public function inhouseDetail(): HasOne
    {
        return $this->hasOne(InhouseDetail::class, 'production_detail_id', 'id');
    }

    /**
     * Helper method: Check if this is a vendor type detail
     * 
     * @return bool
     */
    public function isVendor(): bool
    {
        return $this->production_type === 'vendor';
    }

    /**
     * Helper method: Check if this is an in-house type detail
     * 
     * @return bool
     */
    public function isInHouse(): bool
    {
        return $this->production_type === 'in_house';
    }
}
