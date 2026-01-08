<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_id',
        'production_file',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke Production.
     * 
     * ProductionResult punya FK production_id yang menunjuk ke Production.id
     * Database: production_results.production_id → productions.id
     * 
     * Setiap Production bisa punya banyak ProductionResults (files).
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }
}