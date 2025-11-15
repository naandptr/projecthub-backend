<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignItem extends Model
{
    use HasFactory;

    protected $table = 'design_items';
    protected $fillable = ['design_id', 'design_file', 'design_notes', 'design_status'];

    public function design()
    {
        return $this->belongsTo(Design::class, 'design_id', 'id');
    }

}
