<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InhouseDetail extends Model
{
    use HasFactory;

    protected $table = 'inhouse_details';

    protected $fillable = [
        'production_detail_id',
        'start_date',
        'end_date',
        'production_budget',
    ];

    /**
     * Cast kolom date
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'production_budget' => 'decimal:2',
    ];

    /**
     * Inhouse detail belongs to production detail
     */
    public function productionDetail(): BelongsTo
    {
        return $this->belongsTo(ProductionDetail::class, 'production_detail_id', 'id');
    }
}