<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'description', 'approval_order', 'is_active', 'parent_id'];

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
     * Get employees assigned to this department
     */
    public function employees()
    {
        return $this->belongsToMany(User::class, 'department_employees')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /**
     * Get department employee records
     */
    public function departmentEmployees()
    {
        return $this->hasMany(DepartmentEmployee::class);
    }

    /**
     * Get workflow steps for this department
     */
    public function workflowSteps()
    {
        return $this->hasMany(WorkflowStep::class);
    }

    /**
     * Get approvals for this department
     */
    public function approvals()
    {
        return $this->hasMany(IdeaApproval::class);
    }

    /**
     * Get the parent department
     */
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Get child departments
     */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id')->with('children');
    }
}
