<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionResult extends Model
{
    protected $table = 'production_results';
    
    // ✅ ADD THIS - Allow mass assignment
    protected $fillable = [
        'production_id',
        'production_file',
    ];
    
    protected $guarded = [];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }
}
