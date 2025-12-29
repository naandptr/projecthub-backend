<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionResult extends Model
{
    use HasFactory;

    protected $table = 'production_results';

    protected $fillable = [
        'production_id',
        'production_file',
    ];

    /**
     * File hasil milik satu Production.
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }
}
