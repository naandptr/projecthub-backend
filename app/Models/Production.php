<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Production extends Model
{
    use HasFactory;

    protected $table = 'productions';

    protected $fillable = [
        'order_id',
        'assigned_to',
    ];

    /**
     * Setiap production punya banyak detail (in_house / vendor).
     */
    public function productionDetails(): HasMany
    {
        return $this->hasMany(ProductionDetail::class, 'production_id', 'id');
    }

    /**
     * Setiap production bisa punya banyak hasil (files).
     * Tabel: production_results
     * FK: production_id (di production_results)
     * PK: id (di productions)
     */
    public function productionResults(): HasMany
    {
        return $this->hasMany(ProductionResult::class, 'production_id', 'id');
    }

    /**
     * Relasi ke Orders.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Relasi ke User (PIC produksi).
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
}
