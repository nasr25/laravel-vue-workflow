<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentManager extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'permission', // 'viewer' or 'approver'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Helper methods
    public function isApprover()
    {
        return $this->permission === 'approver';
    }

    public function isViewer()
    {
        return $this->permission === 'viewer';
    }
}
