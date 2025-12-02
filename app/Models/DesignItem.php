<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignItem extends Model
{
    use HasFactory;

    /**
     * Table name
     */
    protected $table = 'design_items';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'design_id',
        'design_file',
        'design_notes',
        'design_status', // in_progress, revision, approved
    ];

    /**
     * Casts
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Append custom attributes to JSON
     */
    protected $appends = [
        'file_url',
        'file_name',
        'status_label',
    ];

    /**
     * ============================================
     * RELATIONSHIPS
     * ============================================
     */

    /**
     * Design yang mempunyai item ini
     */
    public function design()
    {
        return $this->belongsTo(Design::class, 'design_id', 'id');
    }

    /**
     * ============================================
     * ACCESSORS / MUTATORS
     * ============================================
     */

    /**
     * Get file URL
     */
    public function getFileUrlAttribute()
    {
        if ($this->design_file) {
            return asset('storage/' . $this->design_file);
        }
        return null;
    }

    /**
     * Get file name only
     */
    public function getFileNameAttribute()
    {
        return basename($this->design_file ?? '');
    }

    /**
     * Get status label (human readable)
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'in_progress' => 'In Progress',
            'revision' => 'Need Revision',
            'approved' => 'Approved',
        ];

        return $labels[$this->design_status] ?? ucfirst($this->design_status);
    }

    /**
     * ============================================
     * SCOPES
     * ============================================
     */

    /**
     * Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('design_status', $status);
    }

    /**
     * Filter by design
     */
    public function scopeByDesignId($query, $designId)
    {
        return $query->where('design_id', $designId);
    }

    /**
     * Latest items first
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * ============================================
     * METHODS
     * ============================================
     */

    /**
     * Mark as in progress
     */
    public function markInProgress()
    {
        return $this->update(['design_status' => 'in_progress']);
    }

    /**
     * Mark as revision needed
     */
    public function markRevision()
    {
        return $this->update(['design_status' => 'revision']);
    }

    /**
     * Mark as approved
     */
    public function markApproved()
    {
        return $this->update(['design_status' => 'approved']);
    }

    /**
     * Check if can be edited (only in_progress & revision)
     */
    public function canBeEdited()
    {
        return in_array($this->design_status, ['in_progress', 'revision']);
    }

    /**
     * Check if can be deleted
     */
    public function canBeDeleted()
    {
        return $this->design_status === 'revision';
    }
}