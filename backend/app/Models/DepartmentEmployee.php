<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentEmployee extends Model
{
    protected $fillable = [
        'department_id',
        'user_id',
        'permission'
    ];

    /**
     * Get the department this employee belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user (employee)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this employee has approver permission
     */
    public function canApprove()
    {
        return $this->permission === 'approver';
    }

    /**
     * Check if this employee is a viewer only
     */
    public function isViewer()
    {
        return $this->permission === 'viewer';
    }

    /**
     * Scope to get only approvers
     */
    public function scopeApprovers($query)
    {
        return $query->where('permission', 'approver');
    }

    /**
     * Scope to get only viewers
     */
    public function scopeViewers($query)
    {
        return $query->where('permission', 'viewer');
    }
}
