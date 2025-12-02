<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionDetail extends Model
{
    protected $table = 'production_details';

    // ✅ CRITICAL: Allow mass assignment
    protected $fillable = [
        'production_id',      // Foreign key
        'production_type',    // 'in_house' or 'vendor'
    ];

    protected $guarded = [];

    /**
     * Get the production that owns this detail
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }
}
