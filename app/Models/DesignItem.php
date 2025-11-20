<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignItem extends Model
{
    protected $table = 'design_items';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'design_id',
        'design_file',
        'design_notes',
        'design_status', // revision, approved
    ];

    // Relasi ke Design
    public function design()
    {
        return $this->belongsTo(Design::class, 'design_id', 'id');
    }
}
