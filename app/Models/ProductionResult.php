<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionResult extends Model
{
    use HasFactory;

    protected $table = 'production_results';
    protected $fillable = ['production_id', 'production_file'];

    public function production()
    {
        return $this->belongsTo(Production::class, 'production_id', 'id');
    }
}
