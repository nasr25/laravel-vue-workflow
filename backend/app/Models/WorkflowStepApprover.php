<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStepApprover extends Model
{
    protected $fillable = [
        'workflow_step_id',
        'user_id',
        'role'
    ];

    /**
     * Get the workflow step this approver belongs to
     */
    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /**
     * Get the user assigned as approver
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this approver is an employee
     */
    public function isEmployee()
    {
        return $this->role === 'employee';
    }

    /**
     * Check if this approver is a manager
     */
    public function isManager()
    {
        return $this->role === 'manager';
    }
}
