<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    use HasFactory;

    protected $table = 'designs';
    protected $fillable = ['assigned_to'];

    public function designItem()
    {
        return $this->hasMany(designItem::class, 'design_id', 'id');
    }
}
