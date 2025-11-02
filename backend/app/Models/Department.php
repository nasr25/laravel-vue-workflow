<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'description', 'approval_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get managers assigned to this department
     */
    public function managers()
    {
        return $this->belongsToMany(User::class, 'department_managers');
    }

    /**
     * Get approvals for this department
     */
    public function approvals()
    {
        return $this->hasMany(IdeaApproval::class);
    }
}
