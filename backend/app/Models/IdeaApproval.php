<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdeaApproval extends Model
{
    protected $fillable = [
        'idea_id',
        'department_id',
        'manager_id',
        'step',
        'status',
        'comments',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'step' => 'integer',
    ];

    /**
     * Get the idea this approval belongs to
     */
    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    /**
     * Get the department for this approval
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the manager who reviewed this approval
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
