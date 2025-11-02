<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Idea extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'pdf_file_path',
        'status',
        'current_approval_step',
    ];

    protected $casts = [
        'current_approval_step' => 'integer',
    ];

    /**
     * Get the user who submitted the idea
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all approvals for this idea
     */
    public function approvals()
    {
        return $this->hasMany(IdeaApproval::class)->orderBy('step');
    }

    /**
     * Get current approval step
     */
    public function currentApproval()
    {
        return $this->hasOne(IdeaApproval::class)
            ->where('step', $this->current_approval_step)
            ->where('status', 'pending');
    }

    /**
     * Check if idea is approved by all departments
     */
    public function isFullyApproved()
    {
        return $this->status === 'approved' && $this->current_approval_step > 4;
    }
}
