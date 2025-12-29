<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionDetail extends Model
{
    use HasFactory;

    protected $table = 'production_details';

    protected $fillable = [
        'production_id',
        'production_type',
    ];

    /**
     * Detail milik satu Production.
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }

    /**
     * Detail in-house (jika production_type = 'in_house').
     */
    public function inHouseDetail(): HasOne
    {
        return $this->hasOne(InHouseDetail::class, 'production_detail_id', 'id');
    }

    /**
     * Detail vendor (jika production_type = 'vendor').
     */
    public function vendorDetail(): HasOne
    {
        return $this->hasOne(VendorDetail::class, 'production_detail_id', 'id');
    }

    /**
     
     * Catatan:
     * - Tabel production_results hanya punya FK ke productions (production_id),
     *   bukan ke production_details.
     * - Karena itu kita pakai:
     *   foreign key  = production_id (di production_results)
     *   local key    = production_id (di production_details)
     */
    public function productionResults(): HasMany
    {
        return $this->hasMany(ProductionResult::class, 'production_id', 'production_id');
    }
}
